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

class Notification
{
    /** @var string */
    private $endpoint;

    /** @var null|string */
    private $payload;

    /** @var null|string */
    private $userPublicKey;

    /** @var null|string */
    private $userAuthToken;

    /** @var array Options : TTL, urgency, topic */
    private $options;

    /** @var array Auth details : GCM, VAPID */
    private $auth;

    /**
     * Notification constructor.
     *
     * @param string      $endpoint
     * @param null|string $payload
     * @param null|string $userPublicKey
     * @param null|string $userAuthToken
     * @param array       $options
     * @param array       $auth
     */
    public function __construct(string $endpoint, ?string $payload, ?string $userPublicKey, ?string $userAuthToken, array $options, array $auth)
    {
        $this->endpoint = $endpoint;
        $this->payload = $payload;
        $this->userPublicKey = $userPublicKey;
        $this->userAuthToken = $userAuthToken;
        $this->options = $options;
        $this->auth = $auth;
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
    public function getPayload(): ?string
    {
        return $this->payload;
    }

    /**
     * @return null|string
     */
    public function getUserPublicKey(): ?string
    {
        return $this->userPublicKey;
    }

    /**
     * @return null|string
     */
    public function getUserAuthToken(): ?string
    {
        return $this->userAuthToken;
    }

    /**
     * @param array $defaultOptions
     *
     * @return array
     */
    public function getOptions(array $defaultOptions = []): array
    {
        $options = $this->options;
        $options['TTL'] = array_key_exists('TTL', $options) ? $options['TTL'] : $defaultOptions['TTL'];
        $options['urgency'] = array_key_exists('urgency', $options) ? $options['urgency'] : $defaultOptions['urgency'];
        $options['topic'] = array_key_exists('topic', $options) ? $options['topic'] : $defaultOptions['topic'];

        return $options;
    }

    /**
     * @param array $defaultAuth
     *
     * @return array
     */
    public function getAuth(array $defaultAuth): array
    {
        return count($this->auth) > 0 ? $this->auth : $defaultAuth;
    }
}
