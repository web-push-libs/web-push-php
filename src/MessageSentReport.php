<?php
/**
 * @author Igor Timoshenkov [it@campoint.net]
 * @started: 03.09.2018 9:21
 */

namespace Minishlink\WebPush;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Standardized response from sending a message
 */
class MessageSentReport implements \JsonSerializable
{
    /**
     * @var boolean
     */
    protected $success;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var ResponseInterface | null
     */
    protected $response;

    /**
     * @var string
     */
    protected $reason;

    /**
     * @param string $reason
     */
    public function __construct(RequestInterface $request, ?ResponseInterface $response = null, bool $success = true, $reason = 'OK')
    {
        $this->request  = $request;
        $this->response = $response;
        $this->success  = $success;
        $this->reason   = $reason;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): MessageSentReport
    {
        $this->success = $success;
        return $this;
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    public function setRequest(RequestInterface $request): MessageSentReport
    {
        $this->request = $request;
        return $this;
    }

    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    public function setResponse(ResponseInterface $response): MessageSentReport
    {
        $this->response = $response;
        return $this;
    }

    public function getEndpoint(): string
    {
        return $this->request->getUri()->__toString();
    }

    public function isSubscriptionExpired(): bool
    {
        if (!$this->response) {
            return false;
        }

        return \in_array($this->response->getStatusCode(), [404, 410], true);
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): MessageSentReport
    {
        $this->reason = $reason;
        return $this;
    }

    public function getRequestPayload(): string
    {
        return $this->request->getBody()->getContents();
    }

    public function getResponseContent(): ?string
    {
        if (!$this->response) {
            return null;
        }

        return $this->response->getBody()->getContents();
    }

    public function jsonSerialize(): array
    {
        return [
            'success'  => $this->isSuccess(),
            'expired'  => $this->isSubscriptionExpired(),
            'reason'   => $this->reason,
            'endpoint' => $this->getEndpoint(),
            'payload'  => $this->request->getBody()->getContents(),
        ];
    }
}
