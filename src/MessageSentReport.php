<?php
/**
 * @author Igor Timoshenkov [it@campoint.net]
 * @started: 03.09.2018 9:21
 */

namespace Minishlink\WebPush;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use function in_array;

class MessageSentReport implements ResponseInterface
{
    /**
     * @var RequestInterface
     */
    protected $request;
    /**
     * @var ResponseInterface
     */
    protected $response;

    public function __construct(RequestInterface $request, ResponseInterface $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    public function isSuccess(): bool
    {
        return $this->response->getStatusCode() === 200;
    }

    public function getEndpoint(): string
    {
        return (string) $this->request->getUri();
    }

    public function isSubscriptionExpired(): bool
    {
        return in_array($this->response->getStatusCode(), [404, 410], true);
    }

    public function getRequestPayload(): string
    {
        return $this->request->getBody()->getContents();
    }

    public function getReasonPhrase(): string
    {
        return $this->response->getReasonPhrase();
    }

    public function getResponsePayload(): string
    {
        return $this->response->getBody()->getContents();
    }

    public function getProtocolVersion(): string
    {
        return $this->response->getProtocolVersion();
    }

    public function withProtocolVersion($version): self
    {
        return new static($this->request, $this->response->withProtocolVersion($version));
    }

    public function getHeaders(): array
    {
        return $this->response->getHeaders();
    }

    public function hasHeader($name): bool
    {
        return $this->response->hasHeader($name);
    }

    public function getHeader($name): array
    {
        return $this->response->getHeader($name);
    }

    public function getHeaderLine($name): string
    {
        return $this->response->getHeaderLine($name);
    }

    public function withHeader($name, $value): self
    {
        return new static($this->request, $this->response->withHeader($name, $value));
    }

    public function withAddedHeader($name, $value): self
    {
        return new static($this->request, $this->response->withAddedHeader($name, $value));
    }

    public function withoutHeader($name): self
    {
        return new static($this->request, $this->response->withoutHeader($name));
    }

    public function getBody(): StreamInterface
    {
        return $this->response->getBody();
    }

    public function withBody(StreamInterface $body): self
    {
        return new static($this->request, $this->response->withBody($body));
    }

    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    public function withStatus($code, $reasonPhrase = '')
    {
        return new static($this->request, $this->response->withStatus($code, $reasonPhrase));
    }
}
