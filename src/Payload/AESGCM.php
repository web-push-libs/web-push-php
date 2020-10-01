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
    private const PADDING_NONE = 0;
    private const PADDING_MAX = 4078;
    private const PADDING_RECOMMENDED = 3052;

    private string $serverPublicKey;
    private string $serverPrivateKey;
    private int $padding = self::PADDING_RECOMMENDED;

    public function __construct(string $serverPrivateKey, string $serverPublicKey)
    {
        $this->serverPublicKey = Base64Url::decode($serverPublicKey);
        $this->serverPrivateKey = Base64Url::decode($serverPrivateKey);
    }

    public function noPadding(): self
    {
        $this->padding = self::PADDING_NONE;

        return $this;
    }

    public function recommendedPadding(): self
    {
        $this->padding = self::PADDING_RECOMMENDED;

        return $this;
    }

    public function maxPadding(): self
    {
        $this->padding = self::PADDING_MAX;

        return $this;
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

        //IKM
        $ikm = Utils::computeIKM($userAgentAuthToken, $userAgentPublicKey, $this->serverPrivateKey, $this->serverPublicKey);

        //PRK
        $prk = hash_hmac('sha256', $ikm, $salt, true);

        // Context
        $context = 'P-256';
        $context .= chr(0);
        $context .= chr(65);
        $context .= $userAgentPublicKey;
        $context .= chr(65);
        $context .= $this->serverPublicKey;

        // Derive the Content Encryption Key
        $contentEncryptionKeyInfo = $this->createInfo('aesgcm', $context);
        $contentEncryptionKey = Utils::hkdf($salt, $prk, $contentEncryptionKeyInfo, 16);

        // Derive the Nonce
        $nonceInfo = $this->createInfo('nonce', $context, );
        $nonce = Utils::hkdf($salt, $prk, $nonceInfo, 12);

        // Padding
        $paddedPayload = pack('n*', $this->padding).str_pad($payload, $this->padding, chr(0), STR_PAD_LEFT);

        // Encryption
        $tag = '';
        $encryptedText = openssl_encrypt($paddedPayload, 'aes-128-gcm', $contentEncryptionKey, OPENSSL_RAW_DATA, $nonce, $tag);

        // Body to be sent
        $body = $salt.pack('N*', 4096).chr(65).$this->serverPublicKey;
        $body .= $encryptedText.$tag;

        $bodyLength = mb_strlen($body, '8bit');
        $bodyB64 = Base64Url::encode($body);

        $request
            ->getBody()
            ->write($bodyB64)
        ;

        return $request
            ->withHeader('Encryption', 'salt='.Base64Url::encode($salt))
            ->withHeader('Crypto-Key', 'dh='.Base64Url::encode($this->serverPublicKey))
            ->withHeader('Content-Length', (string) $bodyLength)
            ;
    }

    private function createInfo(string $type, string $context): string
    {
        $info = 'Content-Encoding: ';
        $info .= $type;
        $info .= chr(0);
        $info .= $context;

        return $info;
    }
}
