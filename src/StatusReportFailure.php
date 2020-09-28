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

final class StatusReportFailure extends StatusReport
{
    private string $reason;
    private int $code;

    public function __construct(Subscription $subscription, Notification $notification, int $code, string $reason)
    {
        parent::__construct($subscription, $notification, false);
        $this->reason = $reason;
        $this->code = $code;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
