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

use function array_key_exists;
use Minishlink\WebPush\ExtensionManager;
use Minishlink\WebPush\Notification;
use Minishlink\WebPush\StatusReportFailure;
use Minishlink\WebPush\StatusReportSuccess;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
class WebPushTest extends TestCase
{
    /**
     * @test
     */
    public function aNotificationCanBeSent(): void
    {
        $response = self::createMock(ResponseInterface::class);
        $response
            ->expects(static::once())
            ->method('getStatusCode')
            ->willReturn(200)
        ;
        $response
            ->expects(static::once())
            ->method('getHeaderLine')
            ->with('location')
            ->willReturn('https://BAR-FOO.IO/0123456789')
        ;

        $request = self::createMock(RequestInterface::class);
        $request
            ->expects(static::never())
            ->method(static::anything())
        ;

        $subscription = self::createMock(Subscription::class);
        $subscription
            ->expects(static::once())
            ->method('getEndpoint')
            ->willReturn('https://foo.bar')
        ;

        $notification = self::createMock(Notification::class);
        $notification
            ->expects(static::never())
            ->method(static::anything())
        ;

        $client = self::createMock(ClientInterface::class);
        $client
            ->expects(static::once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response)
        ;

        $requestFactory = self::createMock(RequestFactoryInterface::class);
        $requestFactory
            ->expects(static::once())
            ->method('createRequest')
            ->with('POST', 'https://foo.bar')
            ->willReturn($request)
        ;

        $extensionManager = self::createMock(ExtensionManager::class);
        $extensionManager
            ->expects(static::once())
            ->method('process')
            ->with($request, $notification, $subscription)
            ->willReturnArgument(0)
        ;

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(static::exactly(3))
            ->method('debug')
            ->withConsecutive(
                ['Sending notification', static::callback(static function (array $data) use ($notification): bool {
                    if (!array_key_exists('notification', $data)) {
                        return false;
                    }

                    return $data['notification'] === $notification;
                })],
                ['Request ready', static::callback(static function (array $data) use ($request): bool {
                    if (!array_key_exists('request', $data)) {
                        return false;
                    }

                    return $data['request'] === $request;
                })],
                ['Response received', static::callback(static function (array $data) use ($response): bool {
                    if (!array_key_exists('response', $data)) {
                        return false;
                    }

                    return $data['response'] === $response;
                })],
            )
        ;

        $eventDispatcher = self::createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(StatusReportSuccess::class))
        ;

        $webPush = new WebPush($client, $requestFactory, $extensionManager);
        $report = $webPush
            ->setLogger($logger)
            ->setEventDispatcher($eventDispatcher)
            ->send($notification, $subscription)
        ;

        static::assertInstanceOf(StatusReportSuccess::class, $report);
        static::assertTrue($report->isSuccess());
        static::assertSame($notification, $report->getNotification());
        static::assertSame($subscription, $report->getSubscription());
    }

    /**
     * @test
     */
    public function aNotificationCannotBeSent(): void
    {
        $response = self::createMock(ResponseInterface::class);
        $response
            ->expects(static::once())
            ->method('getStatusCode')
            ->willReturn(409)
        ;
        $response
            ->expects(static::once())
            ->method('getReasonPhrase')
            ->willReturn('Too Many Requests')
        ;

        $request = self::createMock(RequestInterface::class);
        $request
            ->expects(static::never())
            ->method(static::anything())
        ;

        $subscription = self::createMock(Subscription::class);
        $subscription
            ->expects(static::once())
            ->method('getEndpoint')
            ->willReturn('https://foo.bar')
        ;

        $notification = self::createMock(Notification::class);
        $notification
            ->expects(static::never())
            ->method(static::anything())
        ;

        $client = self::createMock(ClientInterface::class);
        $client
            ->expects(static::once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response)
        ;

        $requestFactory = self::createMock(RequestFactoryInterface::class);
        $requestFactory
            ->expects(static::once())
            ->method('createRequest')
            ->with('POST', 'https://foo.bar')
            ->willReturn($request)
        ;

        $extensionManager = self::createMock(ExtensionManager::class);
        $extensionManager
            ->expects(static::once())
            ->method('process')
            ->with($request, $notification, $subscription)
            ->willReturnArgument(0)
        ;

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(static::exactly(3))
            ->method('debug')
            ->withConsecutive(
                ['Sending notification', static::callback(static function (array $data) use ($notification): bool {
                    if (!array_key_exists('notification', $data)) {
                        return false;
                    }

                    return $data['notification'] === $notification;
                })],
                ['Request ready', static::callback(static function (array $data) use ($request): bool {
                    if (!array_key_exists('request', $data)) {
                        return false;
                    }

                    return $data['request'] === $request;
                })],
                ['Response received', static::callback(static function (array $data) use ($response): bool {
                    if (!array_key_exists('response', $data)) {
                        return false;
                    }

                    return $data['response'] === $response;
                })],
            )
        ;

        $eventDispatcher = self::createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(StatusReportFailure::class))
        ;

        $webPush = new WebPush($client, $requestFactory, $extensionManager);
        $report = $webPush
            ->setLogger($logger)
            ->setEventDispatcher($eventDispatcher)
            ->send($notification, $subscription)
        ;

        static::assertInstanceOf(StatusReportFailure::class, $report);
        static::assertFalse($report->isSuccess());
        static::assertSame('Too Many Requests', $report->getReason());
        static::assertSame(409, $report->getCode());
        static::assertSame($notification, $report->getNotification());
        static::assertSame($subscription, $report->getSubscription());
    }
}
