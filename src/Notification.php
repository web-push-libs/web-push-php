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

    public function __construct($endpoint, $payload, $userPublicKey, $userAuthToken)
    {
        $this->endpoint = $endpoint;
        $this->payload = $payload;
        $this->userPublicKey = $userPublicKey;
        $this->userAuthToken = $userAuthToken;
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
}
