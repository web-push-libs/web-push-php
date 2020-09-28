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

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class WebPush
{
    private ClientInterface $client;
    private LoggerInterface $logger;
    private EventDispatcherInterface $eventDispatcher;
    private RequestFactoryInterface $requestFactory;
    private ExtensionManager $extensionManager;

    public function __construct(ClientInterface $client, RequestFactoryInterface $requestFactory, ExtensionManager $extensionManager)
    {
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->extensionManager = $extensionManager;
        $this->logger = new NullLogger();
        $this->eventDispatcher = new NullEventDispatcher();
    }

    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): self
    {
        $this->eventDispatcher = $eventDispatcher;

        return $this;
    }

    public function send(Notification $notification, Subscription $subscription): void
    {
        $this->logger->debug('Sending notification', ['notification' => $notification, 'subscription' => $subscription]);
        $request = $this->requestFactory->createRequest('POST', $subscription->getEndpoint());
        $request = $this->extensionManager->process($request, $notification, $subscription);
        $this->logger->debug('Request ready', ['request' => $request]);

        $response = $this->client->sendRequest($request);
        $this->logger->debug('Response received', ['response' => $response]);
        if (200 === $response->getStatusCode()) {
            $location = $response->getHeaderLine('location');
            $this->eventDispatcher->dispatch(new StatusReportSuccess($subscription, $notification, $location));

            return;
        }

        $this->eventDispatcher->dispatch(new StatusReportFailure(
            $subscription,
            $notification,
            $response->getStatusCode(),
            $response->getBody()->getContents()
        ));
    }
}
