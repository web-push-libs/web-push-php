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
use Jose\Object\JWK;
use Mdanter\Ecc\Curves\NistCurve;
use Mdanter\Ecc\EccFactory;
use Mdanter\Ecc\Serializer\PrivateKey\DerPrivateKeySerializer;
use Mdanter\Ecc\Serializer\PrivateKey\PemPrivateKeySerializer;

class VAPID
{
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

        if (!array_key_exists('publicKey', $vapid)) {
            throw new \ErrorException('[VAPID] You must provide a public key.');
        }

        $publicKey = Base64Url::decode($vapid['publicKey']);

        if (Utils::safe_strlen($publicKey) !== 65) {
            throw new \ErrorException('[VAPID] Public key should be 65 bytes long when decoded.');
        }

        if (!array_key_exists('privateKey', $vapid)) {
            throw new \ErrorException('[VAPID] You must provide a private key.');
        }

        $privateKey = Base64Url::decode($vapid['privateKey']);

        if (Utils::safe_strlen($privateKey) !== 32) {
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
        $expirationLimit = time() + 86400;
        if (!isset($expiration) || $expiration > $expirationLimit) {
            $expiration = $expirationLimit;
        }

        $header = array(
            'typ' => 'JWT',
            'alg' => 'ES256',
        );

        $jwtPayload = array(
            'aud' => $audience,
            'exp' => $expiration,
            'sub' => $subject,
        );

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
}
