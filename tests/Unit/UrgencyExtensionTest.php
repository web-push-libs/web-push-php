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
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\UrgencyExtension;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class UrgencyExtensionTest extends TestCase
{
    /**
     * @test
     * @dataProvider dataUrgencyIsSetInHeader
     */
    public function urgencyIsSetInHeader(string $urgency): void
    {
        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(static::once())
            ->method('debug')
            ->with('Processing with the Urgency extension', ['Urgency' => $urgency])
    ;

        $request = self::createMock(RequestInterface::class);
        $request
            ->expects(static::once())
            ->method('withHeader')
            ->with('Urgency', $urgency)
            ->willReturnSelf()
        ;

        $notification = self::createMock(Notification::class);
        $notification
            ->expects(static::once())
            ->method('getUrgency')
            ->willReturn($urgency)
        ;
        $subscription = self::createMock(Subscription::class);

        $extension = new UrgencyExtension();
        $extension
            ->setLogger($logger)
            ->process($request, $notification, $subscription)
        ;
    }

    public function dataUrgencyIsSetInHeader(): array
    {
        return [
            [Notification::URGENCY_VERY_LOW],
            [Notification::URGENCY_LOW],
            [Notification::URGENCY_NORMAL],
            [Notification::URGENCY_HIGH],
        ];
    }
}
