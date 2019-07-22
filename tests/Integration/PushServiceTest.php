<?php

declare(strict_types = 1);

namespace WebPush\Tests\Integration;

/*
 * This file is part of the WebPush library.
 *
 * (c) Louis Lagrange <lagrange.louis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Base64Url\Base64Url;
use Exception;
use WebPush\Authorization;
use WebPush\Subscription;
use WebPush\Tests\TestCase;
use WebPush\WebPush;

final class PushServiceTest extends TestCase
{
    /**
     * @var Authorization
     */
    private $authorization;
    /**
     * @var string
     */
    private $testSuiteId;

    protected function setUp()
    {
        parent::setUp();
        $this->testSuiteId = $this->post('start-test-suite')['testSuiteId'];
        $this->authorization = $this->getAuthorization();
    }

    public function browserProvider(): array
    {
        return [
            ['chrome', 'stable', $this->getAuthorization()],
            ['chrome', 'beta', $this->getAuthorization()],

            ['firefox', 'stable', $this->getAuthorization()],
            ['firefox', 'beta', $this->getAuthorization()],
        ];
    }

    /**
     * @param int $attempts
     * @param callable $test
     *
     * @throws Exception
     */
    public function retry(int $attempts, callable $test): void
    {
        $attempt = 0;
        do {
            try {
                $exception = null;
                $test();
            } catch (Exception $exception) {

            }
        } while (++$attempt < $attempts);

        if (isset($exception)) {
            throw $exception;
        }
    }

    /**
     * @dataProvider browserProvider
     *
     * @param string $browser
     * @param string $channel
     * @param Authorization $authorization
     *
     * @throws Exception
     */
    public function testBrowserCompatibility(string $browser, string $channel, Authorization $authorization): void
    {
        $this->retry(1, function () use ($browser, $channel, $authorization) {
            $webpush = new WebPush($authorization);

            $response = $this->post('get-subscription', [
                'testSuiteId' => $this->testSuiteId,
                'browserName' => $browser,
                'browserVersion' => $channel,
                'vapidPublicKey' => Base64Url::encode($this->authorization->getPublicKey())
            ]);

            $test_id = $response['testId'];
            $endpoint = $response['subscription']['endpoint'];
            $auth_token = $response['subscription']['keys']['auth'];
            $public_key = $response['subscription']['keys']['p256dh'];
            $payload = 'hello';

            foreach ($subscription['supportedContentEncodings'] ?? ['aesgcm'] as $encoding) {
                $this->setEncodingExpectations($encoding);
                $subscription = new Subscription($endpoint, $public_key, $auth_token, $encoding);
                foreach ($webpush->queueNotification($subscription, $payload)->deliver() as $report) {
                    $this->assertTrue($report->isSuccess());
                }

                $status = $this->post('get-notification-status', [
                    'testSuiteId' => $this->testSuiteId,
                    'testId' => $test_id
                ]);

                if (array_key_exists('messages', $status) === false) {
                    throw new Exception('web-push-testing-service error, no messages: ' . json_encode($status));
                }

                $this->assertCount(1, $status['messages']);
                $this->assertEquals($payload, $status['messages'][0]);
            }
        });
    }

    /**
     * @param string $encoding
     */
    private function setEncodingExpectations(string $encoding): void
    {
        if (in_array($encoding, ['aesgcm', 'aes128gcm'], true) === false) {
            $this->expectException('ErrorException');
            $this->expectExceptionMessage('This content encoding is not supported.');
            $this->fail('Unsupported content encoding: ' . $encoding);
        }
    }

    /**
     * @param string $endpoint
     * @param array $parameters
     *
     * @return mixed
     * @throws Exception
     */
    private function post(string $endpoint, array $parameters = [])
    {
        $payload = json_encode($parameters);
        $curl = curl_init('http://localhost:9012/api/' . $endpoint);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload),
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5
        ]);

        $response = curl_exec($curl);
        if ($response === false) {
            throw new Exception('Curl error: n' . curl_errno($curl) . ' - ' . curl_error($curl));
        }

        $parsed = json_decode($response, true);
        if (!array_key_exists('data', $parsed)) {
            throw new Exception('web-push-testing-service error: ' . $response);
        }

        return $parsed['data'];
    }

    protected function tearDown()
    {
        $this->post('end-test-suite', ['testSuiteId' => $this->testSuiteId]);
        $this->testSuiteId = null;
    }
}
