<?php
/**
 * @author Igor Timoshenkov [it@campoint.net]
 * @started: 03.09.2018 9:21
 */

namespace Minishlink\WebPush;

use function in_array;
use JsonSerializable;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Standardized response from sending a message
 */
class MessageSentReport implements JsonSerializable
{
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     */
    public function __construct(RequestInterface $request, ResponseInterface $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->response->getStatusCode() === 200;
    }

    /**
     * @return string
     */
    public function getEndpoint(): string
    {
        return (string) $this->request->getUri();
    }

    /**
     * @return bool
     */
    public function isSubscriptionExpired(): bool
    {
        return in_array($this->response->getStatusCode(), [404, 410], true);
    }

    /**
     * @return string
     */
    public function getReason(): string
    {
        return $this->response->getReasonPhrase();
    }

    /**
     * @return string
     */
    public function getRequestPayload(): string
    {
        return $this->request->getBody()->getContents();
    }

    /**
     * @return string
     */
    public function getResponseContent(): string
    {
        return $this->response->getBody()->getContents();
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'success' => $this->isSuccess(),
            'expired' => $this->isSubscriptionExpired(),
            'reason' => $this->getReason(),
            'endpoint' => $this->getEndpoint(),
            'payload' => $this->getResponseContent(),
        ];
    }
}
