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
use Jose\Factory\JWKFactory;
use Jose\Factory\JWSFactory;
use Mdanter\Ecc\Crypto\Key\PrivateKeyInterface;
use Mdanter\Ecc\EccFactory;
use Mdanter\Ecc\Serializer\Point\UncompressedPointSerializer;
use Mdanter\Ecc\Serializer\PrivateKey\DerPrivateKeySerializer;
use Mdanter\Ecc\Serializer\PrivateKey\PemPrivateKeySerializer;

class VAPID
{
    const PUBLIC_KEY_LENGTH = 65;
    const PRIVATE_KEY_LENGTH = 32;

    /**
     * @param array $vapid
     *
     * @return array
     *
     * @throws \ErrorException
     */
    public static function validate(array $vapid)
    {
        if (!array_key_exists('subject', $vapid)) {
            throw new \ErrorException('[VAPID] You must provide a subject that is either a mailto: or a URL.');
        }

        if (array_key_exists('pemFile', $vapid)) {
            $vapid['pem'] = file_get_contents($vapid['pemFile']);

            if (!$vapid['pem']) {
                throw new \ErrorException('Error loading PEM file.');
            }
        }

        if (array_key_exists('pem', $vapid)) {
            $pem = $vapid['pem'];
            $posStartKey = strpos($pem, '-----BEGIN EC PRIVATE KEY-----');
            $posEndKey = strpos($pem, '-----END EC PRIVATE KEY-----');

            if ($posStartKey === false || $posEndKey === false) {
                throw new \ErrorException('Invalid PEM data.');
            }

            $posStartKey += 30; // length of '-----BEGIN EC PRIVATE KEY-----'

            $pemSerializer = new PemPrivateKeySerializer(new DerPrivateKeySerializer());
            $keys = self::getUncompressedKeys($pemSerializer->parse(mb_substr($pem, $posStartKey, $posEndKey - $posStartKey, '8bit')));
            $vapid['publicKey'] = $keys['publicKey'];
            $vapid['privateKey'] = $keys['privateKey'];
        }

        if (!array_key_exists('publicKey', $vapid)) {
            throw new \ErrorException('[VAPID] You must provide a public key.');
        }

        $publicKey = Base64Url::decode($vapid['publicKey']);

        if (Utils::safeStrlen($publicKey) !== self::PUBLIC_KEY_LENGTH) {
            throw new \ErrorException('[VAPID] Public key should be 65 bytes long when decoded.');
        }

        if (!array_key_exists('privateKey', $vapid)) {
            throw new \ErrorException('[VAPID] You must provide a private key.');
        }

        $privateKey = Base64Url::decode($vapid['privateKey']);

        if (Utils::safeStrlen($privateKey) !== self::PRIVATE_KEY_LENGTH) {
            throw new \ErrorException('[VAPID] Private key should be 32 bytes long when decoded.');
        }

        return array(
            'subject' => $vapid['subject'],
            'publicKey' => $publicKey,
            'privateKey' => $privateKey,
        );
    }

    /**
     * This method takes the required VAPID parameters and returns the required
     * header to be added to a Web Push Protocol Request.
     *
     * @param string $audience   This must be the origin of the push service
     * @param string $subject    This should be a URL or a 'mailto:' email address
     * @param string $publicKey  The decoded VAPID public key
     * @param string $privateKey The decoded VAPID private key
     * @param int    $expiration The expiration of the VAPID JWT. (UNIX timestamp)
     *
     * @return array Returns an array with the 'Authorization' and 'Crypto-Key' values to be used as headers
     */
    public static function getVapidHeaders($audience, $subject, $publicKey, $privateKey, $expiration = null)
    {
        $expirationLimit = time() + 43200; // equal margin of error between 0 and 24h
        if (!isset($expiration) || $expiration > $expirationLimit) {
            $expiration = $expirationLimit;
        }

        $header = array(
            'typ' => 'JWT',
            'alg' => 'ES256',
        );

        $jwtPayload = json_encode(array(
            'aud' => $audience,
            'exp' => $expiration,
            'sub' => $subject,
        ), JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);

        $generator = EccFactory::getNistCurves()->generator256();
        $privateKeyObject = $generator->getPrivateKeyFrom(gmp_init(bin2hex($privateKey), 16));
        $pemSerialize = new PemPrivateKeySerializer(new DerPrivateKeySerializer());
        $pem = $pemSerialize->serialize($privateKeyObject);

        $jwk = JWKFactory::createFromKey($pem, null);
        $jws = JWSFactory::createJWSToCompactJSON($jwtPayload, $jwk, $header);

        return array(
            'Authorization' => 'WebPush '.$jws,
            'Crypto-Key' => 'p256ecdsa='.Base64Url::encode($publicKey),
        );
    }

    /**
     * This method creates VAPID keys in case you would not be able to have a Linux bash.
     * DO NOT create keys at each initialization! Save those keys and reuse them.
     *
     * @return array
     */
    public static function createVapidKeys()
    {
        $privateKeyObject = EccFactory::getNistCurves()->generator256()->createPrivateKey();

        return self::getUncompressedKeys($privateKeyObject);
    }

    private static function getUncompressedKeys(PrivateKeyInterface $privateKeyObject)
    {
        $pointSerializer = new UncompressedPointSerializer(EccFactory::getAdapter());
        $vapid['publicKey'] = base64_encode(hex2bin($pointSerializer->serialize($privateKeyObject->getPublicKey()->getPoint())));
        $vapid['privateKey'] = base64_encode(hex2bin(str_pad(gmp_strval($privateKeyObject->getSecret(), 16), 2 * self::PRIVATE_KEY_LENGTH, '0', STR_PAD_LEFT)));

        return $vapid;
    }
}
