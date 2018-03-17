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

    /** @var string */
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
        string $contentEncoding = "aesgcm"
    ) {
        $supportedContentEncodings = ['aesgcm', 'aes128gcm'];
        if (!in_array($contentEncoding, $supportedContentEncodings)) {
            throw new \ErrorException('This content encoding ('.$contentEncoding.') is not supported.');
        }

        $this->endpoint = $endpoint;
        $this->publicKey = $publicKey;
        $this->authToken = $authToken;
        $this->contentEncoding = $contentEncoding;
    }

    /**
     * Subscription factory.
     *
     * @param array $associativeArray (with keys endpoint, publicKey, authToken, contentEncoding)
     * @return Subscription
     * @throws \ErrorException
     */
    public static function create(array $associativeArray): Subscription {
        $instance = new self(
            $associativeArray['endpoint'],
            $associativeArray['publicKey'],
            $associativeArray['authToken'],
            $associativeArray['contentEncoding']
        );

        return $instance;
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
     * @return string
     */
    public function getContentEncoding(): string
    {
        return $this->contentEncoding;
    }
}
