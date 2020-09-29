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
