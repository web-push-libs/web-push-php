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

class Subscription implements SubscriptionInterface
{
    public const defaultContentEncoding = ContentEncoding::aesgcm; // Default for legacy input. The next major will use "aes128gcm" as defined to rfc8291.
    protected ?ContentEncoding $contentEncoding = null;

    /**
     * This is a data class. No key validation is done.
     * @param string|\Minishlink\WebPush\ContentEncoding|null $contentEncoding (Optional) defaults to "aesgcm". The next major will use "aes128gcm" as defined to rfc8291.
     */
    public function __construct(
        private string  $endpoint,
        private ?string $publicKey = null,
        private ?string $authToken = null,
        ContentEncoding|string|null $contentEncoding = null,
    ) {
        if ($publicKey || $authToken || $contentEncoding) {
            if (is_string($contentEncoding)) {
                try {
                    if (empty($contentEncoding)) {
                        $contentEncoding = self::defaultContentEncoding;
                    } else {
                        $contentEncoding = ContentEncoding::from($contentEncoding);
                    }
                } catch (\ValueError) {
                    throw new \ValueError('This content encoding ('.$contentEncoding.') is not supported.');
                }
            } elseif ($contentEncoding === null) {
                $contentEncoding =  self::defaultContentEncoding;
            }
            $this->contentEncoding = $contentEncoding;
        }
    }

    /**
     * @param array $associativeArray (with keys endpoint, publicKey, authToken, contentEncoding)
     * @throws \ErrorException
     */
    public static function create(array $associativeArray): self
    {
        if (array_key_exists('keys', $associativeArray) && is_array($associativeArray['keys'])) {
            return new self(
                $associativeArray['endpoint'],
                $associativeArray['keys']['p256dh'] ?? null,
                $associativeArray['keys']['auth'] ?? null,
                $associativeArray['contentEncoding'] ?? ContentEncoding::aesgcm,
            );
        }

        if (array_key_exists('publicKey', $associativeArray) || array_key_exists('authToken', $associativeArray) || array_key_exists('contentEncoding', $associativeArray)) {
            return new self(
                $associativeArray['endpoint'],
                $associativeArray['publicKey'] ?? null,
                $associativeArray['authToken'] ?? null,
                $associativeArray['contentEncoding'] ?? ContentEncoding::aesgcm,
            );
        }

        return new self(
            $associativeArray['endpoint']
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * {@inheritDoc}
     */
    public function getPublicKey(): ?string
    {
        return $this->publicKey;
    }

    /**
     * {@inheritDoc}
     */
    public function getAuthToken(): ?string
    {
        return $this->authToken;
    }

    /**
     * {@inheritDoc}
     */
    public function getContentEncoding(): ?string
    {
        return $this->contentEncoding?->value;
    }

    public function getContentEncodingTyped(): ?ContentEncoding
    {
        return $this->contentEncoding;
    }
}
