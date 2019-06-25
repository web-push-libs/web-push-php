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

use ErrorException;

class Notification
{
    /** @var Contracts\SubscriptionInterface */
    private $subscription;

    /** @var Payload */
    private $payload;

    /** @var Options */
    private $options;

    /** @var Contracts\AuthorizationInterface */
    private $auth;

    /**
     * @param Contracts\SubscriptionInterface $subscription
     * @param Payload $payload
     * @param Options $options
     * @param Contracts\AuthorizationInterface $auth
     */
    public function __construct(Contracts\SubscriptionInterface $subscription, Payload $payload, Options $options, Contracts\AuthorizationInterface $auth)
    {
        $this->subscription = $subscription;
        $this->payload = $payload;
        $this->auth = $auth;
        $this->options = $options;
    }

    public function getSubscription(): Contracts\SubscriptionInterface
    {
        return $this->subscription;
    }

    public function getOptions(): Options
    {
        return $this->options;
    }

    public function getAuth(): Contracts\AuthorizationInterface
    {
        return $this->auth;
    }

    public function getPayload(): Payload
    {
        return $this->payload;
    }

    /**
     * @return Headers
     * @throws ErrorException
     */
    public function buildHeaders(): Headers
    {
        return (new HeadersBuilder())->build($this);
    }
}
