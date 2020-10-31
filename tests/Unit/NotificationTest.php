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

use Assert\InvalidArgumentException;
use Minishlink\WebPush\Notification;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class NotificationTest extends TestCase
{
    /**
     * @test
     */
    public function createNotificationFluent(): void
    {
        $subscription = Notification::create()
            ->veryLow()
            ->low()
            ->normal()
            ->high()
            ->withUrgency(Notification::URGENCY_HIGH)
            ->withTTL(0)
            ->withPayload('payload')
            ->withTopic('topic')
            ->sync()
        ;

        static::assertEquals(Notification::URGENCY_HIGH, $subscription->getUrgency());
        static::assertEquals(0, $subscription->getTTL());
        static::assertEquals('payload', $subscription->getPayload());
        static::assertEquals('topic', $subscription->getTopic());
        static::assertFalse($subscription->isAsync());
    }

    /**
     * @test
     */
    public function createNotificationWithTTL(): void
    {
        $subscription = Notification::create()
            ->withTTL(3600)
        ;

        static::assertEquals(Notification::URGENCY_NORMAL, $subscription->getUrgency());
        static::assertEquals(3600, $subscription->getTTL());
        static::assertEquals(null, $subscription->getPayload());
        static::assertEquals(null, $subscription->getTopic());
    }

    /**
     * @test
     */
    public function createAsyncNotification(): void
    {
        $subscription = Notification::create()
            ->async()
        ;

        static::assertTrue($subscription->isAsync());
    }

    /**
     * @test
     */
    public function defaultNotificationIsSync(): void
    {
        $subscription = Notification::create();

        static::assertFalse($subscription->isAsync());
    }

    /**
     * @test
     * @dataProvider dataUrgencies
     */
    public function urgencies(string $urgency): void
    {
        $subscription = Notification::create()
            ->withUrgency($urgency)
        ;

        static::assertEquals($urgency, $subscription->getUrgency());
    }

    /**
     * @test
     */
    public function invalidUrgency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid urgency parameter');

        Notification::create()
            ->withUrgency('urgency')
        ;
    }

    /**
     * @test
     */
    public function invalidTopic(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid topic');

        Notification::create()
            ->withTopic('')
        ;
    }

    /**
     * @test
     */
    public function invalidTTL(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid TTL');

        Notification::create()
            ->withTTL(-1)
        ;
    }

    /**
     * @test
     */
    public function createNotificationWithMetadata(): void
    {
        $notification = Notification::create()
            ->add('foo', 'BAR')
        ;

        static::assertFalse($notification->has('nope'));
        static::assertTrue($notification->has('foo'));
        static::assertEquals('BAR', $notification->get('foo'));
        static::assertEquals(['foo' => 'BAR'], $notification->getMetadata());
    }

    /**
     * @test
     */
    public function missingMetadata(): void
    {
        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage('Missing metadata');
        $notification = Notification::create();

        $notification->get('fff');
    }

    public function dataUrgencies(): array
    {
        return [
            [Notification::URGENCY_VERY_LOW],
            [Notification::URGENCY_LOW],
            [Notification::URGENCY_NORMAL],
            [Notification::URGENCY_HIGH],
        ];
    }
}
