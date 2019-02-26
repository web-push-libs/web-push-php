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
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param bool              $success
     * @param string            $reason
     */
    public function __construct(RequestInterface $request, ?ResponseInterface $response = null, bool $success = true, $reason = 'OK')
    {
        $this->request  = $request;
        $this->response = $response;
        $this->success  = $success;
        $this->reason   = $reason;
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @param bool $success
     *
     * @return MessageSentReport
     */
    public function setSuccess(bool $success): MessageSentReport
    {
        $this->success = $success;
        return $this;
    }

    /**
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * @param RequestInterface $request
     *
     * @return MessageSentReport
     */
    public function setRequest(RequestInterface $request): MessageSentReport
    {
        $this->request = $request;
        return $this;
    }

    /**
     * @return ResponseInterface | null
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    /**
     * @param ResponseInterface $response
     *
     * @return MessageSentReport
     */
    public function setResponse(ResponseInterface $response): MessageSentReport
    {
        $this->response = $response;
        return $this;
    }

    /**
     * @return string
     */
    public function getEndpoint(): string
    {
        return $this->request->getUri()->__toString();
    }

    /**
     * @return bool
     */
    public function isSubscriptionExpired(): bool
    {
        if (!$this->response) {
            return false;
        }

        return \in_array($this->response->getStatusCode(), [404, 410], true);
    }

    /**
     * @return string
     */
    public function getReason(): string
    {
        return $this->reason;
    }

    /**
     * @param string $reason
     *
     * @return MessageSentReport
     */
    public function setReason(string $reason): MessageSentReport
    {
        $this->reason = $reason;
        return $this;
    }

    /**
     * @return string
     */
    public function getRequestPayload(): string
    {
        return $this->request->getBody()->getContents();
    }

    /**
     * @return string | null
     */
    public function getResponseContent(): ?string
    {
        if (!$this->response) {
            return null;
        }

        return $this->response->getBody()->getContents();
    }

    /**
     * @return array|mixed
     */
    public function jsonSerialize()
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
