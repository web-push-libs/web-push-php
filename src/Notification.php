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
    /**
     * @param array $options Options: TTL, urgency, topic
     * @param array $auth    Auth details: VAPID
     */
    public function __construct(
        private SubscriptionInterface $subscription,
        private ?string               $payload,
        private array                 $options,
        private array                 $auth
    ) {
    }

    public function getSubscription(): SubscriptionInterface
    {
        return $this->subscription;
    }

    public function getPayload(): ?string
    {
        return $this->payload;
    }

    public function getOptions(array $defaultOptions = []): array
    {
        $options = $this->options;
        $options['TTL'] = array_key_exists('TTL', $options) ? $options['TTL'] : $defaultOptions['TTL'];
        $options['urgency'] = array_key_exists('urgency', $options) ? $options['urgency'] : $defaultOptions['urgency'];
        $options['topic'] = array_key_exists('topic', $options) ? $options['topic'] : $defaultOptions['topic'];

        return $options;
    }

    public function getAuth(array $defaultAuth): array
    {
        return count($this->auth) > 0 ? $this->auth : $defaultAuth;
    }
}
