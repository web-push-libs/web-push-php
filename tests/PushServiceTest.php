<?php declare(strict_types=1);
/*
 * This file is part of the WebPush library.
 *
 * (c) Louis Lagrange <lagrange.louis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test with test server.
 */
#[group('online')]
#[CoversNothing]
final class PushServiceTest extends PHPUnit\Framework\TestCase
{
    private static int    $timeout    = 30;
    private static int    $portNumber = 9012;
    private static string $testServiceUrl;
    public static array   $vapidKeys  = [
        'subject' => 'http://test.com',
        'publicKey' => 'BA6jvk34k6YjElHQ6S0oZwmrsqHdCNajxcod6KJnI77Dagikfb--O_kYXcR2eflRz6l3PcI2r8fPCH3BElLQHDk',
        'privateKey' => '-3CdhFOqjzixgAbUSa0Zv9zi-dwDVmWO7672aBxSFPQ',
    ];

    /** @var WebPush WebPush with correct api keys */
    private WebPush $webPush;

    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass(): void
    {
        self::$testServiceUrl = 'http://localhost:'.self::$portNumber;
    }

    public static function browserProvider(): array
    {
        return [
            ['firefox', ['VAPID' => self::$vapidKeys]],
            ['chrome', ['VAPID' => self::$vapidKeys]],
            ['firefox', []],
            ['chrome', []],
        ];
    }

    /**
     * Selenium tests are flakey so add retries.
     */
    public function retryTest($retryCount, $test): void
    {
        // just like above without checking the annotation
        for ($i = 0; $i < $retryCount; $i++) {
            try {
                $test();

                return;
            } catch (Exception $e) {
                // last one thrown below
            }
        }
        if (isset($e)) {
            throw $e;
        }
    }

    /**
     * Run integration tests with browsers
     */
    #[dataProvider('browserProvider')]
    public function testBrowsers($browserId, $options): void
    {
        $this->retryTest(2, $this->createClosureTest($browserId, $options));
    }

    protected function createClosureTest($browserId, $options): callable
    {
        return function () use ($browserId, $options): void {
            $this->webPush = new WebPush($options);
            $this->webPush->setAutomaticPadding(false);
            $subscriptionParameters = [];

            if (array_key_exists('VAPID', $options)) {
                $subscriptionParameters['applicationServerKey'] = self::$vapidKeys['publicKey'];
            }

            $subscriptionParameters = json_encode($subscriptionParameters, JSON_THROW_ON_ERROR);

            $getSubscriptionCurl = curl_init(self::$testServiceUrl.'/subscribe');
            curl_setopt_array($getSubscriptionCurl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $subscriptionParameters,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Content-Length: '.strlen($subscriptionParameters),
                ],
                CURLOPT_TIMEOUT => self::$timeout,
            ]);

            $parsedResp = $this->getResponse($getSubscriptionCurl);
            $subscription = $parsedResp->{'data'};

            $supportedContentEncodings = ['aesgcm', 'aes128gcm'];

            $endpoint = $subscription->{'endpoint'};
            $keys = $subscription->{'keys'};
            $auth = $keys->{'auth'};
            $p256dh = $keys->{'p256dh'};
            $clientHash = $subscription->{'clientHash'};
            $payload = 'hello';
            $messageIndex = 0;

            foreach ($supportedContentEncodings as $contentEncoding) {
                if (!in_array($contentEncoding, ['aesgcm', 'aes128gcm'], true)) {
                    $this->expectException(ErrorException::class);
                    $this->expectExceptionMessage('This content encoding ('.$contentEncoding.') is not supported.');
                    $this->markTestIncomplete('Unsupported content encoding: '.$contentEncoding);
                }

                $subscription = new Subscription($endpoint, $p256dh, $auth, $contentEncoding);
                $report = $this->webPush->sendOneNotification($subscription, $payload);
                $this->assertInstanceOf(MessageSentReport::class, $report);
                $this->assertTrue($report->isSuccess());

                $dataString = json_encode([
                                              'clientHash' => $clientHash,
                                          ], JSON_THROW_ON_ERROR);

                $getNotificationCurl = curl_init(self::$testServiceUrl.'/get-notifications');
                curl_setopt_array($getNotificationCurl, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $dataString,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Content-Length: '.strlen($dataString),
                    ],
                    CURLOPT_TIMEOUT => self::$timeout,
                ]);

                $parsedResp = $this->getResponse($getNotificationCurl);

                if (!property_exists($parsedResp->{'data'}, 'messages')) {
                    throw new RuntimeException('web-push-testing error, no messages: '.json_encode($parsedResp, JSON_THROW_ON_ERROR));
                }

                $messages = $parsedResp->{'data'}->{'messages'};
                $this->assertEquals($payload, $messages[$messageIndex]);
                $this->assertCount(++$messageIndex, $messages);
            }
        };
    }

    private function getResponse($ch)
    {
        $resp = curl_exec($ch);

        if (!$resp) {
            $error = 'Curl error: n'.curl_errno($ch).' - '.curl_error($ch);
            throw new RuntimeException($error);
        }

        $parsedResp = json_decode($resp, null, 512, JSON_THROW_ON_ERROR);

        if (!property_exists($parsedResp, 'data')) {
            throw new RuntimeException('web-push-testing-service error: '.$resp);
        }

        return $parsedResp;
    }
}
