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

    /** @var array */
    private $options = [
        'TTL' => null, 'urgency' => null, 'topic' => null
    ];

    /** @var array */
    private $auth = [
        'GCM' => null, 'VAPID' => null
    ];

    /**
     * @param Subscription $subscription
     * @param string $payload
     * @param array $options
     * @param array $auth
     *
     * @throws Exception
     */
    public function __construct(Subscription $subscription, string $payload, array $options, array $auth)
    {
        $this->subscription = $subscription;
        $this->setPayload($payload);
        $this->setAuth($auth);
        $this->setOptions($options);
    }

    public function getSubscription(): Subscription
    {
        return $this->subscription;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    /**
     * @return array|string|null
     */
    public function getAuth()
    {
        if ($this->hasAuth() === false) {
            return null;
        }

        return $this->auth[$this->getAuthType()];
    }

    public function getAuthType(): ?string
    {
        if ($this->hasAuth() === false) {
            return null;
        }

        return !empty($this->auth['GCM']) ? 'GCM' : 'VAPID';
    }

    public function hasAuth(): bool
    {
        return !empty($this->auth);
    }

    private function setPayload(string $payload): void
    {
        $this->payload = $payload;
    }

    private function setOptions(array $options): void
    {
        $this->options = array_filter(array_intersect_key($options, $this->options));
    }

    /**
     * @param array $auth
     *
     * @throws Exception
     */
    private function setAuth(array $auth): void
    {
        $auth = array_filter(array_intersect_key($auth, $this->auth));
        if (count($auth) > 1) {
            throw new Exception('You must specify only one form of authorization');
        }
        $this->auth = $auth;
    }
}
