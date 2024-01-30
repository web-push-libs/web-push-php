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
    /**
     * @param string|null $contentEncoding (Optional) Must be "aesgcm"
     * @throws \ErrorException
     */
    public function __construct(
        private string  $endpoint,
        private ?string $publicKey = null,
        private ?string $authToken = null,
        private ?string $contentEncoding = null
    ) {
        if($publicKey || $authToken || $contentEncoding) {
            $supportedContentEncodings = ['aesgcm', 'aes128gcm'];
            if ($contentEncoding && !in_array($contentEncoding, $supportedContentEncodings, true)) {
                throw new \ErrorException('This content encoding ('.$contentEncoding.') is not supported.');
            }
            $this->contentEncoding = $contentEncoding ?: "aesgcm";
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
                $associativeArray['contentEncoding'] ?? "aesgcm"
            );
        }

        if (array_key_exists('publicKey', $associativeArray) || array_key_exists('authToken', $associativeArray) || array_key_exists('contentEncoding', $associativeArray)) {
            return new self(
                $associativeArray['endpoint'],
                $associativeArray['publicKey'] ?? null,
                $associativeArray['authToken'] ?? null,
                $associativeArray['contentEncoding'] ?? "aesgcm"
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
        return $this->contentEncoding;
    }
}
