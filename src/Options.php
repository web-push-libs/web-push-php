<?php

declare(strict_types = 1);

namespace WebPush;

class Options
{
    /**
     * @var array
     */
    private $allowed = ['ttl', 'urgency', 'topic'];
    /**
     * @var array
     */
    private $options = ['ttl' => 2419200];

    public function __construct(array $options = [])
    {
        $this->setOptions($options);
    }

    public function getTtl(): int
    {
        return (int) ($this->options['ttl'] ?? 2419200);
    }

    public function getUrgency(): string
    {
        return (string) ($this->options['urgency'] ?? '');
    }

    public function getTopic(): string
    {
        return (string) ($this->options['topic'] ?? '');
    }

    /**
     * @param Options|array $options
     *
     * @return Options
     */
    public function with($options): Options
    {
        return new static(array_replace(static::wrap($options)->toArray(), $this->toArray()));
    }

    public static function create(array $options)
    {
        return new static($options);
    }

    /**
     * @param mixed $options
     *
     * @return Options
     */
    public static function wrap($options): Options
    {
        return $options instanceof self ? $options : new static((array) $options);
    }

    private function setOptions(array $options): void
    {
        $this->options = array_merge($this->options, $this->filterOptions($options));
    }

    private function filterOptions(array $options): array
    {
        return array_filter(array_intersect_key($options, array_flip($this->allowed)));
    }

    public function toArray(): array
    {
        return array_filter($this->options);
    }
}
