<?php

declare(strict_types = 1);

/*
 * This file is part of the WebPush library.
 *
 * (c) Louis Lagrange <lagrange.louis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WebPush;

class Subscription implements Contracts\SubscriptionInterface
{
    /**
     * @var string
     */
    private $endpoint;
    /**
     * @var string
     */
    private $publicKey;
    /**
     * @var string
     */
    private $authToken;
    /**
     * @var string
     */
    private $encoding;

    /**
     * @param string $endpoint
     * @param string $publicKey
     * @param string $authToken
     * @param string $encoding
     */
    public function __construct(
        string $endpoint,
        string $publicKey,
        string $authToken,
        string $encoding = 'aesgcm'
    ) {
        $this->endpoint = $endpoint;
        $this->publicKey = $publicKey;
        $this->authToken = $authToken;
        $this->encoding = $encoding;
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

    public function getEncoding(): string
    {
        return $this->encoding;
    }
}
