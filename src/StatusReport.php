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

class StatusReport
{
    private Subscription $subscription;
    private Notification $notification;
    private bool $success;

    public function __construct(Subscription $subscription, Notification $notification, bool $success)
    {
        $this->subscription = $subscription;
        $this->notification = $notification;
        $this->success = $success;
    }

    public function getSubscription(): Subscription
    {
        return $this->subscription;
    }

    public function getNotification(): Notification
    {
        return $this->notification;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }
}
