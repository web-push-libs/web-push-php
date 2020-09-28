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

namespace Minishlink\Tests\Unit;

use Minishlink\WebPush\Notification;
use Minishlink\WebPush\StatusReportFailure;
use Minishlink\WebPush\StatusReportSuccess;
use Minishlink\WebPush\Subscription;
use PHPUnit\Framework\TestCase;

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
        $report = new StatusReportFailure(
            $subscription,
            $notification,
            409,
            'reason'
        );

        static::assertEquals($subscription, $report->getSubscription());
        static::assertEquals($notification, $report->getNotification());
        static::assertEquals(false, $report->isSuccess());
        static::assertEquals(409, $report->getCode());
        static::assertEquals('reason', $report->getReason());
    }
}
