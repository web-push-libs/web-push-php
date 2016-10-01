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
    private static $testSuiteId;
    private static $gcmSenderId = "653317226796";
    private static $gcmApiKey = "AIzaSyBBh4ddPa96rQQNxqiq_qQj7sq1JdsNQUQ";

    /** @var WebPush WebPush with correct api keys */
    private $webPush;

    public static function setUpBeforeClass()
    {
        $curl = curl_init("http://localhost:8090/api/start-test-suite/");
        curl_setopt_array($curl, array(
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => true
        ));
        $resp = curl_exec($curl);
        $parsedResp = json_decode($resp);
        self::$testSuiteId = $parsedResp->{'data'}->{'testSuiteId'};
        curl_close($curl);
    }

    protected function setUp()
    {
        $this->webPush = new WebPush(array('GCM' => self::$gcmApiKey));
        $this->webPush->setAutomaticPadding(false);
    }

    public function testChromeStable()
    {
        $dataString = json_encode(array(
            "testSuiteId" => self::$testSuiteId,
            "browserName" => "chrome",
            "browserVersion" => "stable",
            "gcmSenderId" => self::$gcmSenderId
        ));
        $curl = curl_init("http://localhost:8090/api/get-subscription/");
        curl_setopt_array($curl, array(
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $dataString,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Content-Length: " . strlen($dataString)
            )
        ));
        $resp = curl_exec($curl);
        $parsedResp = json_decode($resp);

        $testId = $parsedResp->{'data'}->{'testId'};
        $subscription = $parsedResp->{'data'}->{'subscription'};
        $endpoint = $subscription->{'endpoint'};
        $keys = $subscription->{'keys'};
        $auth = $keys->{'auth'};
        $p256dh = $keys->{'p256dh'};

        // Close request to clear up some resources
        curl_close($curl);

        $payload = 'hello';
        $sendResp = $this->webPush->sendNotification($endpoint, $payload, $p256dh, $auth, true);
        $this->assertTrue($sendResp);

        $dataString = json_encode(array(
            "testSuiteId" => self::$testSuiteId,
            "testId" => $testId
        ));
        $curl = curl_init("http://localhost:8090/api/get-notification-status/");
        curl_setopt_array($curl, array(
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $dataString,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Content-Length: " . strlen($dataString)
            )
        ));
        $resp = curl_exec($curl);
        $parsedResp = json_decode($resp);

        $messages = $parsedResp->{'data'}->{'messages'};
        $this->assertEquals(count($messages), 1);
        $this->assertEquals($messages[0], $payload);
    }

    protected function tearDown()
    {
        fwrite(STDOUT, "tearDown()\n");
    }

    public static function tearDownAfterClass()
    {
        $dataString = "{ \"testSuiteId\": " . self::$testSuiteId . " }";
        $curl = curl_init("http://localhost:8090/api/end-test-suite/");
        curl_setopt_array($curl, array(
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $dataString,
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
}
