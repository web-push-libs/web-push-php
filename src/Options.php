<?php

declare(strict_types = 1);

namespace Minishlink\WebPush;

class Options
{
    /**
     * @var int
     */
    private $ttl;
    /**
     * @var string|null
     */
    private $urgency;
    /**
     * @var string|null
     */
    private $topic;
    /**
     * @var int
     */
    private $batchSize;

    public function __construct(
        ?int $ttl = null,
        ?string $urgency = null,
        ?string $topic = null,
        ?int $batch_size = null
    ) {
        $this->ttl = $ttl ?? 2419200;
        $this->urgency = $urgency;
        $this->topic = $topic;
        $this->batchSize = $batch_size ?? 1000;
    }

    public static function fromArray(array $array)
    {
        return new static(
            $array['TTL'] ?? null,
            $array['urgency'] ?? null,
            $array['topic'] ?? null,
            $array['batch_size'] ?? null
        );
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }

    public function getUrgency(): ?string
    {
        return $this->urgency;
    }

    public function getTopic(): ?string
    {
        return $this->topic;
    }

    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    /**
     * @param Options|array $options
     *
     * @return Options
     */
    public function with($options): Options
    {
        return static::fromArray(array_replace(static::normalize($options)->toArray(), $this->toArray()));
    }

    /**
     * @param Options|array $options
     *
     * @return Options
     */
    public static function normalize($options): Options
    {
        return $options instanceof self ? $options : static::fromArray($options);
    }

    public function toArray(): array
    {
        return array_filter([
            'TTL' => $this->ttl,
            'urgency' => $this->urgency,
            'topic' => $this->topic,
            'batch_size' => $this->batchSize
        ]);
    }
}
