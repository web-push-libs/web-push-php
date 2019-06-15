<?php

declare(strict_types = 1);

namespace Minishlink\WebPush\Tests\Integration;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Minishlink\WebPush\Client;
use Minishlink\WebPush\Tests\TestCase;
use Mockery;

/**
 * @covers \Minishlink\WebPush\GuzzleAsyncClient
 */
final class ClientTest extends TestCase
{
    /**
     * @var GuzzleClient|Mockery\MockInterface
     */
    private $guzzle;
    /**
     * @var Client
     */
    private $client;

    protected function setUp()
    {
        parent::setUp();
        $this->guzzle = Mockery::mock(GuzzleClient::class);
        $this->client = new Client($this->guzzle);
    }

    /**
     * @dataProvider providesPromises
     *
     * @param PromiseInterface $promise
     * @param bool $success
     */
    public function testHandlesHttpErrorsAndSuccesses(PromiseInterface $promise, bool $success): void
    {
        $this->guzzle->expects('sendAsync')->once()->andReturn($promise);
        $response = $this->client->sendNow('');
        $this->assertEquals($success, $response->isSuccess());
    }

    public function providesPromises(): array
    {
        return [
            '200 OK' => [$this->getSuccessfulPromise(), true],
            '400 Bad Request (Fulfilled)' => [new FulfilledPromise(new Response(400)), false],
            '400 Bad Request (Rejected)' => [$this->getRejectedPromise(400), false],
            '404 Not Found' => [$this->getRejectedPromise(404), false],
            '410 Gone' => [$this->getRejectedPromise(410), false]
        ];
    }

    private function getSuccessfulPromise(): FulfilledPromise
    {
        return new FulfilledPromise(new Response(200));
    }

    private function getRejectedPromise(int $status): RejectedPromise
    {
        return new RejectedPromise(
            new RequestException('Rejected', new Request('', ''), new Response($status))
        );
    }
}
