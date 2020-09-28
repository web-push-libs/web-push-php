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

final class StatusReportSuccess extends StatusReport
{
    private string $location;

    public function __construct(Subscription $subscription, Notification $notification, string $location)
    {
        parent::__construct($subscription, $notification, true);
        $this->location = $location;
    }

    public function getLocation(): string
    {
        return $this->location;
    }
}
