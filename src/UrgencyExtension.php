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

use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class UrgencyExtension implements Extension
{
    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function process(RequestInterface $request, Notification $notification, Subscription $subscription): RequestInterface
    {
        $urgency = $notification->getUrgency();
        $this->logger->debug('Processing with the Urgency extension', ['Urgency' => $urgency]);

        return $request
            ->withHeader('Urgency', $urgency)
        ;
    }
}
