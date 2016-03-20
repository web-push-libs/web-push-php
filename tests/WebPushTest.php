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
        $this->webPush = new WebPush(array('GCM' => getenv('GCM_API_KEY')));
        $this->webPush->setAutomaticPadding(false); // disable automatic padding in tests to speed these up
    }

    public function notificationProvider()
    {
        self::setUpBeforeClass(); // dirty hack of PHPUnit limitation
        return array(
            array(self::$endpoints['standard'], null, null, null),
            array(self::$endpoints['standard'], '{message: "Plop", tag: "general"}', self::$keys['standard'], self::$tokens['standard']),
            array(self::$endpoints['standard'], '{message: "Plop", tag: "general"}', self::$keys['standard'], null),
            array(self::$endpoints['GCM'], null, null, null),
            array(self::$endpoints['GCM'], '{message: "Plop", tag: "general"}', self::$keys['GCM'], self::$tokens['GCM']),
            array(self::$endpoints['GCM'], '{message: "Plop", tag: "general"}', self::$keys['GCM'], null),
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

    public function testSendNotificationWithOldAPI()
    {
        $this->setExpectedException('ErrorException', 'The API has changed: sendNotification now takes the optional user auth token as parameter.');
        $this->webPush->sendNotification(
            self::$endpoints['standard'],
            'test',
            self::$keys['standard'],
            true
        );
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
        $webPush = new WebPush();

        $this->setExpectedException('ErrorException', 'No GCM API Key specified.');
        $webPush->sendNotification(self::$endpoints['GCM'], null, null, null, true);
    }

    public function testSendGCMNotificationWithWrongGCMApiKey()
    {
        $webPush = new WebPush(array('GCM' => 'bar'));

        $res = $webPush->sendNotification(self::$endpoints['GCM'], null, null, null, true);

        $this->assertTrue(is_array($res)); // there has been an error
        $this->assertArrayHasKey('success', $res);
        $this->assertFalse($res['success']);

        $this->assertArrayHasKey('statusCode', $res);
        $this->assertEquals(401, $res['statusCode']);

        $this->assertArrayHasKey('headers', $res);
    }
}
