<?php

declare(strict_types=1);

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2020 Spomky-Labs
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
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
