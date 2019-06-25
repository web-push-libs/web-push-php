<?php

declare(strict_types = 1);

namespace Minishlink\WebPush\Tests\Unit;

use ErrorException;
use GuzzleHttp\Psr7\Response;
use Http\Mock\Client as MockClient;
use Minishlink\WebPush\Client;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Tests\TestCase;
use Minishlink\WebPush\WebPush;

final class WebPushTest extends TestCase
{
    /**
     * @var WebPush
     */
    private $webpush;

    protected function setUp()
    {
        parent::setUp();
        $this->webpush = new WebPush($this->getAuthorization(), null, new Client($mock = new MockClient()));
        $mock->setDefaultResponse(new Response(200));
    }

    /**
     * @throws ErrorException
     */
    public function testCountsTheNumberOfQueuesNotifications(): void
    {
        $this->assertEquals(0, $this->webpush->countQueuedNotifications());

        $subscription = $this->getSubscription();
        $this->webpush->queueNotification($subscription)
            ->queueNotification($subscription)
            ->queueNotification($subscription)
            ->queueNotification($subscription);

        $this->assertEquals(4, $this->webpush->countQueuedNotifications());
    }

    /**
     * @throws ErrorException
     */
    public function testDeliversQueuedNotificationsInBatches(): void
    {
        $subscription = $this->getSubscription();
        $this->webpush->queueNotification($subscription)
            ->queueNotification($subscription)
            ->queueNotification($subscription)
            ->queueNotification($subscription);

        foreach ($this->webpush->deliver(2) as $report) {
            $this->assertInstanceOf(MessageSentReport::class, $report);
        }

        $this->assertEquals(0, $this->webpush->countQueuedNotifications());
    }

    /**
     * @throws ErrorException
     */
    public function testDeliversLeftOverQueuedNotificationsIfTheBatchSizeIsNotReached(): void
    {
        $subscription = $this->getSubscription();
        $this->webpush->queueNotification($subscription);

        foreach ($this->webpush->deliver(10) as $report) {
            $this->assertInstanceOf(MessageSentReport::class, $report);
        }

        $this->assertEquals(0, $this->webpush->countQueuedNotifications());
    }

    public function testGeneratorWillBeIterableZeroTimesIfQueueIsEmpty(): void
    {
        foreach ($this->webpush->deliver() as $report) {
            $this->fail('Should not be iterated over');
        }

        $this->assertFalse(isset($report));
    }

    /**
     * @throws ErrorException
     */
    public function testPayloadCannotExceedAcceptableLength(): void
    {
        $this->expectExceptionMessage('Size of payload must not be greater than 4078 octets.');
        $this->webpush->queueNotification($this->getSubscription(), str_repeat('a', 4079));
    }
}