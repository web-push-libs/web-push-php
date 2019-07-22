<?php

namespace WebPush;

use ArrayIterator;
use Exception;
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
     * @param mixed $value
     *
     * @return Headers
     * @throws InvalidArgumentException
     */
    public function set(string $name, $value): self
    {
        $this->validateValue($value);

        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * @param string $name
     *
     * @return mixed Returns null if a header does not exist.
     * @throws Exception
     */
    public function get(string $name)
    {
        if ($this->has($name) === false) {
            throw new Exception('No value has been set for header with name "' . $name . '"');
        }

        return $this->headers[$name];
    }

    public function add(array $headers): self
    {
        foreach ($headers as $header => $value) {
            $this->set($header, $value);
        }

        return $this;
    }

    /**
     * @param string $name
     * @param string $value
     * @param string|null $delimiter
     *
     * @return Headers
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function append(string $name, string $value, string $delimiter = null): self
    {
        if ($this->has($name) === false) {
            return $this->set($name, $value);
        }

        return $this->set($name, sprintf('%s%s%s', $this->get($name), $delimiter, $value));
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

    /**
     * @param mixed $value
     *
     * @throws InvalidArgumentException
     */
    private function validateValue($value): void
    {
        if ($value !== null && is_scalar($value) === false && is_array($value) === false) {
            throw new InvalidArgumentException('Invalid header value specified ' . json_encode($value));
        }
    }
}
