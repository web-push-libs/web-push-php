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
use Minishlink\WebPush\StatusReportFailure;
use Minishlink\WebPush\StatusReportSuccess;
use Minishlink\WebPush\Subscription;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 */
final class StatusReportTest extends TestCase
{
    /**
     * @test
     */
    public function successReport(): void
    {
        $subscription = self::createMock(Subscription::class);
        $notification = self::createMock(Notification::class);
        $report = new StatusReportSuccess(
            $subscription,
            $notification,
            'https://foo.bar'
        );

        static::assertEquals($subscription, $report->getSubscription());
        static::assertEquals($notification, $report->getNotification());
        static::assertEquals('https://foo.bar', $report->getLocation());
        static::assertEquals(true, $report->isSuccess());
    }

    /**
     * @test
     */
    public function failureReport(): void
    {
        $subscription = self::createMock(Subscription::class);
        $notification = self::createMock(Notification::class);
        $request = self::createMock(RequestInterface::class);
        $response = self::createMock(ResponseInterface::class);
        $report = new StatusReportFailure(
            $subscription,
            $notification,
            409,
            'reason',
            $request,
            $response
        );

        static::assertEquals($subscription, $report->getSubscription());
        static::assertEquals($notification, $report->getNotification());
        static::assertEquals(false, $report->isSuccess());
        static::assertEquals(409, $report->getCode());
        static::assertEquals('reason', $report->getReason());
        static::assertSame($request, $report->getRequest());
        static::assertSame($response, $report->getResponse());
    }
}
