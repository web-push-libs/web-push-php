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

class Subscription
{
    /** @var string */
    private $endpoint;

    /** @var null|string */
    private $publicKey;

    /** @var null|string */
    private $authToken;

    /** @var null|string */
    private $contentEncoding;

    /**
     * Subscription constructor.
     *
     * @param string $endpoint
     * @param null|string $publicKey
     * @param null|string $authToken
     * @param string $contentEncoding (Optional) Must be "aesgcm"
     * @throws \ErrorException
     */
    public function __construct(
        string $endpoint,
        ?string $publicKey = null,
        ?string $authToken = null,
        ?string $contentEncoding = null
    ) {
        $this->endpoint = $endpoint;

        if ($publicKey || $authToken || $contentEncoding) {
            $supportedContentEncodings = ['aesgcm', 'aes128gcm'];
            if ($contentEncoding && !in_array($contentEncoding, $supportedContentEncodings)) {
                throw new \ErrorException('This content encoding ('.$contentEncoding.') is not supported.');
            }

            $this->publicKey = $publicKey;
            $this->authToken = $authToken;
            $this->contentEncoding = $contentEncoding ?: "aesgcm";
        }
    }

    /**
     * Subscription factory.
     *
     * @param array $associativeArray (with keys endpoint, publicKey, authToken, contentEncoding)
     * @return Subscription
     * @throws \ErrorException
     */
    public static function create(array $associativeArray): Subscription {
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
     * @return string
     */
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * @return null|string
     */
    public function getPublicKey(): ?string
    {
        return $this->publicKey;
    }

    /**
     * @return null|string
     */
    public function getAuthToken(): ?string
    {
        return $this->authToken;
    }

    /**
     * @return null|string
     */
    public function getContentEncoding(): ?string
    {
        return $this->contentEncoding;
    }
}
