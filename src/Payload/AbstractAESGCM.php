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

abstract class AbstractAESGCM implements ContentEncoding
{
    protected const PADDING_NONE = 0;
    protected const PADDING_RECOMMENDED = 3052;

    protected string $serverPublicKey;
    protected string $serverPrivateKey;
    protected int $padding = self::PADDING_RECOMMENDED;

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

    abstract public function maxPadding(): self;

    public function encode(string $payload, RequestInterface $request, Subscription $subscription): RequestInterface
    {
        $keys = $subscription->getKeys();
        Assertion::true($keys->has('p256dh'), 'The user-agent public key is missing');
        $userAgentPublicKey = Base64Url::decode($keys->get('p256dh'));

        Assertion::true($keys->has('auth'), 'The user-agent authentication token is missing');
        $userAgentAuthToken = Base64Url::decode($keys->get('auth'));

        $salt = random_bytes(16);

        //IKM
        $keyInfo = $this->getKeyInfo($userAgentPublicKey);
        $ikm = Utils::computeIKM($keyInfo, $userAgentAuthToken, $userAgentPublicKey, $this->serverPrivateKey, $this->serverPublicKey);

        //PRK
        $prk = hash_hmac('sha256', $ikm, $salt, true);

        // Context
        $context = $this->getContext($userAgentPublicKey);

        // Derive the Content Encryption Key
        $contentEncryptionKeyInfo = $this->createInfo($this->name(), $context);
        $contentEncryptionKey = mb_substr(hash_hmac('sha256', $contentEncryptionKeyInfo.chr(1), $prk, true), 0, 16, '8bit');

        // Derive the Nonce
        $nonceInfo = $this->createInfo('nonce', $context, );
        $nonce = mb_substr(hash_hmac('sha256', $nonceInfo.chr(1), $prk, true), 0, 12, '8bit');

        // Padding
        $paddedPayload = $this->addPadding($payload);

        // Encryption
        $tag = '';
        $encryptedText = openssl_encrypt($paddedPayload, 'aes-128-gcm', $contentEncryptionKey, OPENSSL_RAW_DATA, $nonce, $tag);

        // Body to be sent
        $body = $this->prepareBody($encryptedText, $tag, $salt);
        $request->getBody()->write($body);

        $bodyLength = mb_strlen($body, '8bit');
        Assertion::max($bodyLength, 4096, 'The size of payload must not be greater than 4096 bytes.');

        $request = $this->prepareRequest($request, $salt);

        return $request
            ->withAddedHeader('Crypto-Key', sprintf('dh=%s', Base64Url::encode($this->serverPublicKey)))
            ->withHeader('Content-Length', (string) $bodyLength)
            ;
    }

    protected function createInfo(string $type, string $context): string
    {
        $info = 'Content-Encoding: ';
        $info .= $type;
        $info .= chr(0);
        $info .= $context;

        return $info;
    }

    abstract protected function getKeyInfo(string $userAgentPublicKey): string;

    abstract protected function getContext(string $userAgentPublicKey): string;

    abstract protected function addPadding(string $payload): string;

    abstract protected function prepareRequest(RequestInterface $request, string $salt): RequestInterface;

    abstract protected function prepareBody(string $encryptedText, string $tag, string $salt): string;
}
