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
    protected ContentEncoding $contentEncoding;
    /**
     * This is a data class. No key validation is done.
     * @param string|\Minishlink\WebPush\ContentEncoding $contentEncoding (Optional) defaults to "aes128gcm" as defined to rfc8291.
     * @throws \ErrorException
     */
    public function __construct(
        protected readonly string $endpoint,
        protected readonly string $publicKey,
        protected readonly string $authToken,
        ContentEncoding|string  $contentEncoding = ContentEncoding::aes128gcm,
    ) {
        if(is_string($contentEncoding)) {
            try {
                if(empty($contentEncoding)) {
                    $this->contentEncoding = ContentEncoding::aesgcm; // default
                } else {
                    $this->contentEncoding = ContentEncoding::from($contentEncoding);
                }
            } catch(\ValueError) {
                throw new \ValueError('This content encoding ('.$contentEncoding.') is not supported.');
            }
        } else {
            $this->contentEncoding = $contentEncoding;
        }
        if(empty($publicKey) || empty($authToken)) {
            throw new \ValueError('Missing values.');
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
                $associativeArray['endpoint'] ?? "",
                $associativeArray['keys']['p256dh'] ?? "",
                $associativeArray['keys']['auth'] ?? "",
                $associativeArray['contentEncoding'] ?? ContentEncoding::aes128gcm,
            );
        }

        return new self(
            $associativeArray['endpoint'] ?? "",
            $associativeArray['publicKey'] ?? "",
            $associativeArray['authToken'] ?? "",
            $associativeArray['contentEncoding'] ?? ContentEncoding::aes128gcm,
        );
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function getAuthToken(): string
    {
        return $this->authToken;
    }

    public function getContentEncoding(): ContentEncoding
    {
        return $this->contentEncoding;
    }
}
