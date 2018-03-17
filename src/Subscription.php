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

    /**
     * Subscription constructor.
     *
     * @param string      $endpoint
     * @param null|string $publicKey
     * @param null|string $authToken
     */
    public function __construct(string $endpoint, ?string $publicKey = null, ?string $authToken = null)
    {
        $this->endpoint = $endpoint;
        $this->publicKey = $publicKey;
        $this->authToken = $authToken;
    }

    /**
     * Subscription factory.
     *
     * @param array $associativeArray (with keys endpoint, publicKey, authToken)
     * @return Subscription
     */
    public static function create(array $associativeArray): Subscription {
        $instance = new self(
            $associativeArray['endpoint'],
            $associativeArray['publicKey'],
            $associativeArray['authToken']
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
}
