<?php

namespace Minishlink\WebPush;

use ErrorException;

class Payload
{
    /**
     * @var string
     */
    private $payload;
    /**
     * @var string
     */
    private $salt;
    /**
     * @var string
     */
    private $localPublicKey;

    public function __construct(string $payload, string $salt, string $key)
    {
        $this->payload = $payload;
        $this->salt = $salt;
        $this->localPublicKey = $key;
    }

    /**
     * @param Contracts\SubscriptionInterface $subscription
     * @param string $payload
     * @param int $padding
     *
     * @return Payload
     * @throws ErrorException
     */
    public static function create(Contracts\SubscriptionInterface $subscription, string $payload, int $padding): Payload
    {
        return (new PayloadBuilder())->build($subscription, $payload, $padding);
    }

    public function getSalt(): string
    {
        return $this->salt;
    }

    public function getLocalPublicKey(): string
    {
        return $this->localPublicKey;
    }

    public function toString(): string
    {
        return $this->payload;
    }

    public function isEmpty(): bool
    {
        return empty($this->payload);
    }

    public function isNotEmpty(): bool
    {
        return $this->isEmpty() === false;
    }

    public function getLength(): int
    {
        return mb_strlen($this->payload, '8bit');
    }

    public function __toString()
    {
        return $this->toString();
    }
}
