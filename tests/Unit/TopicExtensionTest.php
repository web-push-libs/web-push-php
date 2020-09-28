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
use Minishlink\WebPush\TopicExtension;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class TopicExtensionTest extends TestCase
{
    /**
     * @test
     * @dataProvider dataTopicIsSetInHeader
     */
    public function topicIsSetInHeader(?string $topic): void
    {
        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(static::once())
            ->method('debug')
            ->with('Processing with the Topic extension', ['Topic' => $topic])
        ;

        $request = self::createMock(RequestInterface::class);
        if (null === $topic) {
            $request
                ->expects(static::never())
                ->method(static::anything())
                ->willReturnSelf()
            ;
        } else {
            $request
                ->expects(static::once())
                ->method('withHeader')
                ->with('Topic', $topic)
                ->willReturnSelf()
            ;
        }

        $notification = self::createMock(Notification::class);
        $notification
            ->expects(static::once())
            ->method('getTopic')
            ->willReturn($topic)
        ;
        $subscription = self::createMock(Subscription::class);

        $extension = new TopicExtension();
        $extension
            ->setLogger($logger)
            ->process($request, $notification, $subscription)
        ;
    }

    public function dataTopicIsSetInHeader(): array
    {
        return [
            [null],
            ['topic1'],
            ['foo-bar'],
        ];
    }
}
