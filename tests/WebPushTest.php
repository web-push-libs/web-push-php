<?php declare(strict_types=1);
/*
 * This file is part of the WebPush library.
 *
 * (c) Louis Lagrange <lagrange.louis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\SubscriptionInterface;
use Minishlink\WebPush\WebPush;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(WebPush::class)]
final class WebPushTest extends PHPUnit\Framework\TestCase
{
    private static array $endpoints;
    private static array $keys;
    private static array $tokens;
    private static array $vapidKeys;

    /** @var WebPush WebPush with correct api keys */
    private WebPush $webPush;

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

        self::$vapidKeys = [
            'publicKey'     => getenv('VAPID_PUBLIC_KEY'),
            'privateKey'    => getenv('VAPID_PRIVATE_KEY'),
        ];

        if (getenv('CI')) {
            self::setCiEnvironment();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setUp(): void
    {
        if (!getenv('CI')) {
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
        }

        $this->webPush = new WebPush([
            'VAPID' => [
                'subject' => 'https://github.com/Minishlink/web-push',
                'publicKey' => self::$vapidKeys['publicKey'],
                'privateKey' => self::$vapidKeys['privateKey'],
            ],
        ]);
        $this->webPush->setAutomaticPadding(false); // disable automatic padding in tests to speed these up
    }

    private static function setCiEnvironment(): void
    {
        self::$vapidKeys['publicKey'] = PushServiceTest::$vapidKeys['publicKey'];
        self::$vapidKeys['privateKey'] = PushServiceTest::$vapidKeys['privateKey'];
        $subscriptionParameters = [
            'applicationServerKey' => self::$vapidKeys['publicKey'],
        ];

        $subscriptionParameters = json_encode($subscriptionParameters, JSON_THROW_ON_ERROR);

        $getSubscriptionCurl = curl_init('http://localhost:9012/subscribe');
        curl_setopt_array($getSubscriptionCurl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $subscriptionParameters,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($subscriptionParameters),
            ],
        ]);

        $response = curl_exec($getSubscriptionCurl);

        if (!$response) {
            $error = 'Curl error: n'.curl_errno($getSubscriptionCurl).' - '.curl_error($getSubscriptionCurl);
            curl_close($getSubscriptionCurl);
            throw new RuntimeException($error);
        }

        $parsedResp = json_decode($response, null, 512, JSON_THROW_ON_ERROR);

        $subscription = $parsedResp->{'data'};

        self::$endpoints['standard'] = $subscription->{'endpoint'};
        $keys = $subscription->{'keys'};
        self::$tokens['standard'] = $keys->{'auth'};
        self::$keys['standard'] = $keys->{'p256dh'};
    }

    /**
     * @throws ErrorException
     */
    public static function notificationProvider(): array
    {
        self::setUpBeforeClass(); // dirty hack of PHPUnit limitation

        return [
            [new Subscription(self::$endpoints['standard'] ?: '', self::$keys['standard'] ?: '', self::$tokens['standard'] ?: ''), '{"message":"Comment ça va ?","tag":"general"}'],
        ];
    }

    /**
     * @throws ErrorException
     */
    #[dataProvider('notificationProvider')]
    public function testSendOneNotification(SubscriptionInterface $subscription, string $payload): void
    {
        $report = $this->webPush->sendOneNotification($subscription, $payload);
        $this->assertTrue($report->isSuccess());
    }

    /**
     * @throws ErrorException
     */
    public function testSendNotificationBatch(): void
    {
        $batchSize = 10;
        $total = 50;

        $notifications = self::notificationProvider();
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
    public function testSendOneNotificationWithTooBigPayload(): void
    {
        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Size of payload must not be greater than 4078 octets.');

        $subscription = new Subscription(self::$endpoints['standard'], self::$keys['standard']);
        $this->webPush->sendOneNotification(
            $subscription,
            str_repeat('test', 1020)
        );
    }

    /**
     * @throws \ErrorException
     * @throws \JsonException
     */
    public function testFlush(): void
    {
        $subscription = new Subscription(self::$endpoints['standard']);

        $report = $this->webPush->sendOneNotification($subscription);
        $this->assertFalse($report->isSuccess()); // it doesn't have VAPID

        // queue has been reset
        $this->assertEmpty(iterator_to_array($this->webPush->flush()));

        $report = $this->webPush->sendOneNotification($subscription);
        $this->assertFalse($report->isSuccess());  // it doesn't have VAPID

        $nonExistentSubscription = Subscription::create([
            'endpoint'        => 'https://fcm.googleapis.com/fcm/send/fCd2-8nXJhU:APA91bGi2uaqFXGft4qdolwyRUcUPCL1XV_jWy1tpCRqnu4sk7ojUpC5gnq1PTncbCdMq9RCVQIIFIU9BjzScvjrDqpsI7J-K_3xYW8xo1xSNCfge1RvJ6Xs8RGL_Sw7JtbCyG1_EVgWDc22on1r_jozD8vsFbB0Fg',
            'publicKey'       => 'BME-1ZSAv2AyGjENQTzrXDj6vSnhAIdKso4n3NDY0lsd1DUgEzBw7ARMKjrYAm7JmJBPsilV5CWNH0mVPyJEt0Q',
            'authToken'       => 'hUIGbmiypj9_EQea8AnCKA',
            'contentEncoding' => 'aes128gcm',
        ]);

        // test multiple requests
        $this->webPush->queueNotification($nonExistentSubscription, json_encode(['test' => 1], JSON_THROW_ON_ERROR));
        $this->webPush->queueNotification($nonExistentSubscription, json_encode(['test' => 2], JSON_THROW_ON_ERROR));
        $this->webPush->queueNotification($nonExistentSubscription, json_encode(['test' => 3], JSON_THROW_ON_ERROR));

        /** @var \Minishlink\WebPush\MessageSentReport $report */
        foreach ($this->webPush->flush() as $report) {
            $this->assertFalse($report->isSuccess());
            $this->assertTrue($report->isSubscriptionExpired());
            $this->assertEquals(410, $report->getResponse()->getStatusCode());
            $this->assertNotEmpty($report->getReason());
            $this->assertNotFalse(filter_var($report->getEndpoint(), FILTER_VALIDATE_URL));
        }
    }

    /**
     * @throws \ErrorException
     * @throws \JsonException
     */
    public function testFlushPooled(): void
    {
        $subscription = new Subscription(self::$endpoints['standard']);

        $report = $this->webPush->sendOneNotification($subscription);
        $this->assertFalse($report->isSuccess()); // it doesn't have VAPID

        // queue has been reset
        $this->assertEmpty(iterator_to_array($this->webPush->flush()));

        $report = $this->webPush->sendOneNotification($subscription);
        $this->assertFalse($report->isSuccess());  // it doesn't have VAPID

        $nonExistentSubscription = Subscription::create([
            'endpoint'        => 'https://fcm.googleapis.com/fcm/send/fCd2-8nXJhU:APA91bGi2uaqFXGft4qdolwyRUcUPCL1XV_jWy1tpCRqnu4sk7ojUpC5gnq1PTncbCdMq9RCVQIIFIU9BjzScvjrDqpsI7J-K_3xYW8xo1xSNCfge1RvJ6Xs8RGL_Sw7JtbCyG1_EVgWDc22on1r_jozD8vsFbB0Fg',
            'publicKey'       => 'BME-1ZSAv2AyGjENQTzrXDj6vSnhAIdKso4n3NDY0lsd1DUgEzBw7ARMKjrYAm7JmJBPsilV5CWNH0mVPyJEt0Q',
            'authToken'       => 'hUIGbmiypj9_EQea8AnCKA',
            'contentEncoding' => 'aes128gcm',
        ]);

        // test multiple requests
        $this->webPush->queueNotification($nonExistentSubscription, json_encode(['test' => 1], JSON_THROW_ON_ERROR));
        $this->webPush->queueNotification($nonExistentSubscription, json_encode(['test' => 2], JSON_THROW_ON_ERROR));
        $this->webPush->queueNotification($nonExistentSubscription, json_encode(['test' => 3], JSON_THROW_ON_ERROR));

        $callback = function ($report) {
            $this->assertFalse($report->isSuccess());
            $this->assertTrue($report->isSubscriptionExpired());
            $this->assertEquals(410, $report->getResponse()->getStatusCode());
            $this->assertNotEmpty($report->getReason());
            $this->assertNotFalse(filter_var($report->getEndpoint(), FILTER_VALIDATE_URL));
        };

        $this->webPush->flushPooled($callback);
    }

    public function testFlushEmpty(): void
    {
        $this->assertEmpty(iterator_to_array($this->webPush->flush(300)));
    }

    /**
     * @throws ErrorException
     */
    public function testCount(): void
    {
        $subscription = new Subscription(self::$endpoints['standard']);

        $this->webPush->queueNotification($subscription);
        $this->webPush->queueNotification($subscription);
        $this->webPush->queueNotification($subscription);
        $this->webPush->queueNotification($subscription);

        $this->assertEquals(4, $this->webPush->countPendingNotifications());
    }
}
