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

    public function __construct($endpoint, $payload, $userPublicKey)
    {
        $this->endpoint = $endpoint;
        $this->payload = $payload;
        $this->userPublicKey = $userPublicKey;
    }

    /**
     * @return string
     */
    public function getEndpoint()
    {
        return $this->endpoint;
    }
}
