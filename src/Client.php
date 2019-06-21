<?php

declare(strict_types = 1);

namespace Minishlink\WebPush;

use Exception;
use Http\Client\HttpAsyncClient;
use Http\Discovery\HttpAsyncClientDiscovery;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Promise\Promise;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

class Client
{
    /**
     * @var HttpAsyncClient
     */
    private $client;
    /**
     * @var RequestFactoryInterface
     */
    private $requestFactory;
    /**
     * @var StreamFactoryInterface
     */
    private $streamFactory;
    /**
     * @var RequestInterface
     */
    private $request;

    public function __construct(
        ?HttpAsyncClient $client = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null
    ) {
        $this->client = $client ?? HttpAsyncClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
    }

    /**
     * @param string $endpoint
     * @param Headers $headers
     * @param string|null $payload
     *
     * @return MessageSentReport
     * @throws Exception
     */
    public function sendNow(string $endpoint, Headers $headers, ?string $payload = null): MessageSentReport
    {
        return $this->sendAsync(...func_get_args())->wait();
    }

    /**
     * @param string $endpoint
     * @param Headers $headers
     * @param string|null $payload
     *
     * @return Promise
     * @throws Exception
     */
    public function sendAsync(string $endpoint, Headers $headers, ?string $payload = null): Promise
    {
        return $this->createRequest($endpoint)
            ->withPayload($payload)
            ->withHeaders($headers)
            ->send();
    }

    private function createRequest(string $endpoint): self
    {
        $this->request = $this->requestFactory->createRequest('POST', $endpoint);

        return $this;
    }

    private function withHeaders(Headers $headers): self
    {
        foreach ($headers as $header => $value) {
            $this->request->withHeader($header, $value);
        }

        return $this;
    }

    private function withPayload(?string $payload): self
    {
        if ($payload) {
            $this->request->withBody($this->streamFactory->createStream($payload));
        }

        return $this;
    }

    /**
     * @return Promise
     * @throws Exception
     */
    private function send(): Promise
    {
        $request = $this->request;
        unset($this->request);

        return $this->client->sendAsyncRequest($request)
            ->then(static function (ResponseInterface $response) use ($request) {
                return new MessageSentReport($request, $response);
            });
    }
}
