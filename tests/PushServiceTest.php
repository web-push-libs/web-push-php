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
    private static $portNumber = 9012;
    private static $testSuiteId;
    private static $testServiceUrl;
    private static $gcmSenderId = "759071690750";
    private static $gcmApiKey = "AIzaSyBAU0VfXoskxUSg81K5VgLgwblHbZWe6tA";

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
        self::$testServiceUrl = "http://localhost:".self::$portNumber;
    }

    protected function setUp()
    {
      $startApiCurl = curl_init(self::$testServiceUrl."/api/start-test-suite/");
      curl_setopt_array($startApiCurl, array(
          CURLOPT_POST => true,
          CURLOPT_POSTFIELDS => array(),
          CURLOPT_RETURNTRANSFER => true,
      ));

      $resp = curl_exec($startApiCurl);

      if ($resp) {
        $parsedResp = json_decode($resp);
        self::$testSuiteId = $parsedResp->{'data'}->{'testSuiteId'};
      } else {
        echo "Curl error: ";
        echo curl_error($startApiCurl);

        throw new Exception('Unable to get a test suite from the '.
          'web-push-testing-service');
      }

      curl_close($startApiCurl);
    }

    public function browserProvider()
    {
        return array(
            // Web Push
            array("chrome", "stable", array()),
            array("chrome", "beta", array()),
            array("chrome", "unstable", array()),
            array("firefox", "stable", array()),
            array("firefox", "beta", array()),
            array("firefox", "unstable", array()),
            // Web Push + GCM
            array("chrome", "stable", array('GCM' => self::$gcmApiKey)),
            array("chrome", "beta", array('GCM' => self::$gcmApiKey)),
            array("chrome", "unstable", array('GCM' => self::$gcmApiKey)),
            array("firefox", "stable", array('GCM' => self::$gcmApiKey)),
            array("firefox", "beta", array('GCM' => self::$gcmApiKey)),
            array("firefox", "unstable", array('GCM' => self::$gcmApiKey)),
            // Web Push + VAPID
            // Web Push + GCM + VAPID
        );
    }

    /**
     * @dataProvider browserProvider
     * Run integration tests with browsers
     */
    public function testBrowsers($browserId, $browserVersion, $options)
    {
        $this->webPush = new WebPush($options);
        $this->webPush->setAutomaticPadding(false);

        $dataString = json_encode(array(
            "testSuiteId" => self::$testSuiteId,
            "browserName" => $browserId,
            "browserVersion" => $browserVersion,
            "gcmSenderId" => self::$gcmSenderId
        ));
        $getSubscriptionCurl = curl_init(self::$testServiceUrl."/api/get-subscription/");
        curl_setopt_array($getSubscriptionCurl, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $dataString,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Content-Length: " . strlen($dataString)
            )
        ));

        $resp = curl_exec($getSubscriptionCurl);

        // Close request to clear up some resources
        curl_close($getSubscriptionCurl);

        $parsedResp = json_decode($resp);

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
             "testSuiteId" => self::$testSuiteId,
             "testId" => $testId
          ));

          $getNotificationCurl = curl_init(self::$testServiceUrl."/api/get-notification-status/");
          curl_setopt_array($getNotificationCurl, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $dataString,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Content-Length: " . strlen($dataString)
            )
          ));
          $resp = curl_exec($getNotificationCurl);
          $parsedResp = json_decode($resp);

          $messages = $parsedResp->{'data'}->{'messages'};
          $this->assertEquals(count($messages), 1);
          $this->assertEquals($messages[0], $payload);
      } catch (Exception $e) {
        if (
          strpos($endpoint, 'https://android.googleapis.com/gcm/send') === 0 &&
          !array_key_exists('GCM', $options)
        ) {
          if ($e->getMessage() !== 'No GCM API Key specified.') {
            echo $e;
          }
          $this->assertEquals($e->getMessage(), 'No GCM API Key specified.');
        } else {
          if ($getNotificationCurl) {
            echo "Curl error: ";
            echo curl_error($getNotificationCurl);
          }
          throw $e;
        }
      }
    }

    protected function tearDown()
    {
      $dataString = "{ \"testSuiteId\": " . self::$testSuiteId . " }";
      $curl = curl_init(self::$testServiceUrl."/api/end-test-suite/");
      curl_setopt_array($curl, array(
          CURLOPT_POST => true,
          CURLOPT_POSTFIELDS => $dataString,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_HTTPHEADER => array(
              "Content-Type: application/json",
              "Content-Length: " . strlen($dataString)
          )
      ));
      $resp = curl_exec($curl);
      $parsedResp = json_decode($resp);

      self::$testSuiteId = null;
      // Close request to clear up some resources
      curl_close($curl);
    }

    public static function tearDownAfterClass()
    {
        $testingServiceResult = exec(
          "web-push-testing-service stop phpunit");
    }
}
