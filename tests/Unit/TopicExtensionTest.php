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
