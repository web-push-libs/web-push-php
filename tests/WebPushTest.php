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

class WebPushTest extends PHPUnit_Framework_TestCase
{
    private static $endpoints;
    private static $keys;
    private static $tokens;

    /** @var WebPush WebPush with correct api keys */
    private $webPush;

    protected function checkRequirements()
    {
        parent::checkRequirements();

        if (getenv('TRAVIS') || getenv('CI')) {
            $this->markTestSkipped('This test does not run on Travis.');
        }

        if (!getenv('STANDARD_ENDPOINT')) {
            $this->markTestSkipped('No \'STANDARD_ENDPOINT\' found in env.');
        }

        if (!getenv('GCM_ENDPOINT')) {
            $this->markTestSkipped('No \'GCM_ENDPOINT\' found in env.');
        }

        if (!getenv('USER_PUBLIC_KEY')) {
            $this->markTestSkipped('No \'USER_PUBLIC_KEY\' found in env.');
        }

        if (!getenv('GCM_USER_PUBLIC_KEY')) {
            $this->markTestSkipped('No \'GCM_USER_PUBLIC_KEY\' found in env.');
        }

        if (!getenv('USER_AUTH_TOKEN')) {
            $this->markTestSkipped('No \'USER_PUBLIC_KEY\' found in env.');
        }

        if (!getenv('GCM_USER_AUTH_TOKEN')) {
            $this->markTestSkipped('No \'GCM_USER_AUTH_TOKEN\' found in env.');
        }

        if (!getenv('VAPID_PUBLIC_KEY')) {
            $this->markTestSkipped('No \'VAPID_PUBLIC_KEY\' found in env.');
        }

        if (!getenv('VAPID_PRIVATE_KEY')) {
            $this->markTestSkipped('No \'VAPID_PRIVATE_KEY\' found in env.');
        }
    }

    public static function setUpBeforeClass()
    {
        self::$endpoints = array(
            'standard' => getenv('STANDARD_ENDPOINT'),
            'GCM' => getenv('GCM_ENDPOINT'),
        );

        self::$keys = array(
            'standard' => getenv('USER_PUBLIC_KEY'),
            'GCM' => getenv('GCM_USER_PUBLIC_KEY'),
        );

        self::$tokens = array(
            'standard' => getenv('USER_AUTH_TOKEN'),
            'GCM' => getenv('GCM_USER_AUTH_TOKEN'),
        );
    }

    public function setUp()
    {
        $this->webPush = new WebPush(array(
            'GCM' => getenv('GCM_API_KEY'),
            'VAPID' => array(
                'subject' => 'https://github.com/Minishlink/web-push',
                'publicKey' => getenv('VAPID_PUBLIC_KEY'),
                'privateKey' => getenv('VAPID_PRIVATE_KEY'),
            ),
        ));
        $this->webPush->setAutomaticPadding(false); // disable automatic padding in tests to speed these up
    }

    public function notificationProvider()
    {
        self::setUpBeforeClass(); // dirty hack of PHPUnit limitation
        return array(
            array(self::$endpoints['standard'], null, null, null),
            array(self::$endpoints['standard'], '{"message":"Comment ça va ?","tag":"general"}', self::$keys['standard'], self::$tokens['standard']),
            array(self::$endpoints['GCM'], null, null, null),
            array(self::$endpoints['GCM'], '{"message":"Comment ça va ?","tag":"general"}', self::$keys['GCM'], self::$tokens['GCM']),
        );
    }

    /**
     * @dataProvider notificationProvider
     *
     * @param string $endpoint
     * @param string $payload
     * @param string $userPublicKey
     * @param string $userAuthKey
     */
    public function testSendNotification($endpoint, $payload, $userPublicKey, $userAuthKey)
    {
        $res = $this->webPush->sendNotification($endpoint, $payload, $userPublicKey, $userAuthKey, true);

        $this->assertTrue($res);
    }

    public function testSendNotificationBatch()
    {
        $batchSize = 10;
        $total = 50;

        $notifications = $this->notificationProvider();
        $notifications = array_fill(0, $total, $notifications[0]);

        foreach ($notifications as $notification) {
            $this->webPush->sendNotification($notification[0], $notification[1], $notification[2], $notification[3]);
        }

        $res = $this->webPush->flush($batchSize);

        $this->assertTrue($res);
    }

    public function testSendNotificationWithTooBigPayload()
    {
        $this->setExpectedException('ErrorException', 'Size of payload must not be greater than 4078 octets.');
        $this->webPush->sendNotification(
            self::$endpoints['standard'],
            str_repeat('test', 1020),
            self::$keys['standard'],
            null,
            true
        );
    }

    public function testFlush()
    {
        $this->webPush->sendNotification(self::$endpoints['standard']);
        $this->assertTrue($this->webPush->flush());

        // queue has been reset
        $this->assertFalse($this->webPush->flush());

        $this->webPush->sendNotification(self::$endpoints['standard']);
        $this->assertTrue($this->webPush->flush());
    }

    public function testSendGCMNotificationWithoutGCMApiKey()
    {
        if (substr(self::$endpoints['GCM'], 0, strlen(WebPush::GCM_URL)) !== WebPush::GCM_URL) {
            $this->markTestSkipped('The provided GCM URL is not a GCM URL, but probably a FCM URL.');
        }

        $webPush = new WebPush();
        $this->setExpectedException('ErrorException', 'No GCM API Key specified.');
        $webPush->sendNotification(self::$endpoints['GCM'], null, null, null, true);
    }

    public function testSendGCMNotificationWithWrongGCMApiKey()
    {
        if (substr(self::$endpoints['GCM'], 0, strlen(WebPush::GCM_URL)) !== WebPush::GCM_URL) {
            $this->markTestSkipped('The provided GCM URL is not a GCM URL, but probably a FCM URL.');
        }

        $webPush = new WebPush(array('GCM' => 'bar'));

        $res = $webPush->sendNotification(self::$endpoints['GCM'], null, null, null, true);

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
