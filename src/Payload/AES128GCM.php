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
use function Safe\sprintf;

final class AES128GCM implements ContentEncoding
{
    private const ENCODING = 'aes128gcm';
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

        $ikm = Utils::computeIKM($userAgentAuthToken, $userAgentPublicKey, $this->serverPrivateKey, $this->serverPublicKey);

        //PRK
        $prk = hash_hmac('sha256', $ikm, $salt, true);

        // Context
        $context = '';

        // Derive the Content Encryption Key
        $contentEncryptionKeyInfo = $this->createInfo('aes128gcm', $context);
        $contentEncryptionKey = mb_substr(hash_hmac('sha256', $contentEncryptionKeyInfo.chr(1), $prk, true), 0, 16, '8bit');

        // Derive the Nonce
        $nonceInfo = $this->createInfo('nonce', $context, );
        $nonce = mb_substr(hash_hmac('sha256', $nonceInfo.chr(1), $prk, true), 0, 12, '8bit');

        // Padding
        $paddedPayload = str_pad($payload.chr(2), $this->padding, chr(0), STR_PAD_RIGHT);

        // Encryption
        $tag = '';
        $encryptedText = openssl_encrypt($paddedPayload, 'aes-128-gcm', $contentEncryptionKey, OPENSSL_RAW_DATA, $nonce, $tag);

        // Body to be sent
        $body = $salt.pack('N*', 4096).pack('C*', mb_strlen($this->serverPublicKey, '8bit')).$this->serverPublicKey;
        $body .= $encryptedText.$tag;

        $bodyB64 = Base64Url::encode($body);
        $bodyLength = mb_strlen($body, '8bit');

        $request->getBody()->write($bodyB64);

        return $request
            ->withAddedHeader('Crypto-Key', sprintf('dh=%s', Base64Url::encode($this->serverPublicKey)))
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
