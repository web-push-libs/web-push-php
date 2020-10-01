<?php

declare(strict_types=1);

/*
 * This file is part of the WebPush library.
 *
 * (c) Louis Lagrange <lagrange.louis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Minishlink\WebPush\VAPID;

use Assert\Assertion;
use Minishlink\WebPush\Extension;
use Minishlink\WebPush\Notification;
use Minishlink\WebPush\Subscription;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Safe\DateTimeImmutable;
use function Safe\parse_url;
use function Safe\sprintf;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class VAPID implements Extension
{
    private const DEFAULT_CACHE_KEY = 'WEB_PUSH_DEFAULT_CACHE_KEY';

    private JWSProvider $jwsProvider;
    private ?CacheInterface $cache = null;
    private ?string $cacheKey = self::DEFAULT_CACHE_KEY;
    private string $expirationTime = 'now +1h';
    private LoggerInterface $logger;
    private string $subject;

    public function __construct(string $subject, JWSProvider $jwsProvider)
    {
        $this->subject = $subject;
        $this->jwsProvider = $jwsProvider;
        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function setExpirationTime(string $expirationTime = 'now +1h'): self
    {
        $this->expirationTime = $expirationTime;

        return $this;
    }

    public function setCache(CacheInterface $cache, ?string $cacheKey = null): self
    {
        $this->cache = $cache;
        if (null !== $cacheKey) {
            $this->cacheKey = $cacheKey;
        }

        return $this;
    }

    public function process(RequestInterface $request, Notification $notification, Subscription $subscription): RequestInterface
    {
        $this->logger->debug('Processing with VAPID header');
        $endpoint = $subscription->getEndpoint();
        $expiresAt = new DateTimeImmutable($this->expirationTime);
        $parsedEndpoint = parse_url($endpoint);
        $origin = $parsedEndpoint['scheme'].'://'.$parsedEndpoint['host'];
        $claims = [
            'aud' => $origin,
            'sub' => $this->subject,
            'exp' => $expiresAt->getTimestamp(),
        ];
        if (null !== $this->cache) {
            $this->logger->debug('Caching feature is available');
            $header = $this->getHeaderFromCache($endpoint, $claims);
            $this->logger->debug('Header from cache', ['header' => $header]);
            Assertion::isInstanceOf($header, Header::class, 'Unable to generate the VAPID header');
        } else {
            $this->logger->debug('Caching feature is not available');
            $header = $this->jwsProvider->computeHeader($claims);
            $this->logger->debug('Generated header', ['header' => $header]);
        }

        return $request
            ->withHeader('Authorization', sprintf('vapid t=%s, k=%s', $header->getToken(), $header->getKey()))
            ->withAddedHeader('Crypto-Key', sprintf('p256ecdsa=%s', $header->getKey()))
        ;
    }

    private function getHeaderFromCache(string $endpoint, array $claims): ?Header
    {
        $jwsProvider = $this->jwsProvider;
        $expirationTime = $this->expirationTime;

        $computedCacheKey = hash('sha512', sprintf('%s-%s', $this->cacheKey, $endpoint));

        return $this->cache->get($computedCacheKey, static function (ItemInterface $item) use ($claims, $jwsProvider,$expirationTime): Header {
            $expiresAt = new DateTimeImmutable($expirationTime);
            $item->expiresAt($expiresAt);

            return $jwsProvider->computeHeader($claims);
        });
    }
}
