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

namespace Minishlink\WebPush;

use function array_key_exists;
use Assert\Assertion;
use JsonSerializable;
use function Safe\sprintf;

class Keys implements JsonSerializable
{
    /**
     * @var array<string, string>
     */
    private array $keys = [];

    public function set(string $name, string $value): self
    {
        $this->keys[$name] = $value;

        return $this;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->keys);
    }

    public function get(string $name): string
    {
        Assertion::true($this->has($name), sprintf('Undefined key name "%s"', $name));

        return $this->keys[$name];
    }

    /**
     * @return string[]
     */
    public function list(): array
    {
        return array_keys($this->keys);
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->keys;
    }

    public static function createFromAssociativeArray(array $keys): self
    {
        $object = new self();
        foreach ($keys as $k => $v) {
            Assertion::string($k, 'Invalid key name');
            Assertion::string($v, 'Invalid key value');
            $object->set($k, $v);
        }

        return $object;
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return $this->keys;
    }
}
