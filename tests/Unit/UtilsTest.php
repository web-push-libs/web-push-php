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

namespace Minishlink\Tests\Unit;

use Minishlink\WebPush\Base64Url;
use Minishlink\WebPush\Utils;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class UtilsTest extends TestCase
{
    /**
     * @test
     */
    public function publicKeyToPEM(): void
    {
        $publicKey = Base64Url::decode('BB4W1qfBi7MF_Lnrc6i2oL-glAuKF4kevy9T0k2vyKV4qvuBrN3T6o9-7-NR3mKHwzDXzD3fe7XvIqIU1iADpGQ');
        $pem = Utils::publicKeyToPEM($publicKey);

        static::assertEquals(<<<'PEM'
-----BEGIN PUBLIC KEY-----
MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEHhbWp8GLswX8uetzqLagv6CUC4oX
iR6/L1PSTa/IpXiq+4Gs3dPqj37v41HeYofDMNfMPd97te8iohTWIAOkZA==
-----END PUBLIC KEY-----

PEM
, $pem);
    }

    /**
     * @test
     */
    public function privateKeyToPEM(): void
    {
        $privateKey = Base64Url::decode('C40jLFSa5UWxstkFvdwzT3eHONE2FIJSEsVIncSCAqU');
        $publicKey = Base64Url::decode('BB4W1qfBi7MF_Lnrc6i2oL-glAuKF4kevy9T0k2vyKV4qvuBrN3T6o9-7-NR3mKHwzDXzD3fe7XvIqIU1iADpGQ');
        $pem = Utils::privateKeyToPEM($privateKey, $publicKey);

        static::assertEquals(<<<'PEM'
-----BEGIN EC PRIVATE KEY-----
MHcCAQEEIAuNIyxUmuVFsbLZBb3cM093hzjRNhSCUhLFSJ3EggKloAoGCCqGSM49
AwEHoUQDQgAEHhbWp8GLswX8uetzqLagv6CUC4oXiR6/L1PSTa/IpXiq+4Gs3dPq
j37v41HeYofDMNfMPd97te8iohTWIAOkZA==
-----END EC PRIVATE KEY-----

PEM
, $pem);
    }
}
