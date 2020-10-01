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
use DateTimeInterface;
use JsonSerializable;
use Safe\DateTimeImmutable;
use function Safe\json_decode;

class Subscription implements JsonSerializable
{
    private string $endpoint;

    private Keys $keys;

    private string $contentEncoding = 'aesgcm';

    private ?int $expirationTime = null;

    public function __construct(string $endpoint)
    {
        $this->endpoint = $endpoint;
        $this->keys = new Keys();
    }

    public static function create(string $endpoint): self
    {
        return new self($endpoint);
    }

    public function withContentEncoding(string $contentEncoding): self
    {
        $this->contentEncoding = $contentEncoding;

        return $this;
    }

    public function getKeys(): Keys
    {
        return $this->keys;
    }

    public function getExpirationTime(): ?int
    {
        return $this->expirationTime;
    }

    public function expiresAt(): ?DateTimeInterface
    {
        return null === $this->expirationTime ? null : (new DateTimeImmutable())->setTimestamp($this->expirationTime);
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getContentEncoding(): string
    {
        return $this->contentEncoding;
    }

    public static function createFromString(string $input): self
    {
        $data = json_decode($input, true);
        Assertion::isArray($data, 'Invalid input');

        return self::createFromAssociativeArray($data);
    }

    public function jsonSerialize(): array
    {
        return [
            'endpoint' => $this->endpoint,
            'contentEncoding' => $this->contentEncoding,
            'keys' => $this->keys,
        ];
    }

    private static function createFromAssociativeArray(array $input): self
    {
        Assertion::keyExists($input, 'endpoint', 'Invalid input');
        Assertion::string($input['endpoint'], 'Invalid input');

        $object = new self($input['endpoint']);
        if (array_key_exists('contentEncoding', $input)) {
            Assertion::nullOrString($input['contentEncoding'], 'Invalid input');
            $object->contentEncoding = $input['contentEncoding'];
        }
        if (array_key_exists('expirationTime', $input)) {
            Assertion::nullOrInteger($input['expirationTime'], 'Invalid input');
            $object->expirationTime = $input['expirationTime'];
        }
        if (array_key_exists('keys', $input)) {
            Assertion::isArray($input['keys'], 'Invalid input');
            $object->keys = Keys::createFromAssociativeArray($input['keys']);
        }

        return $object;
    }
}
