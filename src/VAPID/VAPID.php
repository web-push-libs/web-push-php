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

use Minishlink\WebPush\Extension;
use Minishlink\WebPush\Notification;
use Minishlink\WebPush\Subscription;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Safe\DateTimeImmutable;
use function Safe\parse_url;
use function Safe\sprintf;

class VAPID implements Extension
{
    private JWSProvider $jwsProvider;
    private ?CacheItemPoolInterface $cache = null;
    private string $tokenExpirationTime = 'now +1h';
    private LoggerInterface $logger;
    private string $subject;
    private string $cacheExpirationTime = 'now +30min';

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

    public function setTokenExpirationTime(string $tokenExpirationTime = 'now +1h'): self
    {
        $this->tokenExpirationTime = $tokenExpirationTime;

        return $this;
    }

    public function setCache(CacheItemPoolInterface $cache, string $cacheExpirationTime = 'now +30min'): self
    {
        $this->cache = $cache;
        $this->cacheExpirationTime = $cacheExpirationTime;

        return $this;
    }

    public function process(RequestInterface $request, Notification $notification, Subscription $subscription): RequestInterface
    {
        $this->logger->debug('Processing with VAPID header');
        $endpoint = $subscription->getEndpoint();
        $expiresAt = new DateTimeImmutable($this->tokenExpirationTime);
        $parsedEndpoint = parse_url($endpoint);
        $origin = $parsedEndpoint['scheme'].'://'.$parsedEndpoint['host'].(isset($parsedEndpoint['port']) ? ':'.$parsedEndpoint['port'] : '');
        $claims = [
            'aud' => $origin,
            'sub' => $this->subject,
            'exp' => $expiresAt->getTimestamp(),
        ];
        if (null !== $this->cache) {
            $this->logger->debug('Caching feature is available');
            $header = $this->getHeaderFromCache($origin, $claims);
            $this->logger->debug('Header from cache', ['header' => $header]);
        } else {
            $this->logger->debug('Caching feature is not available');
            $header = $this->jwsProvider->computeHeader($claims);
            $this->logger->debug('Generated header', ['header' => $header]);
        }

        return $request
            ->withAddedHeader('Authorization', sprintf('vapid t=%s, k=%s', $header->getToken(), $header->getKey()))
        ;
    }

    private function getHeaderFromCache(string $origin, array $claims): Header
    {
        $jwsProvider = $this->jwsProvider;
        $computedCacheKey = hash('sha512', $origin);

        $item = $this->cache->getItem($computedCacheKey);
        if ($item->isHit()) {
            return $item->get();
        }

        $token = $jwsProvider->computeHeader($claims);
        $item = $item
            ->set($token)
            ->expiresAt(new DateTimeImmutable($this->cacheExpirationTime))
        ;
        $this->cache->save($item);

        return $token;
    }
}
