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
use Brick\Math\BigInteger;
use Jose\Component\Core\JWK;
use Jose\Component\Core\Util\Ecc\NistCurve;
use Jose\Component\Core\Util\Ecc\PrivateKey;
use Jose\Component\Core\Util\ECKey;

class Encryption
{
    public const MAX_PAYLOAD_LENGTH = 4078;
    public const MAX_COMPATIBILITY_PAYLOAD_LENGTH = 3052;

    /**
     * @param string $payload
     * @param int $maxLengthToPad
     * @param string $contentEncoding
     * @return string padded payload (plaintext)
     * @throws \ErrorException
     */
    public static function padPayload(string $payload, int $maxLengthToPad, string $contentEncoding): string
    {
        $payloadLen = Utils::safeStrlen($payload);
        $padLen = $maxLengthToPad ? $maxLengthToPad - $payloadLen : 0;

        if ($contentEncoding === "aesgcm") {
            return pack('n*', $padLen).str_pad($payload, $padLen + $payloadLen, chr(0), STR_PAD_LEFT);
        } elseif ($contentEncoding === "aes128gcm") {
            return str_pad($payload.chr(2), $padLen + $payloadLen, chr(0), STR_PAD_RIGHT);
        } else {
            throw new \ErrorException("This content encoding is not supported");
        }
    }

    /**
     * @param string $payload With padding
     * @param string $userPublicKey Base 64 encoded (MIME or URL-safe)
     * @param string $userAuthToken Base 64 encoded (MIME or URL-safe)
     * @param string $contentEncoding
     * @return array
     *
     * @throws \ErrorException
     */
    public static function encrypt(string $payload, string $userPublicKey, string $userAuthToken, string $contentEncoding): array
    {
        return self::deterministicEncrypt(
            $payload,
            $userPublicKey,
            $userAuthToken,
            $contentEncoding,
            self::createLocalKeyObject(),
            random_bytes(16)
        );
    }

    /**
     * @param string $payload
     * @param string $userPublicKey
     * @param string $userAuthToken
     * @param string $contentEncoding
     * @param array $localKeyObject
     * @param string $salt
     * @return array
     *
     * @throws \ErrorException
     */
    public static function deterministicEncrypt(string $payload, string $userPublicKey, string $userAuthToken, string $contentEncoding, array $localKeyObject, string $salt): array
    {
        $userPublicKey = Base64Url::decode($userPublicKey);
        $userAuthToken = Base64Url::decode($userAuthToken);

        // get local key pair
        if (count($localKeyObject) === 1) {
            /** @var JWK $localJwk */
            $localJwk = current($localKeyObject);
            $localPublicKey = hex2bin(Utils::serializePublicKeyFromJWK($localJwk));
        } else {
            /** @var PrivateKey $localPrivateKeyObject */
            list($localPublicKeyObject, $localPrivateKeyObject) = $localKeyObject;
            $localPublicKey = hex2bin(Utils::serializePublicKey($localPublicKeyObject));
            $localJwk = new JWK([
                'kty' => 'EC',
                'crv' => 'P-256',
                'd' => Base64Url::encode($localPrivateKeyObject->getSecret()->toBytes()),
                'x' => Base64Url::encode($localPublicKeyObject[0]),
                'y' => Base64Url::encode($localPublicKeyObject[1]),
            ]);
        }
        if (!$localPublicKey) {
            throw new \ErrorException('Failed to convert local public key from hexadecimal to binary');
        }

        // get user public key object
        [$userPublicKeyObjectX, $userPublicKeyObjectY] = Utils::unserializePublicKey($userPublicKey);
        $userJwk = new JWK([
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => Base64Url::encode($userPublicKeyObjectX),
            'y' => Base64Url::encode($userPublicKeyObjectY),
        ]);

        // get shared secret from user public key and local private key

        $sharedSecret = self::calculateAgreementKey($localJwk, $userJwk);

        $sharedSecret = str_pad($sharedSecret, 32, chr(0), STR_PAD_LEFT);
        if (!$sharedSecret) {
            throw new \ErrorException('Failed to convert shared secret from hexadecimal to binary');
        }

        // section 4.3
        $ikm = self::getIKM($userAuthToken, $userPublicKey, $localPublicKey, $sharedSecret, $contentEncoding);

        // section 4.2
        $context = self::createContext($userPublicKey, $localPublicKey, $contentEncoding);

        // derive the Content Encryption Key
        $contentEncryptionKeyInfo = self::createInfo($contentEncoding, $context, $contentEncoding);
        $contentEncryptionKey = self::hkdf($salt, $ikm, $contentEncryptionKeyInfo, 16);

        // section 3.3, derive the nonce
        $nonceInfo = self::createInfo('nonce', $context, $contentEncoding);
        $nonce = self::hkdf($salt, $ikm, $nonceInfo, 12);

        // encrypt
        // "The additional data passed to each invocation of AEAD_AES_128_GCM is a zero-length octet sequence."
        $tag = '';
        $encryptedText = openssl_encrypt($payload, 'aes-128-gcm', $contentEncryptionKey, OPENSSL_RAW_DATA, $nonce, $tag);

        // return values in url safe base64
        return [
            'localPublicKey' => $localPublicKey,
            'salt' => $salt,
            'cipherText' => $encryptedText.$tag,
        ];
    }

