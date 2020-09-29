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
use function Safe\json_decode;

class Subscription implements JsonSerializable
{
    public const CONTENT_ENCODING_AESGCM = 'aesgcm';
    public const CONTENT_ENCODING_AES128GCM = 'aes128gcm';

    private string $endpoint;

    private Keys $keys;

    private string $contentEncoding = self::CONTENT_ENCODING_AESGCM;

    public function __construct(string $endpoint)
    {
        $this->endpoint = $endpoint;
        $this->keys = new Keys();
    }

    public static function create(string $endpoint): self
    {
        return new self($endpoint);
    }

    public function withAESGCMContentEncoding(): self
    {
        $this->contentEncoding = self::CONTENT_ENCODING_AESGCM;

        return $this;
    }

    public function withAES128GCMContentEncoding(): self
    {
        $this->contentEncoding = self::CONTENT_ENCODING_AES128GCM;

        return $this;
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

    public function setKeys(Keys $keys): self
    {
        $this->keys = $keys;

        return $this;
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

        $object = self::create($input['endpoint']);
        if (array_key_exists('contentEncoding', $input)) {
            Assertion::nullOrString($input['contentEncoding'], 'Invalid input');
            $object->withContentEncoding($input['contentEncoding']);
        }
        if (array_key_exists('keys', $input)) {
            Assertion::isArray($input['keys'], 'Invalid input');
            $object->setKeys(Keys::createFromAssociativeArray($input['keys']));
        }

        return $object;
    }
}
