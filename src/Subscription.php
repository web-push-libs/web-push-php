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
     * @param string $contentEncoding (Optional) defaults to "aesgcm"
     * @throws \ErrorException
     */
    public function __construct(
        private readonly string $endpoint,
        private readonly string $publicKey,
        private readonly string $authToken,
        private readonly string $contentEncoding = "aesgcm",
    ) {
        $supportedContentEncodings = ['aesgcm', 'aes128gcm'];
        if ($contentEncoding && !in_array($contentEncoding, $supportedContentEncodings, true)) {
            throw new \ErrorException('This content encoding ('.$contentEncoding.') is not supported.');
        }
        if(empty($publicKey) || empty($authToken) || empty($contentEncoding)) {
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
                $associativeArray['contentEncoding'] ?? "aesgcm"
            );
        }

        if (array_key_exists('publicKey', $associativeArray) || array_key_exists('authToken', $associativeArray) || array_key_exists('contentEncoding', $associativeArray)) {
            return new self(
                $associativeArray['endpoint'] ?? "",
                $associativeArray['publicKey'] ?? "",
                $associativeArray['authToken'] ?? "",
                $associativeArray['contentEncoding'] ?? "aesgcm"
            );
        }

        throw new \ValueError('Missing values.');
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

    public function getContentEncoding(): string
    {
        return $this->contentEncoding;
    }
}
