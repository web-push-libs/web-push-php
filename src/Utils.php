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

use Base64Url\Base64Url;
use Jose\Component\Core\JWK;
use Jose\Component\Core\Util\Ecc\PublicKey;

class Utils
{
    public static function safeStrlen(string $value): int
    {
        return mb_strlen($value, '8bit');
    }

    public static function serializePublicKey(PublicKey $publicKey): string
    {
        $hexString = '04';
        $point = $publicKey->getPoint();
        $hexString .= str_pad($point->getX()->toBase(16), 64, '0', STR_PAD_LEFT);
        $hexString .= str_pad($point->getY()->toBase(16), 64, '0', STR_PAD_LEFT);

        return $hexString;
    }

    public static function serializePublicKeyFromJWK(JWK $jwk): string
    {
        $hexString = '04';
        $hexString .= str_pad(bin2hex(Base64Url::decode($jwk->get('x'))), 64, '0', STR_PAD_LEFT);
        $hexString .= str_pad(bin2hex(Base64Url::decode($jwk->get('y'))), 64, '0', STR_PAD_LEFT);

        return $hexString;
    }

    public static function unserializePublicKey(string $data): array
    {
        $data = bin2hex($data);
        if (mb_substr($data, 0, 2, '8bit') !== '04') {
            throw new \InvalidArgumentException('Invalid data: only uncompressed keys are supported.');
        }
        $data = mb_substr($data, 2, null, '8bit');
        $dataLength = self::safeStrlen($data);

        return [
            hex2bin(mb_substr($data, 0, $dataLength / 2, '8bit')),
            hex2bin(mb_substr($data, $dataLength / 2, null, '8bit')),
        ];
    }

    /**
     * Generates user warning/notice if some requirements are not met.
     * Does not throw exception to allow unusual or polyfill environments.
     */
    public static function checkRequirement(): void
    {
        self::checkRequirementExtension();
        self::checkRequirementKeyCipherHash();
    }

    public static function checkRequirementExtension(): void
    {
        $requiredExtensions = [
            'curl'     => '[WebPush] curl extension is not loaded but is required. You can fix this in your php.ini.',
            'mbstring' => '[WebPush] mbstring extension is not loaded but is required for sending push notifications with payload or for VAPID authentication. You can fix this in your php.ini.',
            'openssl'  => '[WebPush] openssl extension is not loaded but is required for sending push notifications with payload or for VAPID authentication. You can fix this in your php.ini.',
        ];
        foreach($requiredExtensions as $extension => $message) {
            if(!extension_loaded($extension)) {
                trigger_error($message, E_USER_WARNING);
            }
        }

        // Check optional extensions.
        if(!extension_loaded("bcmath") && !extension_loaded("gmp")) {
            trigger_error("It is highly recommended to install the GMP or BCMath extension to speed up calculations. The fastest available calculator implementation will be automatically selected at runtime.", E_USER_NOTICE);
        }
    }

    public static function checkRequirementKeyCipherHash(): void
    {
        // Print your current openssl version with: OPENSSL_VERSION_TEXT
        // Check for outdated openssl without EC support.
        $requiredCurves  = [
            'prime256v1' => '[WebPush] Openssl does not support required curve prime256v1.',
        ];
        $availableCurves = openssl_get_curve_names();
        if($availableCurves === false) {
            trigger_error('[WebPush] Openssl does not support curves.', E_USER_WARNING);
        } else {
            foreach($requiredCurves as $curve => $message) {
                if(!in_array($curve, $availableCurves, true)) {
                    trigger_error($message, E_USER_WARNING);
                }
            }
        }

        // Check for unusual openssl without cipher support.
        $requiredCiphers  = [
            'aes-128-gcm' => '[WebPush] Openssl does not support required cipher aes-128-gcm.',
        ];
        $availableCiphers = openssl_get_cipher_methods();
        foreach($requiredCiphers as $cipher => $message) {
            if(!in_array($cipher, $availableCiphers, true)) {
                trigger_error($message, E_USER_WARNING);
            }
        }

        // Check for unusual php without hash algo support.
        $requiredHash  = [
            'sha256' => '[WebPush] Php does not support required hmac hash sha256.',
        ];
        $availableHash = hash_hmac_algos();
        foreach($requiredHash as $hash => $message) {
            if(!in_array($hash, $availableHash, true)) {
                trigger_error($message, E_USER_WARNING);
            }
        }
    }
}
