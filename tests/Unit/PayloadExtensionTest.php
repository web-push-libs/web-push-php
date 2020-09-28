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

use InvalidArgumentException;
use Minishlink\WebPush\Notification;
use Minishlink\WebPush\Payload\ContentEncoding;
use Minishlink\WebPush\Payload\PayloadExtension;
use Minishlink\WebPush\Subscription;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class PayloadExtensionTest extends TestCase
{
    /**
     * @test
     */
    public function canProcessWithoutPayload(): void
    {
        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(static::exactly(2))
            ->method('debug')
            ->withConsecutive(
                ['Processing with payload'],
                ['No payload'],
            )
        ;

        $request = self::createMock(RequestInterface::class);
        $request
            ->expects(static::once())
            ->method('withHeader')
            ->with('Content-Length', '0')
            ->willReturnSelf()
        ;

        $notification = self::createMock(Notification::class);
        $notification
            ->expects(static::once())
            ->method('getPayload')
            ->willReturn(null)
        ;
        $subscription = self::createMock(Subscription::class);

        $extension = new PayloadExtension();
        $extension
            ->setLogger($logger)
            ->process($request, $notification, $subscription)
        ;
    }

    /**
     * @test
     */
    public function canProcessWithPayload(): void
    {
        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(static::exactly(2))
            ->method('debug')
            ->withConsecutive(
                ['Processing with payload'],
                ['Encoder found: aesgcm. Processing with the encoder.'],
            )
        ;

        $request = self::createMock(RequestInterface::class);
        $request
            ->expects(static::exactly(2))
            ->method('withHeader')
            ->withConsecutive(
                ['Content-Type', 'application/octet-stream'],
                ['Content-Encoding', Subscription::CONTENT_ENCODING_AESGCM],
            )
            ->willReturnSelf()
        ;

        $notification = self::createMock(Notification::class);
        $notification
            ->expects(static::once())
            ->method('getPayload')
            ->willReturn('Payload')
        ;
        $subscription = self::createMock(Subscription::class);
        $subscription
            ->expects(static::once())
            ->method('getContentEncoding')
            ->willReturn(Subscription::CONTENT_ENCODING_AESGCM)
        ;

        $contentEncoding = self::createMock(ContentEncoding::class);
        $contentEncoding
            ->expects(static::once())
            ->method('name')
            ->willReturn(Subscription::CONTENT_ENCODING_AESGCM)
        ;
        $contentEncoding
            ->expects(static::once())
            ->method('encode')
            ->with(
                'Payload',
                static::isInstanceOf(RequestInterface::class),
                static::isInstanceOf(Subscription::class)
            )
            ->willReturnArgument(1)
        ;

        $extension = new PayloadExtension();
        $extension
            ->setLogger($logger)
            ->addContentEncoding($contentEncoding)
            ->process($request, $notification, $subscription)
        ;
    }

    /**
     * @test
     */
    public function unsupportedContentEncoding(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The content encoding "aesgcm" is not supported');

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(static::once())
            ->method('debug')
            ->withConsecutive(
                ['Processing with payload'],
            )
        ;

        $request = self::createMock(RequestInterface::class);
        $request
            ->expects(static::never())
            ->method(static::anything())
        ;

        $notification = self::createMock(Notification::class);
        $notification
            ->expects(static::once())
            ->method('getPayload')
            ->willReturn('Payload')
        ;
        $subscription = self::createMock(Subscription::class);
        $subscription
            ->expects(static::once())
            ->method('getContentEncoding')
            ->willReturn(Subscription::CONTENT_ENCODING_AESGCM)
        ;

        $contentEncoding = self::createMock(ContentEncoding::class);
        $contentEncoding
            ->expects(static::never())
            ->method(static::anything())
        ;

        $extension = new PayloadExtension();
        $extension
            ->setLogger($logger)
            ->process($request, $notification, $subscription)
        ;
    }
}
