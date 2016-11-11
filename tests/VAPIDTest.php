<?php

/*
 * This file is part of the WebPush library.
 *
 * (c) Louis Lagrange <lagrange.louis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Minishlink\WebPush\Utils;
use Minishlink\WebPush\VAPID;

class VAPIDTest extends PHPUnit_Framework_TestCase
{
    public function vapidProvider()
    {
        return array(
            array(
                'http://push.com',
                array(
                    'subject' => 'http://test.com',
                    'publicKey' => 'BA6jvk34k6YjElHQ6S0oZwmrsqHdCNajxcod6KJnI77Dagikfb--O_kYXcR2eflRz6l3PcI2r8fPCH3BElLQHDk',
                    'privateKey' => '-3CdhFOqjzixgAbUSa0Zv9zi-dwDVmWO7672aBxSFPQ',
                ),
                '1475452165',
                'WebPush eyJ0eXAiOiJKV1QiLCJhbGciOiJFUzI1NiJ9.eyJhdWQiOiJodHRwOi8vcHVzaC5jb20iLCJleHAiOjE0NzU0NTIxNjUsInN1YiI6Imh0dHA6Ly90ZXN0LmNvbSJ9.4F3ZKjeru4P9XM20rHPNvGBcr9zxhz8_ViyNfe11_xcuy7A9y7KfEPt6yuNikyW7eT9zYYD5mQZubDGa-5H2cA',
                'p256ecdsa=BA6jvk34k6YjElHQ6S0oZwmrsqHdCNajxcod6KJnI77Dagikfb--O_kYXcR2eflRz6l3PcI2r8fPCH3BElLQHDk',
            ),
        );
    }

    /**
     * @dataProvider vapidProvider
     *
     * @param $audience
     * @param $vapid
     * @param $expiration
     * @param $expectedAuthorization
     * @param $expectedCryptoKey
     */
    public function testGetVapidHeaders($audience, $vapid, $expiration, $expectedAuthorization, $expectedCryptoKey)
    {
        $vapid = VAPID::validate($vapid);
        $headers = VAPID::getVapidHeaders($audience, $vapid['subject'], $vapid['publicKey'], $vapid['privateKey'], $expiration);

        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertEquals(Utils::safeStrlen($expectedAuthorization), Utils::safeStrlen($headers['Authorization']));
        $this->assertEquals($this->explodeAuthorization($expectedAuthorization), $this->explodeAuthorization($headers['Authorization']));
        $this->assertArrayHasKey('Crypto-Key', $headers);
        $this->assertEquals($expectedCryptoKey, $headers['Crypto-Key']);
    }

    private function explodeAuthorization($auth)
    {
        $auth = explode('.', $auth);
        array_pop($auth); // delete the signature which changes each time
        return $auth;
    }

    public function testCreateVapidKeys()
    {
        $keys = VAPID::createVapidKeys();
        $this->assertArrayHasKey('publicKey', $keys);
        $this->assertArrayHasKey('privateKey', $keys);
        $this->assertEquals(strlen($keys['publicKey']), 88);
        $this->assertEquals(strlen($keys['privateKey']), 44);
    }
}
