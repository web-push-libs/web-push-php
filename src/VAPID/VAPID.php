<?php

declare(strict_types=1);

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2020 Spomky-Labs
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
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
use function Safe\sprintf;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class VAPID implements Extension
{
    private const DEFAULT_CACHE_KEY = 'WEB_PUSH_DEFAULT_CACHE_KEY';

    private JWSProvider $jwsProvider;
    private ?CacheInterface $cache = null;
    private ?string $cacheKey = self::DEFAULT_CACHE_KEY;
    private ?string $expirationTime = null;
    private LoggerInterface $logger;

    public function __construct(JWSProvider $jwsProvider)
    {
        $this->jwsProvider = $jwsProvider;
        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function setCache(CacheInterface $cache, string $expirationTime = 'now +1 hour', ?string $cacheKey = null): self
    {
        $this->cache = $cache;
        $this->expirationTime = $expirationTime;
        if (null !== $cacheKey) {
            $this->cacheKey = $cacheKey;
        }

        return $this;
    }

    public function process(RequestInterface $request, Notification $notification, Subscription $subscription): RequestInterface
    {
        $this->logger->debug('Processing with VAPID header');
        if (null !== $this->cache) {
            $this->logger->debug('Caching feature is available');
            $header = $this->getHeaderFromCache();
            $this->logger->debug('Header from cache', ['header' => $header]);
            Assertion::isInstanceOf($header, Header::class, 'Unable to generate the VAPID header');
        } else {
            $this->logger->debug('Caching feature is not available');
            $expiresAt = new DateTimeImmutable($this->expirationTime);
            $header = $this->jwsProvider->computeHeader($expiresAt);
            $this->logger->debug('Generated header', ['header' => $header]);
        }

        return  $request->withHeader(
            'Authorization',
            sprintf('t=%s k=%s', $header->getToken(), $header->getKey())
        );
    }

    private function getHeaderFromCache(): ?Header
    {
        $jwsProvider = $this->jwsProvider;
        $expirationTime = $this->expirationTime;

        return $this->cache->get($this->cacheKey, static function (ItemInterface $item) use ($jwsProvider,$expirationTime): Header {
            $expiresAt = new DateTimeImmutable($expirationTime);
            $item->expiresAt($expiresAt);

            return $jwsProvider->computeHeader($expiresAt);
        });
    }
}
