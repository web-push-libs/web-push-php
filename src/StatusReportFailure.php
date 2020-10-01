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

final class StatusReportFailure extends StatusReport
{
    private string $reason;
    private int $code;

    private RequestInterface $request;
    private ResponseInterface $response;

    public function __construct(Subscription $subscription, Notification $notification, int $code, string $reason, RequestInterface $request, ResponseInterface $response)
    {
        parent::__construct($subscription, $notification, false);
        $this->reason = $reason;
        $this->code = $code;
        $this->request = $request;
        $this->response = $response;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
