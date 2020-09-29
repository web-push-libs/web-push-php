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

namespace Minishlink\WebPush\Payload;

use Assert\Assertion;
use function chr;
use Minishlink\WebPush\Base64Url;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\Utils;
use Psr\Http\Message\RequestInterface;
use function Safe\openssl_encrypt;

final class AESGCM implements ContentEncoding
{
    private const ENCODING = 'aesgcm';

    private string $serverPublicKey;
    private string $serverPrivateKey;

    public function __construct(string $serverPrivateKey, string $serverPublicKey)
    {
        $this->serverPublicKey = Base64Url::decode($serverPublicKey);
        $this->serverPrivateKey = Base64Url::decode($serverPrivateKey);
        /*$this->serverPrivateKeyPEM = Utils::privateKeyToPEM(
            Base64Url::decode($serverPrivateKey),
            Base64Url::decode($serverPublicKey)
        );*/
    }

    public function name(): string
    {
        return self::ENCODING;
    }

    public function encode(string $payload, RequestInterface $request, Subscription $subscription): RequestInterface
    {
        $keys = $subscription->getKeys();
        Assertion::true($keys->has('p256dh'), 'The user-agent public key is missing');
        $userAgentPublicKey = Base64Url::decode($keys->get('p256dh'));

        Assertion::true($keys->has('auth'), 'The user-agent authentication token is missing');
        $userAgentAuthToken = Base64Url::decode($keys->get('auth'));

        $salt = random_bytes(16);

        //Agreement key
        $sharedSecret = Utils::computeAgreementKey($userAgentPublicKey, $this->serverPrivateKey, $this->serverPublicKey);

        //IKM
        $keyInfo = 'WebPush: info'.chr(0).$userAgentPublicKey.$this->serverPublicKey;
        $ikm = Utils::hkdf($userAgentAuthToken, $sharedSecret, $keyInfo, 32);

        //PRK
        $prk = hash_hmac('sha256', $ikm, $salt, true);

        // Derive the Content Encryption Key
        $contentEncryptionKeyInfo = $this->createInfo('aesgcm', $userAgentPublicKey);
        $contentEncryptionKey = Utils::hkdf($salt, $prk, $contentEncryptionKeyInfo, 16);

        // Derive the Nonce
        $nonceInfo = $this->createInfo('nonce', $userAgentPublicKey);
        $nonce = Utils::hkdf($salt, $prk, $nonceInfo, 12);

        $tag = '';
        $encryptedText = openssl_encrypt($payload, 'aes-128-gcm', $contentEncryptionKey, OPENSSL_RAW_DATA, $nonce, $tag);

        $encryptedTextB64 = base64_encode($encryptedText);
        $encryptedTextB64Length = mb_strlen($encryptedTextB64, '8bit');
        Assertion::lessOrEqualThan($encryptedTextB64Length, 4078, 'Payload too large');

        $request
            ->getBody()
            ->write($encryptedTextB64)
        ;

        return $request
            ->withHeader('Encryption', 'salt='.Base64Url::encode($salt))
            ->withHeader('Crypto-Key', 'dh='.Base64Url::encode($this->serverPublicKey))
            ->withHeader('Content-Length', (string) $encryptedTextB64Length)
        ;
    }

    private function createInfo(string $type, string $userAgentPublicKey): string
    {
        $info = 'Content-Encoding: ';
        $info .= $type;
        $info .= chr(0);
        $info .= 'P-256';
        $info .= chr(0);
        $info .= chr(65);
        $info .= $userAgentPublicKey;
        $info .= chr(65);
        $info .= $this->serverPublicKey;

        return $info;
    }
}
