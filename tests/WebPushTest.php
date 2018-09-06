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
    public static function setUpBeforeClass()
    {
        self::$endpoints = [
            'standard' => getenv('STANDARD_ENDPOINT'),
            'GCM' => getenv('GCM_ENDPOINT'),
        ];

        self::$keys = [
            'standard' => getenv('USER_PUBLIC_KEY'),
            'GCM' => getenv('GCM_USER_PUBLIC_KEY'),
        ];

        self::$tokens = [
            'standard' => getenv('USER_AUTH_TOKEN'),
            'GCM' => getenv('GCM_USER_AUTH_TOKEN'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $envs = [
            'STANDARD_ENDPOINT',
            'GCM_ENDPOINT',
            'USER_PUBLIC_KEY',
            'GCM_API_KEY',
            'GCM_USER_PUBLIC_KEY',
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
            'GCM' => getenv('GCM_API_KEY'),
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
            [new Subscription(self::$endpoints['standard']), null],
            [new Subscription(self::$endpoints['standard'], self::$keys['standard'], self::$tokens['standard']), '{"message":"Comment ça va ?","tag":"general"}'],
            [new Subscription(self::$endpoints['GCM']), null],
            [new Subscription(self::$endpoints['GCM'], self::$keys['GCM'], self::$tokens['GCM']), '{"message":"Comment ça va ?","tag":"general"}'],
        ];
    }

    /**
     * @dataProvider notificationProvider
     *
     * @param Subscription $subscription
     * @param string $payload
     * @throws ErrorException
     */
    public function testSendNotification($subscription, $payload)
    {
        $res = $this->webPush->sendNotification($subscription, $payload, true);

        $this->assertTrue($res);
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
            $this->webPush->sendNotification($notification[0], $notification[1]);
        }

        $res = $this->webPush->flush($batchSize);

        $this->assertTrue($res);
    }

    /**
     * @throws ErrorException
     */
    public function testSendNotificationWithTooBigPayload()
    {
        $this->expectException('ErrorException');
        $this->expectExceptionMessage('Size of payload must not be greater than 4078 octets.');

        $subscription = new Subscription(self::$endpoints['standard'], self::$keys['standard']);
        $this->webPush->sendNotification(
            $subscription,
            str_repeat('test', 1020),
            true
        );
    }

    /**
     * @throws ErrorException
     */
    public function testFlush() {
	    $subscription = new Subscription(self::$endpoints['standard']);

	    $this->webPush->sendNotification($subscription);
	    $this->assertNotEmpty(iterator_to_array($this->webPush->flush()));

	    // queue has been reset
	    $this->assertEmpty(iterator_to_array($this->webPush->flush()));

	    $this->webPush->sendNotification($subscription);
	    $this->assertNotEmpty(iterator_to_array($this->webPush->flush()));

	    $sub = Subscription::create([
		    'endpoint'        => 'https://fcm.googleapis.com/fcm/send/fCd2-8nXJhU:APA91bGi2uaqFXGft4qdolwyRUcUPCL1XV_jWy1tpCRqnu4sk7ojUpC5gnq1PTncbCdMq9RCVQIIFIU9BjzScvjrDqpsI7J-K_3xYW8xo1xSNCfge1RvJ6Xs8RGL_Sw7JtbCyG1_EVgWDc22on1r_jozD8vsFbB0Fg',
		    'publicKey'       => 'BME-1ZSAv2AyGjENQTzrXDj6vSnhAIdKso4n3NDY0lsd1DUgEzBw7ARMKjrYAm7JmJBPsilV5CWNH0mVPyJEt0Q',
		    'authToken'       => 'hUIGbmiypj9_EQea8AnCKA',
		    'contentEncoding' => 'aes128gcm',
	    ]);

	    // test multiple requests
	    $this->webPush->sendNotification($sub, json_encode(['test' => 1]));
	    $this->webPush->sendNotification($sub, json_encode(['test' => 2]));
	    $this->webPush->sendNotification($sub, json_encode(['test' => 3]));

	    /** @var \Minishlink\WebPush\MessageSentReport $report */
	    foreach ($this->webPush->flush() as $report) {
	    	$this->assertFalse($report->isSuccess());
	    	$this->assertFalse($report->isSubscriptionExpired());
	    	$this->assertEquals(404, $report->getResponse()->getStatusCode());
	    	$this->assertNotEmpty($report->getReason());
	    	$this->assertNotFalse(filter_var($report->getEndpoint(), FILTER_VALIDATE_URL));
	    }
    }

    /**
     * @throws ErrorException
     */
    public function testSendGCMNotificationWithoutGCMApiKey()
    {
        if (substr(self::$endpoints['GCM'], 0, strlen(WebPush::GCM_URL)) !== WebPush::GCM_URL) {
            $this->markTestSkipped("The provided GCM URL is not a GCM URL, but probably a FCM URL.");
        }

        $webPush = new WebPush();
        $this->expectException('ErrorException');
        $this->expectExceptionMessage('No GCM API Key specified.');

        $subscription = new Subscription(self::$endpoints['GCM']);
        $webPush->sendNotification($subscription, null, true);
    }

    /**
     * @throws ErrorException
     */
    public function testSendGCMNotificationWithWrongGCMApiKey()
    {
        if (substr(self::$endpoints['GCM'], 0, strlen(WebPush::GCM_URL)) !== WebPush::GCM_URL) {
            $this->markTestSkipped("The provided GCM URL is not a GCM URL, but probably a FCM URL.");
        }

        $webPush = new WebPush(['GCM' => 'bar']);

        $subscription = new Subscription(self::$endpoints['GCM']);
        $res = $webPush->sendNotification($subscription, null, true);

        $this->assertTrue(is_array($res)); // there has been an error
        $this->assertArrayHasKey('success', $res);
        $this->assertFalse($res['success']);

        $this->assertArrayHasKey('statusCode', $res);
        $this->assertEquals(400, $res['statusCode']);

        $this->assertArrayHasKey('headers', $res);

        $this->assertArrayHasKey('endpoint', $res);
        $this->assertEquals(self::$endpoints['GCM'], $res['endpoint']);
    }
}
