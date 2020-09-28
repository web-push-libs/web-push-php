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

namespace Minishlink\WebPush;

use Assert\Assertion;

class Notification
{
    public const URGENCY_VERY_LOW = 'very-low';
    public const URGENCY_LOW = 'low';
    public const URGENCY_NORMAL = 'normal';
    public const URGENCY_HIGH = 'high';

    private ?string $payload = null;
    private int $ttl = 0;
    private string $urgency = self::URGENCY_NORMAL;
    private ?string $topic = null;

    public static function create(): self
    {
        return new self();
    }

    public function veryLow(): self
    {
        $this->urgency = self::URGENCY_VERY_LOW;

        return $this;
    }

    public function low(): self
    {
        $this->urgency = self::URGENCY_LOW;

        return $this;
    }

    public function normal(): self
    {
        $this->urgency = self::URGENCY_NORMAL;

        return $this;
    }

    public function high(): self
    {
        $this->urgency = self::URGENCY_HIGH;

        return $this;
    }

    public function withUrgency(string $urgency): self
    {
        Assertion::inArray($urgency, [
            self::URGENCY_VERY_LOW,
            self::URGENCY_LOW,
            self::URGENCY_NORMAL,
            self::URGENCY_HIGH,
        ], 'Invalid urgency parameter');
        $this->urgency = $urgency;

        return $this;
    }

    public function getUrgency(): string
    {
        return $this->urgency;
    }

    public function withPayload(string $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    public function getPayload(): ?string
    {
        return $this->payload;
    }

    public function withTopic(string $topic): self
    {
        Assertion::notBlank($topic, 'Invalid topic');
        $this->topic = $topic;

        return $this;
    }

    public function getTopic(): ?string
    {
        return $this->topic;
    }

    public function withTTL(int $ttl): self
    {
        Assertion::greaterOrEqualThan($ttl, 0, 'Invalid TTL');
        $this->ttl = $ttl;

        return $this;
    }

    public function getTTL(): int
    {
        return $this->ttl;
    }
}
