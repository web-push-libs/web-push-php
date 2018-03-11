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
use Jose\Component\Core\Util\Ecc\NistCurve;

class Encryption
{
    public const MAX_PAYLOAD_LENGTH = 4078;
    public const MAX_COMPATIBILITY_PAYLOAD_LENGTH = 3052;

    /**
     * @param string $payload
     * @param int    $maxLengthToPad
     *
     * @return string padded payload (plaintext)
     */
    public static function padPayload(string $payload, int $maxLengthToPad): string
    {
        $payloadLen = Utils::safeStrlen($payload);
        $padLen = $maxLengthToPad ? $maxLengthToPad - $payloadLen : 0;

        return pack('n*', $padLen).str_pad($payload, $padLen + $payloadLen, chr(0), STR_PAD_LEFT);
    }

    /**
     * @param string $payload       With padding
     * @param string $userPublicKey Base 64 encoded (MIME or URL-safe)
     * @param string $userAuthToken Base 64 encoded (MIME or URL-safe)
     *
     * @return array
     *
     * @throws \ErrorException
     */
    public static function encrypt(string $payload, string $userPublicKey, string $userAuthToken): array
    {
        $userPublicKey = Base64Url::decode($userPublicKey);
        $userAuthToken = Base64Url::decode($userAuthToken);

        $curve = NistCurve::curve256();
        $privateKey = $curve->createPrivateKey();
        $publicKey = $curve->createPublicKey($privateKey);
        $localPublicKey = hex2bin(Utils::serializePublicKey($publicKey));

        // get shared secret from user public key and local private key
        $sharedSecret = $curve->mul($publicKey->getPoint(), $privateKey->getSecret())->getX();
        $sharedSecret = hex2bin(gmp_strval($sharedSecret, 16));

        // generate salt
        $salt = random_bytes(16);

        // section 4.3
        $ikm = !empty($userAuthToken) ?
            self::hkdf($userAuthToken, $sharedSecret, 'Content-Encoding: auth'.chr(0), 32) :
            $sharedSecret;

        // section 4.2
        $context = self::createContext($userPublicKey, $localPublicKey);

        // derive the Content Encryption Key
        $contentEncryptionKeyInfo = self::createInfo('aesgcm', $context);
        $contentEncryptionKey = self::hkdf($salt, $ikm, $contentEncryptionKeyInfo, 16);

        // section 3.3, derive the nonce
        $nonceInfo = self::createInfo('nonce', $context);
        $nonce = self::hkdf($salt, $ikm, $nonceInfo, 12);

        // encrypt
        // "The additional data passed to each invocation of AEAD_AES_128_GCM is a zero-length octet sequence."
        $encryptedText = openssl_encrypt($payload, 'aes-128-gcm', $contentEncryptionKey, OPENSSL_RAW_DATA, $nonce, $tag);

        // return values in url safe base64
        return [
            'localPublicKey' => Base64Url::encode($localPublicKey),
            'salt' => Base64Url::encode($salt),
            'cipherText' => $encryptedText.$tag,
        ];
    }

    /**
     * HMAC-based Extract-and-Expand Key Derivation Function (HKDF).
     *
     * This is used to derive a secure encryption key from a mostly-secure shared
     * secret.
     *
     * This is a partial implementation of HKDF tailored to our specific purposes.
     * In particular, for us the value of N will always be 1, and thus T always
     * equals HMAC-Hash(PRK, info | 0x01).
     *
     * See {@link https://www.rfc-editor.org/rfc/rfc5869.txt}
     * From {@link https://github.com/GoogleChrome/push-encryption-node/blob/master/src/encrypt.js}
     *
     * @param string $salt   A non-secret random value
     * @param string $ikm    Input keying material
     * @param string $info   Application-specific context
     * @param int    $length The length (in bytes) of the required output key
     *
     * @return string
     */
    private static function hkdf(string $salt, string $ikm, string $info, int $length): string
    {
        // extract
        $prk = hash_hmac('sha256', $ikm, $salt, true);

        // expand
        return mb_substr(hash_hmac('sha256', $info.chr(1), $prk, true), 0, $length, '8bit');
    }

    /**
     * Creates a context for deriving encryption parameters.
     * See section 4.2 of
     * {@link https://tools.ietf.org/html/draft-ietf-httpbis-encryption-encoding-00}
     * From {@link https://github.com/GoogleChrome/push-encryption-node/blob/master/src/encrypt.js}.
     *
     * @param string $clientPublicKey The client's public key
     * @param string $serverPublicKey Our public key
     *
     * @return string
     *
     * @throws \ErrorException
     */
    private static function createContext(string $clientPublicKey, string $serverPublicKey): string
    {
        if (Utils::safeStrlen($clientPublicKey) !== 65) {
            throw new \ErrorException('Invalid client public key length');
        }

        // This one should never happen, because it's our code that generates the key
        if (Utils::safeStrlen($serverPublicKey) !== 65) {
            throw new \ErrorException('Invalid server public key length');
        }

        $len = chr(0).'A'; // 65 as Uint16BE

        return chr(0).$len.$clientPublicKey.$len.$serverPublicKey;
    }

    /**
     * Returns an info record. See sections 3.2 and 3.3 of
     * {@link https://tools.ietf.org/html/draft-ietf-httpbis-encryption-encoding-00}
     * From {@link https://github.com/GoogleChrome/push-encryption-node/blob/master/src/encrypt.js}.
     *
     * @param string $type    The type of the info record
     * @param string $context The context for the record
     *
     * @return string
     *
     * @throws \ErrorException
     */
    private static function createInfo(string $type, string $context): string
    {
        if (Utils::safeStrlen($context) !== 135) {
            throw new \ErrorException('Context argument has invalid size');
        }

        return 'Content-Encoding: '.$type.chr(0).'P-256'.$context;
    }
}
