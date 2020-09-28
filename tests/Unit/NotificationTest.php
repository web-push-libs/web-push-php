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
        ;

        static::assertEquals(Notification::URGENCY_HIGH, $subscription->getUrgency());
        static::assertEquals(0, $subscription->getTTL());
        static::assertEquals('payload', $subscription->getPayload());
        static::assertEquals('topic', $subscription->getTopic());
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
