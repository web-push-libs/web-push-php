<?php

declare(strict_types = 1);

namespace WebPush;

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
     * @param Notification $notification
     *
     * @return MessageSentReport
     * @throws Exception
     */
    public function sendNow(Notification $notification): MessageSentReport
    {
        return $this->sendAsync(...func_get_args())->wait();
    }

    /**
     * @param Notification $notification
     *
     * @return Promise
     * @throws Exception
     */
    public function sendAsync(Notification $notification): Promise
    {
        return $this->createRequest($notification->getSubscription())
            ->withPayload($notification->getPayload())
            ->withHeaders($notification->buildHeaders())
            ->send();
    }

    private function createRequest(Contracts\SubscriptionInterface $subscription): self
    {
        $this->request = $this->requestFactory->createRequest('POST', $subscription->getEndpoint());

        return $this;
    }

    private function withHeaders(Headers $headers): self
    {
        foreach ($headers as $header => $value) {
            $this->request = $this->request->withHeader($header, $value);
        }

        return $this;
    }

    private function withPayload(Payload $payload): self
    {
        if ($payload->isEmpty() === false) {
            $this->request = $this->request->withBody($this->streamFactory->createStream($payload->toString()));
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
