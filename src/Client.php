<?php

declare(strict_types = 1);

namespace Minishlink\WebPush;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class Client
{
    /**
     * @var GuzzleClient|null
     */
    private $client;

    public function __construct(?GuzzleClient $client = null)
    {
        $this->client = $client ?? new GuzzleClient([
                'http_errors' => false,
                'timeout' => 30
            ]);
    }

    public function sendNow(Request $request): MessageSentReport
    {
        return $this->sendAsync($request)->wait();
    }

    public function sendAsync(Request $request): PromiseInterface
    {
        return $this->send($request);
    }

    public function buildRequest(string $endpoint, array $headers = [], ?string $body = null): Request
    {
        return new Request('POST', $endpoint, $headers, $body);
    }

    private function send(Request $request): PromiseInterface
    {
        return $this->client->sendAsync($request)
            ->then(static function (Response $response) use ($request) {
                return new MessageSentReport($request, $response);
            }, static function (RequestException $reason) {
                return new MessageSentReport($reason->getRequest(), $reason->getResponse());
            });
    }
}
