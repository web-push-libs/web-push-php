<?php

declare(strict_types = 1);

namespace WebPush\Tests\Unit;

use Exception;
use GuzzleHttp\Psr7\Response;
use Http\Mock\Client as MockClient;
use WebPush\Client;
use WebPush\Notification;
use WebPush\Tests\TestCase;
use Psr\Http\Message\ResponseInterface;

final class ClientTest extends TestCase
{
    /**
     * @var MockClient
     */
    private $mock;
    /**
     * @var Client
     */
    private $client;

    protected function setUp()
    {
        parent::setUp();
        $this->mock = new MockClient();
        $this->client = new Client($this->mock);
    }

    /**
     * @dataProvider providesResponses
     *
     * @param ResponseInterface $response
     *
     * @throws Exception
     */
    public function testHandlesAllResponseTypesWithoutThrowingExceptions(ResponseInterface $response): void
    {
        $this->mock->addResponse($response);
        $this->assertEquals(
            $response->getStatusCode(),
            $this->client->sendNow($this->getNotification())->getStatusCode()
        );
    }

    public function providesResponses(): array
    {
        return [
            [new Response(201)],
            [new Response(302)],
            [new Response(400)],
            [new Response(404)],
            [new Response(500)]
        ];
    }

    private function getNotification(): Notification
    {
        $subscription = $this->getSubscription();
        return new Notification(
            $subscription,
            $this->getPayload($subscription),
            $this->getOptions(),
            $this->getAuthorization()
        );
    }
}
