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
use Minishlink\WebPush\StatusReport;
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
     * @dataProvider dataReport
     */
    public function report(int $statusCode, bool $isSuccess): void
    {
        $subscription = self::createMock(Subscription::class);
        $notification = self::createMock(Notification::class);
        $request = self::createMock(RequestInterface::class);
        $response = self::createMock(ResponseInterface::class);
        $response
            ->expects(static::once())
            ->method('getHeaderLine')
            ->with('location')
            ->willReturn('https://foo.bar')
        ;
        $response
            ->expects(static::once())
            ->method('getStatusCode')
            ->willReturn($statusCode)
        ;
        $response
            ->expects(static::once())
            ->method('getHeader')
            ->with('link')
            ->willReturn(['https://link.1'])
        ;
        $report = new StatusReport(
            $subscription,
            $notification,
            $request,
            $response
        );

        static::assertSame($subscription, $report->getSubscription());
        static::assertSame($notification, $report->getNotification());
        static::assertSame($request, $report->getRequest());
        static::assertSame($response, $report->getResponse());
        static::assertEquals('https://foo.bar', $report->getLocation());
        static::assertEquals(['https://link.1'], $report->getLinks());
        static::assertEquals($isSuccess, $report->isSuccess());
    }

    /**
     * @return array[]
     */
    public function dataReport(): array
    {
        return [
            [199, false],
            [200, true],
            [201, true],
            [202, true],
            [299, true],
            [300, false],
            [301, false],
        ];
    }
}
