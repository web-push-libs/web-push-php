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
use Psr\Http\Message\ResponseInterface;

class StatusReport
{
    private Subscription $subscription;
    private Notification $notification;
    private RequestInterface $request;
    private ResponseInterface $response;

    public function __construct(Subscription $subscription, Notification $notification, RequestInterface $request, ResponseInterface $response)
    {
        $this->subscription = $subscription;
        $this->notification = $notification;
        $this->request = $request;
        $this->response = $response;
    }

    public function getSubscription(): Subscription
    {
        return $this->subscription;
    }

    public function getNotification(): Notification
    {
        return $this->notification;
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    public function isSuccess(): bool
    {
        $code = $this->response->getStatusCode();

        return $code >= 200 && $code < 300;
    }

    public function getLocation(): string
    {
        return $this->response->getHeaderLine('location');
    }

    /**
     * @return string[]
     */
    public function getLinks(): array
    {
        return $this->response->getHeader('link');
    }
}
