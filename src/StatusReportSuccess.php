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
