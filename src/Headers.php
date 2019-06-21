<?php

namespace Minishlink\WebPush;

use ArrayIterator;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

class Headers implements IteratorAggregate
{
    /**
     * @var array
     */
    private $headers;

    public function __construct(array $headers = [])
    {
        $this->headers = $headers;
    }

    /**
     * @param Headers|array $headers
     *
     * @return Headers
     */
    public static function wrap($headers): self
    {
        return $headers instanceof self ? $headers : new static((array) $headers);
    }

    /**
     * @param Headers|array $headers
     *
     * @return Headers
     */
    public function with($headers): Headers
    {
        return new self(array_replace(self::wrap($headers)->toArray(), $this->headers));
    }

    /**
     * @param string $name
     * @param string|array|int $value
     *
     * @return Headers
     * @throws InvalidArgumentException
     */
    public function set(string $name, $value): self
    {
        if (is_scalar($value) === false && is_array($value) === false) {
            throw new InvalidArgumentException(sprintf('Invalid value specified for %s header', $name));
        }

        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * @param string $name
     *
     * @return mixed Returns null if a header does not exist.
     */
    public function get(string $name)
    {
        return $this->headers[$name] ?? null;
    }

    public function add(array $headers): self
    {
        foreach ($headers as $header => $value) {
            $this->set($header, $value);
        }

        return $this;
    }

    public function append(string $name, string $value, string $delimiter = null): self
    {
        if (($existing = $this->get($name)) === null) {
            return $this->set($name, $value);
        }

        return $this->set($name, sprintf('%s%s%s', $existing, $delimiter, $value));
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->headers);
    }

    public function remove(string $name)
    {
        unset($this->headers[$name]);

        return $this;
    }

    public function toArray(): array
    {
        return $this->headers;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->toArray());
    }
}
