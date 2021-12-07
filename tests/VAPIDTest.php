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

use Minishlink\WebPush\Utils;
use Minishlink\WebPush\VAPID;

final class VAPIDTest extends PHPUnit\Framework\TestCase
{
    public function vapidProvider() : array
    {
        return [
            [
                'http://push.com',
                [
                    'subject' => 'http://test.com',
                    'publicKey' => 'BA6jvk34k6YjElHQ6S0oZwmrsqHdCNajxcod6KJnI77Dagikfb--O_kYXcR2eflRz6l3PcI2r8fPCH3BElLQHDk',
                    'privateKey' => '-3CdhFOqjzixgAbUSa0Zv9zi-dwDVmWO7672aBxSFPQ',
                ],
                "aesgcm",
                1475452165,
                'WebPush eyJ0eXAiOiJKV1QiLCJhbGciOiJFUzI1NiJ9.eyJhdWQiOiJodHRwOi8vcHVzaC5jb20iLCJleHAiOjE0NzU0NTIxNjUsInN1YiI6Imh0dHA6Ly90ZXN0LmNvbSJ9.4F3ZKjeru4P9XM20rHPNvGBcr9zxhz8_ViyNfe11_xcuy7A9y7KfEPt6yuNikyW7eT9zYYD5mQZubDGa-5H2cA',
                'p256ecdsa=BA6jvk34k6YjElHQ6S0oZwmrsqHdCNajxcod6KJnI77Dagikfb--O_kYXcR2eflRz6l3PcI2r8fPCH3BElLQHDk',
            ], [
                'http://push.com',
                [
                    'subject' => 'http://test.com',
                    'publicKey' => 'BA6jvk34k6YjElHQ6S0oZwmrsqHdCNajxcod6KJnI77Dagikfb--O_kYXcR2eflRz6l3PcI2r8fPCH3BElLQHDk',
                    'privateKey' => '-3CdhFOqjzixgAbUSa0Zv9zi-dwDVmWO7672aBxSFPQ',
                ],
                "aes128gcm",
                1475452165,
                'vapid t=eyJ0eXAiOiJKV1QiLCJhbGciOiJFUzI1NiJ9.eyJhdWQiOiJodHRwOi8vcHVzaC5jb20iLCJleHAiOjE0NzU0NTIxNjUsInN1YiI6Imh0dHA6Ly90ZXN0LmNvbSJ9.4F3ZKjeru4P9XM20rHPNvGBcr9zxhz8_ViyNfe11_xcuy7A9y7KfEPt6yuNikyW7eT9zYYD5mQZubDGa-5H2cA, k=BA6jvk34k6YjElHQ6S0oZwmrsqHdCNajxcod6KJnI77Dagikfb--O_kYXcR2eflRz6l3PcI2r8fPCH3BElLQHDk',
                null,
            ],
        ];
    }

    /**
     * @dataProvider vapidProvider
     *
     * @throws ErrorException
     */
    public function testGetVapidHeaders(string $audience, array $vapid, string $contentEncoding, int $expiration, string $expectedAuthorization, ?string $expectedCryptoKey)
    {
        $vapid = VAPID::validate($vapid);
        $headers = VAPID::getVapidHeaders(
            $audience,
            $vapid['subject'],
            $vapid['publicKey'],
            $vapid['privateKey'],
            $contentEncoding,
            $expiration
        );

        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertEquals(Utils::safeStrlen($expectedAuthorization), Utils::safeStrlen($headers['Authorization']));
        $this->assertEquals($this->explodeAuthorization($expectedAuthorization), $this->explodeAuthorization($headers['Authorization']));

        if ($expectedCryptoKey) {
            $this->assertArrayHasKey('Crypto-Key', $headers);
            $this->assertEquals($expectedCryptoKey, $headers['Crypto-Key']);
        } else {
            $this->assertArrayNotHasKey('Crypto-Key', $headers);
        }
    }

    /**
     * @return array|string
     */
    private function explodeAuthorization(string $auth)
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
        $this->assertGreaterThanOrEqual(86, strlen($keys['publicKey']));
        $this->assertGreaterThanOrEqual(42, strlen($keys['privateKey']));
    }
}
