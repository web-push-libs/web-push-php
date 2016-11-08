<?php

/*
 * This file is part of the WebPush library.
 *
 * (c) Louis Lagrange <lagrange.louis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Minishlink\WebPush\WebPush;

class PushServiceTest extends PHPUnit_Framework_TestCase
{
    private static $timeout = 30;
    private static $portNumber = 9012;
    private static $testSuiteId;
    private static $testServiceUrl;
    private static $gcmSenderId = '759071690750';
    private static $gcmApiKey = 'AIzaSyBAU0VfXoskxUSg81K5VgLgwblHbZWe6tA';
    private static $vapidKeys = array(
        'subject' => 'http://test.com',
        'publicKey' => 'BA6jvk34k6YjElHQ6S0oZwmrsqHdCNajxcod6KJnI77Dagikfb--O_kYXcR2eflRz6l3PcI2r8fPCH3BElLQHDk',
        'privateKey' => '-3CdhFOqjzixgAbUSa0Zv9zi-dwDVmWO7672aBxSFPQ',
    );

    /** @var WebPush WebPush with correct api keys */
    private $webPush;

    /**
     * This check forces these tests to only run on Travis.
     * If we can reliably start and stop web-push-testing-service and
     * detect current OS, we can probably run this automatically
     * for Linux and OS X at a later date.
     */
    protected function checkRequirements()
    {
        parent::checkRequirements();

        if (!(getenv('TRAVIS') || getenv('CI'))) {
            $this->markTestSkipped('This test does not run on Travis.');
        }
    }

    public static function setUpBeforeClass()
    {
        self::$testServiceUrl = 'http://localhost:'.self::$portNumber;
    }

    protected function setUp()
    {
        $startApiCurl = curl_init(self::$testServiceUrl.'/api/start-test-suite/');
        curl_setopt_array($startApiCurl, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => array(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::$timeout,
        ));

        $parsedResp = $this->getResponse($startApiCurl);
        self::$testSuiteId = $parsedResp->{'data'}->{'testSuiteId'};
    }

    public function browserProvider()
    {
        return array(
            // Web Push
            array('firefox', 'stable', array()),
            array('firefox', 'beta', array()),
            array('firefox', 'unstable', array()),
            // Web Push + GCM
            array('chrome', 'stable', array('GCM' => self::$gcmApiKey)),
            array('chrome', 'beta', array('GCM' => self::$gcmApiKey)),
            array('chrome', 'unstable', array('GCM' => self::$gcmApiKey)),
            array('firefox', 'stable', array('GCM' => self::$gcmApiKey)),
            array('firefox', 'beta', array('GCM' => self::$gcmApiKey)),
            array('firefox', 'unstable', array('GCM' => self::$gcmApiKey)),
            // Web Push + VAPID
            array('chrome', 'stable', array('VAPID' => self::$vapidKeys)),
            array('chrome', 'beta', array('VAPID' => self::$vapidKeys)),
            array('chrome', 'unstable', array('VAPID' => self::$vapidKeys)),
            array('firefox', 'stable', array('VAPID' => self::$vapidKeys)),
            array('firefox', 'beta', array('VAPID' => self::$vapidKeys)),
            array('firefox', 'unstable', array('VAPID' => self::$vapidKeys)),
            // Web Push + GCM + VAPID
            array('chrome', 'stable', array('GCM' => self::$gcmApiKey, 'VAPID' => self::$vapidKeys)),
            array('chrome', 'beta', array('GCM' => self::$gcmApiKey, 'VAPID' => self::$vapidKeys)),
            array('chrome', 'unstable', array('GCM' => self::$gcmApiKey, 'VAPID' => self::$vapidKeys)),
            array('firefox', 'stable', array('GCM' => self::$gcmApiKey, 'VAPID' => self::$vapidKeys)),
            array('firefox', 'beta', array('GCM' => self::$gcmApiKey, 'VAPID' => self::$vapidKeys)),
            array('firefox', 'unstable', array('GCM' => self::$gcmApiKey, 'VAPID' => self::$vapidKeys)),
        );
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
    public function testBrowsers($browserId, $browserVersion, $options)
    {
        $this->retryTest(4, $this->createClosureTest($browserId, $browserVersion, $options));
    }

    protected function createClosureTest($browserId, $browserVersion, $options)
    {
        return function () use ($browserId, $browserVersion, $options) {
            $this->webPush = new WebPush($options);
            $this->webPush->setAutomaticPadding(false);

            $subscriptionParameters = array(
                'testSuiteId' => self::$testSuiteId,
                'browserName' => $browserId,
                'browserVersion' => $browserVersion,
            );

            if (array_key_exists('GCM', $options)) {
                $subscriptionParameters['gcmSenderId'] = self::$gcmSenderId;
            }

            if (array_key_exists('VAPID', $options)) {
                $subscriptionParameters['vapidPublicKey'] = self::$vapidKeys['publicKey'];
            }

            $subscriptionParameters = json_encode($subscriptionParameters);

            $getSubscriptionCurl = curl_init(self::$testServiceUrl.'/api/get-subscription/');
            curl_setopt_array($getSubscriptionCurl, array(
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $subscriptionParameters,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Content-Length: '.strlen($subscriptionParameters),
                ),
                CURLOPT_TIMEOUT => self::$timeout,
            ));

            $parsedResp = $this->getResponse($getSubscriptionCurl);
            $testId = $parsedResp->{'data'}->{'testId'};
            $subscription = $parsedResp->{'data'}->{'subscription'};
            $endpoint = $subscription->{'endpoint'};
            $keys = $subscription->{'keys'};
            $auth = $keys->{'auth'};
            $p256dh = $keys->{'p256dh'};

            $payload = 'hello';
            $getNotificationCurl = null;
            try {
                $sendResp = $this->webPush->sendNotification($endpoint, $payload, $p256dh, $auth, true);
                $this->assertTrue($sendResp);

                $dataString = json_encode(array(
                    'testSuiteId' => self::$testSuiteId,
                    'testId' => $testId,
                ));

                $getNotificationCurl = curl_init(self::$testServiceUrl.'/api/get-notification-status/');
                curl_setopt_array($getNotificationCurl, array(
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $dataString,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => array(
                        'Content-Type: application/json',
                        'Content-Length: '.strlen($dataString),
                    ),
                    CURLOPT_TIMEOUT => self::$timeout,
                ));

                $parsedResp = $this->getResponse($getSubscriptionCurl);

                if (!property_exists($parsedResp->{'data'}, 'messages')) {
                    throw new Exception('web-push-testing-service error, no messages: '.json_encode($parsedResp));
                }

                $messages = $parsedResp->{'data'}->{'messages'};
                $this->assertEquals(count($messages), 1);
                $this->assertEquals($messages[0], $payload);
            } catch (Exception $e) {
                if (strpos($endpoint, 'https://android.googleapis.com/gcm/send') === 0
                    && !array_key_exists('GCM', $options)) {
                    if ($e->getMessage() !== 'No GCM API Key specified.') {
                        echo $e;
                    }
                    $this->assertEquals($e->getMessage(), 'No GCM API Key specified.');
                }
            }
        };
    }

    protected function tearDown()
    {
        $dataString = '{ "testSuiteId": '.self::$testSuiteId.' }';
        $curl = curl_init(self::$testServiceUrl.'/api/end-test-suite/');
        curl_setopt_array($curl, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $dataString,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Content-Length: '.strlen($dataString),
            ),
            CURLOPT_TIMEOUT => self::$timeout,
        ));
        $this->getResponse($curl);
        self::$testSuiteId = null;
    }

    public static function tearDownAfterClass()
    {
        exec('web-push-testing-service stop phpunit');
    }

    private function getResponse($ch)
    {
        $resp = curl_exec($ch);

        if (!$resp) {
            $error = 'Curl error: n'.curl_errno($ch).' - '.curl_error($ch);
            curl_close($ch);
            throw new Exception($error);
        }

        $parsedResp = json_decode($resp);

        if (!property_exists($parsedResp, 'data')) {
            throw new Exception('web-push-testing-service error: '.$resp);
        }

        // Close request to clear up some resources
        curl_close($ch);

        return $parsedResp;
    }
}
