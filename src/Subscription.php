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

namespace Minishlink\WebPush;

use InvalidArgumentException;

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

    /**
     * @param array $associativeArray (with keys endpoint, publicKey, authToken, encoding)
     *
     * @return Contracts\SubscriptionInterface
     * @throws InvalidArgumentException
     */
    public static function create(array $associativeArray): Contracts\SubscriptionInterface
    {
        if (array_key_exists('keys', $associativeArray) && is_array($associativeArray['keys'])) {
            return new static(
                $associativeArray['endpoint'],
                $associativeArray['keys']['p256dh'],
                $associativeArray['keys']['auth'],
                $associativeArray['encoding'] ?? 'aesgcm'
            );
        }

        if (array_key_exists('public_key', $associativeArray) || array_key_exists('auth_token', $associativeArray) || array_key_exists('encoding', $associativeArray)) {
            return new static(
                $associativeArray['endpoint'],
                $associativeArray['public_key'],
                $associativeArray['auth_token'],
                $associativeArray['encoding'] ?? 'aesgcm'
            );
        }

        throw new InvalidArgumentException('Unable to create Subscription from the parameters provided.');
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
