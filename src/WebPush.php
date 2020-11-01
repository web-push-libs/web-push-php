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

    public static function create(ClientInterface $client, RequestFactoryInterface $requestFactory, ExtensionManager $extensionManager): self
    {
        return new self($client, $requestFactory, $extensionManager);
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

    public function send(Notification $notification, Subscription $subscription): StatusReport
    {
        $this->logger->debug('Sending notification', ['notification' => $notification, 'subscription' => $subscription]);
        $request = $this->requestFactory->createRequest('POST', $subscription->getEndpoint());
        $request = $this->extensionManager->process($request, $notification, $subscription);
        $this->logger->debug('Request ready', ['request' => $request]);

        $response = $this->client->sendRequest($request);
        $this->logger->debug('Response received', ['response' => $response]);

        $statusCode = $response->getStatusCode();
        if (201 === $statusCode || 202 === $statusCode) {
            $location = $response->getHeaderLine('location');
            $statusReport = new StatusReportSuccess(
                $subscription,
                $notification,
                $location,
                $response->getHeader('Link')
            );
        } else {
            $statusReport = new StatusReportFailure(
                $subscription,
                $notification,
                $statusCode,
                $response->getReasonPhrase(),
                $request,
                $response
            );
        }

        $this->eventDispatcher->dispatch($statusReport);

        return $statusReport;
    }
}
