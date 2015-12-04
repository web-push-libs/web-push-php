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
    private $endpoints;
    private $keys;

    /** @var WebPush WebPush with correct api keys */
    private $webPush;

    public function setUp()
    {
        $this->endpoints = array(
            'standard' => getenv('STANDARD_ENDPOINT'),
            'GCM' => getenv('GCM_ENDPOINT'),
        );

        $this->keys = array(
            'standard' => getenv('USER_PUBLIC_KEY'),
            'GCM' => getenv('GCM_API_KEY'),
        );

        $this->webPush = new WebPush($this->keys);
    }

    public function testSendNotification()
    {
        $res = $this->webPush->sendNotification($this->endpoints['standard'], null, null, true);

        $this->assertEquals($res, true);
    }

    public function testSendNotificationWithPayload()
    {
        $res = $this->webPush->sendNotification(
            $this->endpoints['standard'],
            'test',
            $this->keys['standard'],
            true
        );

        $this->assertTrue($res);
    }

    public function testSendNotifications()
    {
        foreach($this->endpoints as $endpoint) {
            $this->webPush->sendNotification($endpoint);
        }

        $res = $this->webPush->flush();

        $this->assertEquals(true, $res);
    }

    public function testFlush()
    {
        $this->webPush->sendNotification($this->endpoints['standard']);
        $this->assertEquals(true, $this->webPush->flush());

        // queue has been reset
        $this->assertEquals(false, $this->webPush->flush());

        $this->webPush->sendNotification($this->endpoints['standard']);
        $this->assertEquals(true, $this->webPush->flush());
    }

    public function testSendGCMNotificationWithoutGCMApiKey()
    {
        $webPush = new WebPush();

        $this->setExpectedException('ErrorException', 'No GCM API Key specified.');
        $webPush->sendNotification($this->endpoints['GCM'], null, null, true);
    }

    public function testSendGCMNotificationWithWrongGCMApiKey()
    {
        $webPush = new WebPush(array('GCM' => 'bar'));

        $res = $webPush->sendNotification($this->endpoints['GCM'], null, null, true);

        $this->assertTrue(is_array($res)); // there has been an error
        $this->assertArrayHasKey('success', $res);
        $this->assertEquals(false, $res['success']);

        $this->assertArrayHasKey('statusCode', $res);
        $this->assertEquals(401, $res['statusCode']);

        $this->assertArrayHasKey('headers', $res);
    }

    public function testEncrypt()
    {
        // encrypt is a private method
        $class = new ReflectionClass(get_class($this->webPush));
        $encrypt = $class->getMethod('encrypt');
        $encrypt->setAccessible(true);

        $res = $encrypt->invokeArgs($this->webPush, array($this->keys['standard'], 'test'));

        // I can't really test encryption since I don't have the user private key.
        // I can only test if the function executes.
        $this->assertArrayHasKey('cipherText', $res);
        $this->assertArrayHasKey('salt', $res);
        $this->assertArrayHasKey('localPublicKey', $res);
        $this->assertEquals(16, strlen(base64_decode($res['salt']))); // should be 16 bytes
    }
}
