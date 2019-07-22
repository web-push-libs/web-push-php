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

namespace WebPush;

use ErrorException;
use Exception;
use Generator;

class WebPush
{
    /**
     * @var Client
     */
    private $client;
    /**
     * @var Contracts\AuthorizationInterface|null
     */
    private $auth;
    /**
     * @var Options
     */
    private $options;
    /**
     * @var int
     */
    private $padding = Encryption::MAX_PAYLOAD_LENGTH;

    /**
     * @param Contracts\AuthorizationInterface|null $auth
     * @param Options|null $options
     * @param Client|null $client
     */
    public function __construct(
        ?Contracts\AuthorizationInterface $auth = null,
        ?Options $options = null,
        ?Client $client = null
    ) {
        $this->auth = $auth;
        $this->options = Options::wrap($options);
        $this->client = $client ?? new Client();
    }

    /**
     * @param Contracts\SubscriptionInterface $subscription
     * @param string|null $payload
     * @param Contracts\AuthorizationInterface|null $auth Set the Auth only for this notification.
     * @param Options|null $options Set the Options only for this notification.
     *
     * @return WebPush
     * @throws ErrorException
     */
    public function queueNotification(
        Contracts\SubscriptionInterface $subscription,
        ?string $payload = null,
        ?Contracts\AuthorizationInterface $auth = null,
        ?Options $options = null
    ): self {
        QueueFactory::create('notifications')->push(
            $this->buildNotification($subscription, $payload, $auth, $options)
        );

        return $this;
    }

    /**
     * @param int $batch_size
     *
     * @return Generator|MessageSentReport[]
     * @throws Exception
     */
    public function deliver(int $batch_size = 1000): ?Generator
    {
        $promises = QueueFactory::create('promises');
        while ($notification = QueueFactory::create('notifications')->pop()) {
            $promises->push($this->client->sendAsync($notification));

            if ($promises->count() >= $batch_size) {
                yield from $this->flush();
                CacheFactory::create('vapid')->clear();
            }
        }

        if ($promises->isNotEmpty()) {
            yield from $this->flush();
        }
    }

    public function enableVapidHeaderReuse(): void
    {
        CacheFactory::create('vapid')->enable();
    }

    public function disableVapidHeaderReuse(): void
    {
        CacheFactory::create('vapid')->disable();
    }

    public function countQueuedNotifications(): int
    {
        return QueueFactory::create('notifications')->count();
    }

    /**
     * @param int|bool $padding
     */
    public function setPadding($padding): void
    {
        if (is_numeric($padding)) {
            $this->padding = (int) $padding;
        } else {
            $this->padding = (bool) $padding ? Encryption::MAX_PAYLOAD_LENGTH : 0;
        }
    }

    /**
     * @param Contracts\SubscriptionInterface $subscription
     * @param string|null $payload
     * @param Contracts\AuthorizationInterface|null $auth
     * @param Options|null $options
     *
     * @return Notification
     * @throws ErrorException
     */
    private function buildNotification(
        Contracts\SubscriptionInterface $subscription,
        ?string $payload = null,
        ?Contracts\AuthorizationInterface $auth = null,
        ?Options $options = null
    ): Notification {
        $auth = $auth ?? $this->auth;
        if ($auth === null) {
            throw new ErrorException(
                sprintf('Authorization must be provided as a parameter to %s or %s.', __CLASS__, __METHOD__)
            );
        }

        return new Notification(
            $subscription,
            Payload::create($subscription, (string) $payload, $this->padding),
            $options ?? $this->options,
            $auth
        );
    }

    private function flush(): ?Generator
    {
        while ($promise = QueueFactory::create('promises')->pop()) {
            yield $promise->wait();
        }
    }
}
