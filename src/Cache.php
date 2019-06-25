<?php

namespace Minishlink\WebPush;

class Cache
{
    /**
     * @var array
     */
    private $array = [];
    /**
     * @var bool
     */
    private $enabled = false;

    public function set(string $key, $value): void
    {
        $this->array[$key] = is_callable($value) ? $value() : $value;
    }

    public function get(string $key)
    {
        return $this->array[$key] ?? null;
    }

    public function remember(string $key, $value)
    {
        if ($this->has($key) === false) {
            $this->set($key, $value);
        }

        return $this->get($key);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->array);
    }

    public function clear(): void
    {
        $this->array = [];
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
