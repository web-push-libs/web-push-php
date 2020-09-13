<?php

declare(strict_types=1);

/*
 * This file is part of the WebPush library.
 *
 * (c) Louis Lagrange <lagrange.louis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\SubscriptionInterface;

final class WebPushTest extends PHPUnit\Framework\TestCase
{
    private static $endpoints;
    private static $keys;
    private static $tokens;

    /** @var WebPush WebPush with correct api keys */
    private $webPush;

    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass(): void
    {
        self::$endpoints = [
            'standard' => getenv('STANDARD_ENDPOINT'),
        ];

        self::$keys = [
            'standard' => getenv('USER_PUBLIC_KEY'),
        ];

        self::$tokens = [
            'standard' => getenv('USER_AUTH_TOKEN'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function setUp(): void
    {
        $envs = [
            'STANDARD_ENDPOINT',
            'USER_PUBLIC_KEY',
            'USER_AUTH_TOKEN',
            'VAPID_PUBLIC_KEY',
            'VAPID_PRIVATE_KEY',
        ];
        foreach ($envs as $env) {
            if (!getenv($env)) {
                $this->markTestSkipped("No '$env' found in env.");
            }
        }

        $this->webPush = new WebPush([
            'VAPID' => [
                'subject' => 'https://github.com/Minishlink/web-push',
                'publicKey' => getenv('VAPID_PUBLIC_KEY'),
                'privateKey' => getenv('VAPID_PRIVATE_KEY'),
            ],
        ]);
        $this->webPush->setAutomaticPadding(false); // disable automatic padding in tests to speed these up
    }

    /**
     * @return array
     * @throws ErrorException
     */
    public function notificationProvider(): array
    {
        self::setUpBeforeClass(); // dirty hack of PHPUnit limitation

        // ignore in TravisCI
        if (getenv('CI')) return [];

        return [
            [new Subscription(self::$endpoints['standard'], self::$keys['standard'], self::$tokens['standard']), '{"message":"Comment ça va ?","tag":"general"}'],
        ];
    }

    /**
     * @dataProvider notificationProvider
     *
     * @param SubscriptionInterface $subscription
     * @param string $payload
     * @throws ErrorException
     */
    public function testSendOneNotification($subscription, $payload)
    {
        $report = $this->webPush->sendOneNotification($subscription, $payload);
        $this->assertTrue($report->isSuccess());
    }

    /**
     * @throws ErrorException
     */
    public function testSendNotificationBatch()
    {
        $batchSize = 10;
        $total = 50;

        $notifications = $this->notificationProvider();
        $notifications = array_fill(0, $total, $notifications[0]);

        foreach ($notifications as $notification) {
            $this->webPush->queueNotification($notification[0], $notification[1]);
        }

        $reports = $this->webPush->flush($batchSize);

        foreach ($reports as $report) {
            $this->assertTrue($report->isSuccess());
        }
    }

    /**
     * @throws ErrorException
     */
    public function testSendOneNotificationWithTooBigPayload()
    {
        $this->expectException('ErrorException');
        $this->expectExceptionMessage('Size of payload must not be greater than 4078 octets.');

        $subscription = new Subscription(self::$endpoints['standard'], self::$keys['standard']);
        $this->webPush->sendOneNotification(
            $subscription,
            str_repeat('test', 1020)
        );
    }

    /**
     * @throws ErrorException
     */
    public function testFlush() {
	    $subscription = new Subscription(self::$endpoints['standard']);

	    $report = $this->webPush->sendOneNotification($subscription);
	    $this->assertFalse($report->isSuccess()); // it doesn't have VAPID

	    // queue has been reset
	    $this->assertEmpty(iterator_to_array($this->webPush->flush()));

        $report = $this->webPush->sendOneNotification($subscription);
        $this->assertFalse($report->isSuccess());  // it doesn't have VAPID

	    $nonExistantSubscription = Subscription::create([
		    'endpoint'        => 'https://fcm.googleapis.com/fcm/send/fCd2-8nXJhU:APA91bGi2uaqFXGft4qdolwyRUcUPCL1XV_jWy1tpCRqnu4sk7ojUpC5gnq1PTncbCdMq9RCVQIIFIU9BjzScvjrDqpsI7J-K_3xYW8xo1xSNCfge1RvJ6Xs8RGL_Sw7JtbCyG1_EVgWDc22on1r_jozD8vsFbB0Fg',
		    'publicKey'       => 'BME-1ZSAv2AyGjENQTzrXDj6vSnhAIdKso4n3NDY0lsd1DUgEzBw7ARMKjrYAm7JmJBPsilV5CWNH0mVPyJEt0Q',
		    'authToken'       => 'hUIGbmiypj9_EQea8AnCKA',
		    'contentEncoding' => 'aes128gcm',
	    ]);

	    // test multiple requests
	    $this->webPush->queueNotification($nonExistantSubscription, json_encode(['test' => 1]));
	    $this->webPush->queueNotification($nonExistantSubscription, json_encode(['test' => 2]));
	    $this->webPush->queueNotification($nonExistantSubscription, json_encode(['test' => 3]));

	    /** @var \Minishlink\WebPush\MessageSentReport $report */
	    foreach ($this->webPush->flush() as $report) {
	    	$this->assertFalse($report->isSuccess());
	    	$this->assertTrue($report->isSubscriptionExpired());
	    	$this->assertEquals(410, $report->getResponse()->getStatusCode());
	    	$this->assertNotEmpty($report->getReason());
	    	$this->assertNotFalse(filter_var($report->getEndpoint(), FILTER_VALIDATE_URL));
	    }
    }

	public function testFlushEmpty(): void {
        $this->assertEmpty(iterator_to_array($this->webPush->flush(300)));
    }

	/**
	 * @throws ErrorException
	 */
	public function testCount(): void {
		$subscription = new Subscription(self::$endpoints['standard']);

		$this->webPush->queueNotification($subscription);
		$this->webPush->queueNotification($subscription);
		$this->webPush->queueNotification($subscription);
		$this->webPush->queueNotification($subscription);

		$this->assertEquals(4, $this->webPush->countPendingNotifications());
    }
}
