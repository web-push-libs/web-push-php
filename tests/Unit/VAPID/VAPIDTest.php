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

namespace Minishlink\Tests\Unit\VAPID;

use function array_key_exists;
use InvalidArgumentException;
use Minishlink\WebPush\Notification;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID\Header;
use Minishlink\WebPush\VAPID\JWSProvider;
use Minishlink\WebPush\VAPID\VAPID;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
final class VAPIDTest extends TestCase
{
    /**
     * @test
     */
    public function vapidHeaderCanBeAdded(): void
    {
        $jwsProvider = self::createMock(JWSProvider::class);
        $jwsProvider
            ->expects(static::once())
            ->method('computeHeader')
            ->with()
            ->willReturnCallback(static function (array $parameters): Header {
                static::assertArrayHasKey('aud', $parameters);
                static::assertArrayHasKey('sub', $parameters);
                static::assertArrayHasKey('exp', $parameters);
                static::assertEquals('https://foo.fr', $parameters['aud']);
                static::assertEquals('subject', $parameters['sub']);
                static::assertIsInt($parameters['exp']);

                return new Header('TOKEN', 'KEY');
            })
        ;

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(static::exactly(3))
            ->method('debug')
            ->withConsecutive(
                ['Processing with VAPID header'],
                ['Caching feature is not available'],
                ['Generated header', static::callback(static function (array $data): bool {
                    if (!array_key_exists('header', $data)) {
                        return false;
                    }

                    return $data['header'] instanceof Header;
                })],
            )
        ;

        $extension = new VAPID(
            'subject',
            $jwsProvider
        );

        $request = self::createMock(RequestInterface::class);
        $request
            ->expects(static::once())
            ->method('withAddedHeader')
            ->with('Authorization', 'vapid t=TOKEN, k=KEY')
            ->willReturnSelf()
        ;

        $notification = self::createMock(Notification::class);
        $subscription = self::createMock(Subscription::class);
        $subscription
            ->expects(static::once())
            ->method('getEndpoint')
            ->willReturn('https://foo.fr/test')
        ;

        $extension
            ->setLogger($logger)
            ->process($request, $notification, $subscription)
        ;
    }

    /**
     * @test
     */
    public function vapidWithCacheHeaderCanBeAdded(): void
    {
        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(static::exactly(3))
            ->method('debug')
            ->withConsecutive(
                ['Processing with VAPID header'],
                ['Caching feature is available'],
                ['Header from cache', static::callback(static function (array $data): bool {
                    if (!array_key_exists('header', $data)) {
                        return false;
                    }

                    return $data['header'] instanceof Header;
                })],
            )
        ;

        $cache = self::createMock(CacheInterface::class);
        $cache
            ->expects(static::once())
            ->method('get')
            ->with(hash('sha512', '__KEY__-https://foo.fr/test'))
            ->willReturn(new Header('TOKEN__CACHE', 'KEY__CACHE'))
        ;

        $jwsProvider = self::createMock(JWSProvider::class);
        $jwsProvider
            ->expects(static::never())
            ->method(static::anything())
        ;

        $request = self::createMock(RequestInterface::class);
        $request
            ->expects(static::once())
            ->method('withAddedHeader')
            ->withConsecutive(
                ['Authorization', 'vapid t=TOKEN__CACHE, k=KEY__CACHE'],
                //['Crypto-Key', static::isType('string')],
            )
            ->willReturnSelf()
        ;

        $notification = self::createMock(Notification::class);
        $subscription = self::createMock(Subscription::class);
        $subscription
            ->expects(static::once())
            ->method('getEndpoint')
            ->willReturn('https://foo.fr/test')
        ;

        $extension = new VAPID(
            'subject',
            $jwsProvider
        );
        $extension
            ->setLogger($logger)
            ->setCache($cache, '__KEY__')
            ->setExpirationTime('now +2 hours')
            ->process($request, $notification, $subscription)
        ;
    }

    /**
     * @test
     */
    public function vapidWithMissingCacheHeaderCannotBeAdded(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Unable to generate the VAPID header');

        $cache = self::createMock(CacheInterface::class);
        $cache
            ->expects(static::once())
            ->method('get')
            ->with(hash('sha512', '__CACHE_KEY__-https://foo.fr/test'))
            ->willReturn(null)
        ;

        $jwsProvider = self::createMock(JWSProvider::class);
        $jwsProvider
            ->expects(static::never())
            ->method(static::anything())
        ;

        $extension = new VAPID(
            'subject',
            $jwsProvider
        );
        $extension
            ->setExpirationTime('now +2 hours')
            ->setCache($cache, '__CACHE_KEY__')
        ;

        $request = self::createMock(RequestInterface::class);
        $request
            ->expects(static::never())
            ->method(static::anything())
            ->willReturnSelf()
        ;

        $notification = self::createMock(Notification::class);
        $subscription = self::createMock(Subscription::class);
        $subscription
            ->expects(static::once())
            ->method('getEndpoint')
            ->willReturn('https://foo.fr/test')
        ;

        $extension->process($request, $notification, $subscription);
    }
}
