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

namespace Minishlink\WebPush;

use Assert\Assertion;
use function chr;
use function Safe\pack;
use function Safe\unpack;

abstract class Utils
{
    public static function privateKeyToPEM(string $privateKey, string $publicKey): string
    {
        $d = unpack('H*', str_pad($privateKey, 32, chr(0), STR_PAD_LEFT))[1];

        $der = pack(
            'H*',
            '3077' // SEQUENCE, length 87+length($d)=32
                .'020101' // INTEGER, 1
                    .'0420'   // OCTET STRING, length($d) = 32
                        .$d
                    .'a00a' // TAGGED OBJECT #0, length 10
                        .'0608' // OID, length 8
                            .'2a8648ce3d030107' // 1.3.132.0.34 = P-256 Curve
                    .'a144' //  TAGGED OBJECT #1, length 68
                        .'0342' // BIT STRING, length 66
                            .'00' // prepend with NUL - pubkey will follow
        );
        $der .= $publicKey;

        $pem = '-----BEGIN EC PRIVATE KEY-----'.PHP_EOL;
        $pem .= chunk_split(base64_encode($der), 64, PHP_EOL);
        $pem .= '-----END EC PRIVATE KEY-----'.PHP_EOL;

        return $pem;
    }

    public static function publicKeyToPEM(string $publicKey): string
    {
        $der = pack(
            'H*',
            '3059' // SEQUENCE, length 89
                .'3013' // SEQUENCE, length 19
                    .'0607' // OID, length 7
                        .'2a8648ce3d0201' // 1.2.840.10045.2.1 = EC Public Key
                    .'0608' // OID, length 8
                        .'2a8648ce3d030107' // 1.2.840.10045.3.1.7 = P-256 Curve
                .'0342' // BIT STRING, length 66
                    .'00' // prepend with NUL - pubkey will follow
        );
        $der .= $publicKey;

        $pem = '-----BEGIN PUBLIC KEY-----'.PHP_EOL;
        $pem .= chunk_split(base64_encode($der), 64, PHP_EOL);
        $pem .= '-----END PUBLIC KEY-----'.PHP_EOL;

        return $pem;
    }

    public static function computeIKM(string $keyInfo, string $userAgentAuthToken, string $userAgentPublicKey, string $serverPrivateKey, string $serverPublicKey): string
    {
        $sharedSecret = self::computeAgreementKey($userAgentPublicKey, $serverPrivateKey, $serverPublicKey);

        return self::hkdf($userAgentAuthToken, $sharedSecret, $keyInfo, 32);
    }

    public static function hkdf(string $salt, string $ikm, string $info, int $length): string
    {
        // Extract
        $prk = hash_hmac('sha256', $ikm, $salt, true);

        // Expand
        return mb_substr(hash_hmac('sha256', $info.chr(1), $prk, true), 0, $length, '8bit');
    }

    private static function computeAgreementKey(string $userAgentPublicKey, string $serverPrivateKey, string $serverPublicKey): string
    {
        $serverPrivateKeyPEM = self::privateKeyToPEM($serverPrivateKey, $serverPublicKey);
        $userAgentPublicKeyPEM = self::publicKeyToPEM($userAgentPublicKey);
        $result = openssl_pkey_derive($userAgentPublicKeyPEM, $serverPrivateKeyPEM, 256);
        Assertion::string($result, 'Unable to compute the agreement key');

        return str_pad($result, 32, chr(0), STR_PAD_LEFT);
    }
}
