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
    /**
     * @var string[]
     */
    private array $links;

    public function __construct(Subscription $subscription, Notification $notification, string $location, array $links)
    {
        parent::__construct($subscription, $notification, true);
        $this->location = $location;
        $this->links = $links;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    /**
     * @return string[]
     */
    public function getLinks(): array
    {
        return $this->links;
    }
}
