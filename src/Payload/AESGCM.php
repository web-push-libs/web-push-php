<?php

declare(strict_types=1);

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2020 Spomky-Labs
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
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

    private string $serverPrivateKey;
    private string $serverPublicKey;

    public function __construct(string $serverPrivateKey, string $passphrase = '')
    {
        $this->serverPrivateKey = $serverPrivateKey;
        $this->serverPublicKey = Utils::serializePublicKey($serverPrivateKey, $passphrase);
    }

    public function name(): string
    {
        return self::ENCODING;
    }

    public function encode(string $payload, RequestInterface $request, Subscription $subscription): RequestInterface
    {
        $keys = $subscription->getKeys();
        Assertion::true($keys->has('p256dh'), 'The user-agent public key is missing');
        $userAgentPublicKey = $keys->get('p256dh');

        Assertion::true($keys->has('auth'), 'The user-agent authentication token is missing');
        $userAgentAuthToken = $keys->get('auth');

        $salt = random_bytes(16);

        //Agreement key
        $sharedSecret = Utils::computeAgreementKey($userAgentPublicKey, $this->serverPrivateKey);

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

        $encryptedTextLength = mb_strlen($encryptedText, '8bit');
        Assertion::lessOrEqualThan($encryptedTextLength, 4078, 'Payload too large');

        $request
            ->getBody()
            ->write($encryptedText)
        ;

        return $request
            ->withHeader('Encryption', 'salt='.Base64Url::encode($salt))
            ->withHeader('Crypto-Key', 'dh='.Base64Url::encode($this->serverPublicKey))
            ->withHeader('Content-Length', (string) $encryptedTextLength)
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
