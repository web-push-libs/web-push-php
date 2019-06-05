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
    /** @var Subscription */
    private $subscription;

    /** @var null|string */
    private $payload;

    /** @var array */
    private $options = [
        'TTL' => null, 'urgency' => null, 'topic' => null
    ];

    /** @var array Auth details : GCM, VAPID */
    private $auth;

    /**
     * @param Subscription $subscription
     * @param string|null $payload
     * @param array $options
     * @param array $auth
     */
    public function __construct(Subscription $subscription, ?string $payload, array $options, array $auth)
    {
        $this->subscription = $subscription;
        $this->payload = $payload;
        $this->auth = $auth;
        $this->setOptions($options);
    }

    /**
     * @return Subscription
     */
    public function getSubscription(): Subscription
    {
        return $this->subscription;
    }

    /**
     * @return null|string
     */
    public function getPayload(): ?string
    {
        return $this->payload;
    }

    /**
     * @param array $overrides
     *
     * @return array
     */
    public function getOptions(array $overrides = []): array
    {
        $allowed = $this->filterOptions($overrides);

        return array_merge($this->options, array_replace($allowed, array_filter($this->options)));
    }

    /**
     * @param array $default
     *
     * @return array
     */
    public function getAuth(array $default): array
    {
        return count($this->auth) > 0 ? $this->auth : $default;
    }

    /**
     * @param array $options
     */
    private function setOptions(array $options): void
    {
        $this->options = array_replace($this->options, $this->filterOptions($options));
    }

    /**
     * @param array $options
     *
     * @return array
     */
    private function filterOptions(array $options): array
    {
        return array_intersect_key($options, array_flip(['TTL', 'urgency', 'topic']));
    }
}
