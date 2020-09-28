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

use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ExtensionManager
{
    /**
     * @var Extension[]
     */
    private array $extensions = [];
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

    public function add(Extension $extension): self
    {
        $this->extensions[] = $extension;
        $this->logger->debug('Extension added', ['extension' => $extension]);

        return $this;
    }

    public function process(RequestInterface $request, Notification $notification, Subscription $subscription): RequestInterface
    {
        $this->logger->debug('Processing the request');
        foreach ($this->extensions as $extension) {
            $request = $extension->process($request, $notification, $subscription);
        }
        $this->logger->debug('Processing done');

        return $request;
    }
}
