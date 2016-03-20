<?php

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
use Mdanter\Ecc\Crypto\Key\PublicKey;
use Mdanter\Ecc\EccFactory;
use Mdanter\Ecc\Serializer\Point\UncompressedPointSerializer;

final class Encryption
{
    const MAX_PAYLOAD_LENGTH = 4078;

    /**
     * @param $payload
     * @return string
     */
    public static function automaticPadding($payload)
    {
        return str_pad($payload, self::MAX_PAYLOAD_LENGTH, chr(0), STR_PAD_LEFT);
    }

    /**
     * @param string $payload
     * @param string $userPublicKey MIME base 64 encoded
     * @param string $userAuthToken MIME base 64 encoded
     * @param bool   $nativeEncryption Use OpenSSL (>PHP7.1)
     *
     * @return array
     */
    public static function encrypt($payload, $userPublicKey, $userAuthToken, $nativeEncryption)
    {
        $userPublicKey = base64_decode($userPublicKey);
        $userAuthToken = base64_decode($userAuthToken);
        $plaintext = chr(0).chr(0).utf8_decode($payload);

        // initialize utilities
        $math = EccFactory::getAdapter();
        $keySerializer = new UncompressedPointSerializer($math);
        $curveGenerator = EccFactory::getNistCurves()->generator256();
        $curve = EccFactory::getNistCurves()->curve256();

        // get local key pair
        $localPrivateKeyObject = $curveGenerator->createPrivateKey();
        $localPublicKeyObject = $localPrivateKeyObject->getPublicKey();
        $localPublicKey = hex2bin($keySerializer->serialize($localPublicKeyObject->getPoint()));

        // get user public key object
        $userPublicKeyObject = new PublicKey($math, $curveGenerator, $keySerializer->unserialize($curve, bin2hex($userPublicKey)));

        // get shared secret from user public key and local private key
        $sharedSecret = hex2bin($math->decHex($userPublicKeyObject->getPoint()->mul($localPrivateKeyObject->getSecret())->getX()));

        // generate salt
        $salt = openssl_random_pseudo_bytes(16);

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
        if (!$nativeEncryption) {
            list($encryptedText, $tag) = \Jose\Util\GCM::encrypt($contentEncryptionKey, $nonce, $plaintext, "");
        } else {
            $encryptedText = openssl_encrypt($plaintext, 'aes-128-gcm', $contentEncryptionKey, OPENSSL_RAW_DATA, $nonce, $tag); // base 64 encoded
        }

        // return values in url safe base64
        return array(
            'localPublicKey' => Base64Url::encode($localPublicKey),
            'salt' => Base64Url::encode($salt),
            'cipherText' => $encryptedText.$tag,
        );
    }

    /**
     * HMAC-based Extract-and-Expand Key Derivation Function (HKDF)
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
     * @param $salt string A non-secret random value
     * @param $ikm string Input keying material
     * @param $info string Application-specific context
     * @param $length int The length (in bytes) of the required output key
     * @return string
     */
    private static function hkdf($salt, $ikm, $info, $length)
    {
        // extract
        $prk = hash_hmac('sha256', $ikm, $salt, true);

        // expand
        return substr(hash_hmac('sha256', $info.chr(1), $prk, true), 0, $length);
    }

    /**
     * Creates a context for deriving encyption parameters.
     * See section 4.2 of
     * {@link https://tools.ietf.org/html/draft-ietf-httpbis-encryption-encoding-00}
     * From {@link https://github.com/GoogleChrome/push-encryption-node/blob/master/src/encrypt.js}
     *
     * @param $clientPublicKey string The client's public key
     * @param $serverPublicKey string Our public key
     * @return string
     * @throws \ErrorException
     */
    private static function createContext($clientPublicKey, $serverPublicKey)
    {
        if (strlen($clientPublicKey) !== 65) {
            throw new \ErrorException('Invalid client public key length');
        }

        // This one should never happen, because it's our code that generates the key
        if (strlen($serverPublicKey) !== 65) {
            throw new \ErrorException('Invalid server public key length');
        }

        return chr(0).strlen($clientPublicKey).$clientPublicKey.strlen($serverPublicKey).$serverPublicKey;
    }

    /**
     * Returns an info record. See sections 3.2 and 3.3 of
     * {@link https://tools.ietf.org/html/draft-ietf-httpbis-encryption-encoding-00}
     * From {@link https://github.com/GoogleChrome/push-encryption-node/blob/master/src/encrypt.js}
     *
     * @param $type string The type of the info record
     * @param $context string The context for the record
     * @return string
     * @throws \ErrorException
     */
    private static function createInfo($type, $context) {
        if (strlen($context) !== 135) {
            throw new \ErrorException('Context argument has invalid size');
        }

        return 'Content-Encoding: '.$type.chr(0).'P-256'.$context;
    }
}