    public static function getContentCodingHeader(string $salt, string $localPublicKey, string $contentEncoding): string
    {
        if ($contentEncoding === "aes128gcm") {
            return $salt
                .pack('N*', 4096)
                .pack('C*', Utils::safeStrlen($localPublicKey))
                .$localPublicKey;
        }

        return "";
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
     * @return null|string
     *
     * @throws \ErrorException
     */
    private static function createContext(string $clientPublicKey, string $serverPublicKey, string $contentEncoding): ?string
    {
        if ($contentEncoding === "aes128gcm") {
            return null;
        }

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
     * @param string $type The type of the info record
     * @param string|null $context The context for the record
     * @param string $contentEncoding
     * @return string
     *
     * @throws \ErrorException
     */
    private static function createInfo(string $type, ?string $context, string $contentEncoding): string
    {
        if ($contentEncoding === "aesgcm") {
            if (!$context) {
                throw new \ErrorException('Context must exist');
            }

            if (Utils::safeStrlen($context) !== 135) {
                throw new \ErrorException('Context argument has invalid size');
            }

            return 'Content-Encoding: '.$type.chr(0).'P-256'.$context;
        } elseif ($contentEncoding === "aes128gcm") {
            return 'Content-Encoding: '.$type.chr(0);
        }

        throw new \ErrorException('This content encoding is not supported.');
    }

    /**
     * @return array
     */
    private static function createLocalKeyObject(): array
    {
        try {
            return self::createLocalKeyObjectUsingOpenSSL();
        } catch (\Exception $e) {
            return self::createLocalKeyObjectUsingPurePhpMethod();
        }
    }

    /**
     * @return array
     */
    private static function createLocalKeyObjectUsingPurePhpMethod(): array
    {
        $curve = NistCurve::curve256();
        $privateKey = $curve->createPrivateKey();
        $publicKey = $curve->createPublicKey($privateKey);

        if ($publicKey->getPoint()->getX() instanceof BigInteger) {
            return [
                new JWK([
                    'kty' => 'EC',
                    'crv' => 'P-256',
                    'x' => Base64Url::encode(self::addNullPadding($publicKey->getPoint()->getX()->toBytes())),
                    'y' => Base64Url::encode(self::addNullPadding($publicKey->getPoint()->getY()->toBytes())),
                    'd' => Base64Url::encode(self::addNullPadding($privateKey->getSecret()->toBytes())),
                ])
            ];
        }

        return [
            new JWK([
                'kty' => 'EC',
                'crv' => 'P-256',
                'x' => Base64Url::encode(self::addNullPadding(hex2bin(gmp_strval($publicKey->getPoint()->getX(), 16)))),
                'y' => Base64Url::encode(self::addNullPadding(hex2bin(gmp_strval($publicKey->getPoint()->getY(), 16)))),
                'd' => Base64Url::encode(self::addNullPadding(hex2bin(gmp_strval($privateKey->getSecret(), 16)))),
            ])
        ];
    }

    /**
     * @return array
     */
    private static function createLocalKeyObjectUsingOpenSSL(): array
    {
        $keyResource = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if (!$keyResource) {
            throw new \RuntimeException('Unable to create the key');
        }

        $details = openssl_pkey_get_details($keyResource);
        openssl_pkey_free($keyResource);

        if (!$details) {
            throw new \RuntimeException('Unable to get the key details');
        }

        return [
            new JWK([
                'kty' => 'EC',
                'crv' => 'P-256',
                'x' => Base64Url::encode(self::addNullPadding($details['ec']['x'])),
                'y' => Base64Url::encode(self::addNullPadding($details['ec']['y'])),
                'd' => Base64Url::encode(self::addNullPadding($details['ec']['d'])),
            ])
        ];
    }

    /**
     * @param string $userAuthToken
     * @param string $userPublicKey
     * @param string $localPublicKey
     * @param string $sharedSecret
     * @param string $contentEncoding
     * @return string
     * @throws \ErrorException
     */
    private static function getIKM(string $userAuthToken, string $userPublicKey, string $localPublicKey, string $sharedSecret, string $contentEncoding): string
    {
        if (!empty($userAuthToken)) {
            if ($contentEncoding === "aesgcm") {
                $info = 'Content-Encoding: auth'.chr(0);
            } elseif ($contentEncoding === "aes128gcm") {
                $info = "WebPush: info".chr(0).$userPublicKey.$localPublicKey;
            } else {
                throw new \ErrorException("This content encoding is not supported");
            }

            return self::hkdf($userAuthToken, $sharedSecret, $info, 32);
        }

        return $sharedSecret;
    }

    private static function calculateAgreementKey(JWK $private_key, JWK $public_key): string
    {
        if (function_exists('openssl_pkey_derive')) {
            try {
                $publicPem = ECKey::convertPublicKeyToPEM($public_key);
                $privatePem = ECKey::convertPrivateKeyToPEM($private_key);

                $result = openssl_pkey_derive($publicPem, $privatePem, 256); // @phpstan-ignore-line
                if ($result === false) {
                    throw new \Exception('Unable to compute the agreement key');
                }
                return $result;
            } catch (\Throwable $throwable) {
                //Does nothing. Will fallback to the pure PHP function
            }
        }


        $curve = NistCurve::curve256();
        try {
            $rec_x = self::convertBase64ToBigInteger($public_key->get('x'));
            $rec_y = self::convertBase64ToBigInteger($public_key->get('y'));
            $sen_d = self::convertBase64ToBigInteger($private_key->get('d'));
            $priv_key = PrivateKey::create($sen_d);
            $pub_key = $curve->getPublicKeyFrom($rec_x, $rec_y);

            return hex2bin(str_pad($curve->mul($pub_key->getPoint(), $priv_key->getSecret())->getX()->toBase(16), 64, '0', STR_PAD_LEFT)); // @phpstan-ignore-line
        } catch (\Throwable $e) {
            $rec_x = self::convertBase64ToGMP($public_key->get('x'));
            $rec_y = self::convertBase64ToGMP($public_key->get('y'));
            $sen_d = self::convertBase64ToGMP($private_key->get('d'));
            $priv_key = PrivateKey::create($sen_d); // @phpstan-ignore-line
            $pub_key = $curve->getPublicKeyFrom($rec_x, $rec_y); // @phpstan-ignore-line

            return hex2bin(gmp_strval($curve->mul($pub_key->getPoint(), $priv_key->getSecret())->getX(), 16)); // @phpstan-ignore-line
        }
    }

    /**
     * @param string $value
     * @return BigInteger
     */
    private static function convertBase64ToBigInteger(string $value): BigInteger
    {
        $value = unpack('H*', Base64Url::decode($value));

        return BigInteger::fromBase($value[1], 16);
    }

    /**
     * @param string $value
     * @return \GMP
     */
    private static function convertBase64ToGMP(string $value): \GMP
    {
        $value = unpack('H*', Base64Url::decode($value));

        return gmp_init($value[1], 16);
    }

    private static function addNullPadding(string $data): string
    {
        return str_pad($data, 32, chr(0), STR_PAD_LEFT);
    }
}
