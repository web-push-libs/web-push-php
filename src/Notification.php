<?php

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

    /** @var string */
    private $payload;

    /** @var string */
    private $userPublicKey;

    /** @var string */
    private $userAuthToken;

    /** @var array Options : TTL, urgency, topic */
    private $options;

    /** @var array Auth details : GCM, VAPID */
    private $auth;

    public function __construct($endpoint, $payload, $userPublicKey, $userAuthToken, $options, $auth)
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
    public function getEndpoint()
    {
        return $this->endpoint;
    }

    /**
     * @return null|string
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * @return null|string
     */
    public function getUserPublicKey()
    {
        return $this->userPublicKey;
    }

    /**
     * @return null|string
     */
    public function getUserAuthToken()
    {
        return $this->userAuthToken;
    }

    /**
     * @param array $defaultOptions
     *
     * @return array
     */
    public function getOptions(array $defaultOptions = array())
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
    public function getAuth(array $defaultAuth)
    {
        return count($this->auth) > 0 ? $this->auth : $defaultAuth;
    }
}
