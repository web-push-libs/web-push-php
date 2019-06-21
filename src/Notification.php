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

use Exception;

class Notification
{
    /** @var Subscription */
    private $subscription;

    /** @var string */
    private $payload;

    /** @var Options */
    private $options;

    /** @var Auth */
    private $auth;

    /**
     * @param Subscription $subscription
     * @param string $payload
     * @param Options $options
     * @param Auth $auth
     *
     * @throws Exception
     */
    public function __construct(Subscription $subscription, string $payload, Options $options, Auth $auth)
    {
        $this->subscription = $subscription;
        $this->setPayload($payload);
        $this->auth = $auth;
        $this->options = $options;
    }

    public function getSubscription(): Subscription
    {
        return $this->subscription;
    }

    public function getOptions(): Options
    {
        return $this->options;
    }

    public function getAuth(): Auth
    {
        return $this->auth;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    private function setPayload(string $payload): void
    {
        $this->payload = $payload;
    }
}
