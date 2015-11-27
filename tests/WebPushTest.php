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
        $res = $this->webPush->sendNotification($this->endpoints['standard']);

        $this->assertArrayHasKey('success', $res);
        $this->assertEquals(true, $res['success']);
    }

    public function testSendNotificationWithPayload()
    {
        $res = $this->webPush->sendNotification(
            $this->endpoints['standard'],
            'test',
            $this->keys['standard']
        );

        $this->assertArrayHasKey('success', $res);
        $this->assertEquals(true, $res['success']);
    }

    public function testSendGCMNotification()
    {
        $res = $this->webPush->sendNotification($this->endpoints['GCM']);

        $this->assertArrayHasKey('success', $res);
        $this->assertEquals(true, $res['success']);
    }

    public function testSendGCMNotificationWithoutGCMApiKey()
    {
        $webPush = new WebPush();

        $this->setExpectedException('ErrorException', 'No GCM API Key specified.');
        $webPush->sendNotification($this->endpoints['GCM']);
    }

    public function testSendGCMNotificationWithWrongGCMApiKey()
    {
        $webPush = new WebPush(array('GCM' => 'bar'));

        $res = $webPush->sendNotification($this->endpoints['GCM']);
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

        $expected = array(
            'localPublicKey' => 'BH_1HZcs53fCIMW7Q6ePJqCqc4JIzSeCTjcNBmoet2eMObvQTpiBHH0EnDYZ0kTqk5f2b6wruq7US1vewtngt6o',
            'salt' => '0HK6QfkQmcQKFVAgG2iOTw',
            'cipherText' => 'ivmuewrVd-7qkRgxRcu972JyrSvXJzbLeWhTXx1FRZndeP5PVS3fnLQhmK077PgW7C5MLAA_wzDpIN_oB9vo',
        );

        $actualUserPublicKey = 'BDFsuXPNuJ4SxoYcVVvRagonMcSKHXjsif4qmzpXTDyy29ZKqbwtVAgHCLJGP0HgQ0hpkg6H5-fPBvDjBQxjYfc';
        $actualPayload = '{"action":"chatMsg","name":"Bob","msg":"test"}';

        $actual = $encrypt->invokeArgs($this->webPush, array($actualUserPublicKey, $actualPayload));

        $this->assertEquals($expected, $actual);
    }
}
