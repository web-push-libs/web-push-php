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

final class PushServiceTest extends PHPUnit\Framework\TestCase
{
    private static $timeout = 30;
    private static $portNumber = 9012;
    private static $testServiceUrl;
    public static $vapidKeys = [
        'subject' => 'http://test.com',
        'publicKey' => 'BA6jvk34k6YjElHQ6S0oZwmrsqHdCNajxcod6KJnI77Dagikfb--O_kYXcR2eflRz6l3PcI2r8fPCH3BElLQHDk',
        'privateKey' => '-3CdhFOqjzixgAbUSa0Zv9zi-dwDVmWO7672aBxSFPQ',
    ];

    /** @var WebPush WebPush with correct api keys */
    private $webPush;

    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass(): void
    {
        self::$testServiceUrl = 'http://localhost:'.self::$portNumber;
    }

    public function browserProvider()
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
    public function retryTest($retryCount, $test)
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
     * @dataProvider browserProvider
     * Run integration tests with browsers
     */
    public function testBrowsers($browserId, $options)
    {
        $this->retryTest(2, $this->createClosureTest($browserId, $options));
    }

    protected function createClosureTest($browserId, $options)
    {
        return function () use ($browserId, $options) {
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
                if (!in_array($contentEncoding, ['aesgcm', 'aes128gcm'])) {
                    $this->expectException(\ErrorException::class);
                    $this->expectExceptionMessage('This content encoding ('.$contentEncoding.') is not supported.');
                    $this->markTestIncomplete('Unsupported content encoding: '.$contentEncoding);
                }

                $subscription = new Subscription($endpoint, $p256dh, $auth, $contentEncoding);
                $report = $this->webPush->sendOneNotification($subscription, $payload);
                $this->assertInstanceOf(\Minishlink\WebPush\MessageSentReport::class, $report);
                $this->assertTrue($report->isSuccess());

                $dataString = json_encode([
                    'clientHash' => $clientHash,
                ]);

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
                    throw new Exception('web-push-testing error, no messages: '.json_encode($parsedResp));
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
            curl_close($ch);
            throw new Exception($error);
        }

        $parsedResp = json_decode($resp, null, 512, JSON_THROW_ON_ERROR);

        if (!property_exists($parsedResp, 'data')) {
            throw new Exception('web-push-testing-service error: '.$resp);
        }

        // Close request to clear up some resources
        curl_close($ch);

        return $parsedResp;
    }
}
