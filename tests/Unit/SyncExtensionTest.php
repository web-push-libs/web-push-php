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

namespace Minishlink\Tests\Unit;

use Minishlink\WebPush\Notification;
use Minishlink\WebPush\PreferAsyncExtension;
use Minishlink\WebPush\Subscription;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class SyncExtensionTest extends TestCase
{
    /**
     * @test
     */
    public function asyncIsSetInHeader(): void
    {
        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(static::once())
            ->method('debug')
            ->with('Sending asynchronous notification')
    ;

        $request = self::createMock(RequestInterface::class);
        $request
            ->expects(static::once())
            ->method('withHeader')
            ->with('Prefer', 'respond-async')
            ->willReturnSelf()
        ;

        $notification = self::createMock(Notification::class);
        $notification
            ->expects(static::once())
            ->method('isAsync')
            ->willReturn(true)
        ;
        $subscription = self::createMock(Subscription::class);

        $extension = new PreferAsyncExtension();
        $extension
            ->setLogger($logger)
            ->process($request, $notification, $subscription)
        ;
    }

    /**
     * @test
     */
    public function asyncIsNotSetInHeader(): void
    {
        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(static::once())
            ->method('debug')
            ->with('Sending synchronous notification')
        ;

        $request = self::createMock(RequestInterface::class);
        $request
            ->expects(static::never())
            ->method('withHeader')
            ->willReturnSelf()
        ;

        $notification = self::createMock(Notification::class);
        $notification
            ->expects(static::once())
            ->method('isAsync')
            ->willReturn(false)
        ;
        $subscription = self::createMock(Subscription::class);

        $extension = new PreferAsyncExtension();
        $extension
            ->setLogger($logger)
            ->process($request, $notification, $subscription)
        ;
    }
}
