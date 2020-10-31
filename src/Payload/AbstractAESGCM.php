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
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Safe\DateTimeImmutable;
use function Safe\openssl_encrypt;
use function Safe\openssl_pkey_new;
use function Safe\sprintf;

abstract class AbstractAESGCM implements ContentEncoding
{
    public const WEB_PUSH_PAYLOAD_ENCRYPTION = 'WEB_PUSH_PAYLOAD_ENCRYPTION';
    protected const PADDING_NONE = 0;
    protected const PADDING_RECOMMENDED = 3052;

    protected int $padding = self::PADDING_RECOMMENDED;

    private ?CacheItemPoolInterface $cache = null;
    private LoggerInterface $logger;
    private string $cacheKey = self::WEB_PUSH_PAYLOAD_ENCRYPTION;
    private string $cacheExpirationTime = 'now + 30min';

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function setCache(CacheItemPoolInterface $cache, string $cacheExpirationTime = 'now + 30min'): self
    {
        $this->cache = $cache;
        $this->cacheExpirationTime = $cacheExpirationTime;

        return $this;
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
        $this->logger->debug('Trying to encode the following payload.');
        $keys = $subscription->getKeys();
        Assertion::true($keys->has('p256dh'), 'The user-agent public key is missing');
        $userAgentPublicKey = Base64Url::decode($keys->get('p256dh'));
        $this->logger->debug(sprintf('User-agent public key: %s', Base64Url::encode($userAgentPublicKey)));

        Assertion::true($keys->has('auth'), 'The user-agent authentication token is missing');
        $userAgentAuthToken = Base64Url::decode($keys->get('auth'));
        $this->logger->debug(sprintf('User-agent auth token: %s', Base64Url::encode($userAgentAuthToken)));

        $salt = random_bytes(16);
        $this->logger->debug(sprintf('Salt: %s', Base64Url::encode($salt)));

        $serverKey = $this->getServerKey();

        //IKM
        $keyInfo = $this->getKeyInfo($userAgentPublicKey, $serverKey);
        $ikm = Utils::computeIKM($keyInfo, $userAgentAuthToken, $userAgentPublicKey, $serverKey->getPrivateKey(), $serverKey->getPublicKey());
        $this->logger->debug(sprintf('IKM: %s', Base64Url::encode($ikm)));

        //PRK
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $this->logger->debug(sprintf('PRK: %s', Base64Url::encode($prk)));

        // Context
        $context = $this->getContext($userAgentPublicKey, $serverKey);

        // Derive the Content Encryption Key
        $contentEncryptionKeyInfo = $this->createInfo($this->name(), $context);
        $contentEncryptionKey = mb_substr(hash_hmac('sha256', $contentEncryptionKeyInfo.chr(1), $prk, true), 0, 16, '8bit');
        $this->logger->debug(sprintf('CEK: %s', Base64Url::encode($contentEncryptionKey)));

        // Derive the Nonce
        $nonceInfo = $this->createInfo('nonce', $context);
        $nonce = mb_substr(hash_hmac('sha256', $nonceInfo.chr(1), $prk, true), 0, 12, '8bit');
        $this->logger->debug(sprintf('NONCE: %s', Base64Url::encode($nonce)));

        // Padding
        $paddedPayload = $this->addPadding($payload);
        $this->logger->debug('Payload with padding', ['padded_payload' => $paddedPayload]);

        // Encryption
        $tag = '';
        $encryptedText = openssl_encrypt($paddedPayload, 'aes-128-gcm', $contentEncryptionKey, OPENSSL_RAW_DATA, $nonce, $tag);
        $this->logger->debug(sprintf('Encrypted payload: %s', Base64Url::encode($encryptedText)));
        $this->logger->debug(sprintf('Tag: %s', Base64Url::encode($tag)));

        // Body to be sent
        $body = $this->prepareBody($encryptedText, $serverKey, $tag, $salt);
        $request->getBody()->write($body);

        $bodyLength = mb_strlen($body, '8bit');
        Assertion::max($bodyLength, 4096, 'The size of payload must not be greater than 4096 bytes.');

        $request = $this->prepareRequest($request, $salt);

        return $request
            ->withAddedHeader('Crypto-Key', sprintf('dh=%s', Base64Url::encode($serverKey->getPublicKey())))
            ->withHeader('Content-Length', (string) $bodyLength)
            ;
    }

    abstract protected function getKeyInfo(string $userAgentPublicKey, ServerKey $serverKey): string;

    abstract protected function getContext(string $userAgentPublicKey, ServerKey $serverKey): string;

    abstract protected function addPadding(string $payload): string;

    abstract protected function prepareRequest(RequestInterface $request, string $salt): RequestInterface;

    abstract protected function prepareBody(string $encryptedText, ServerKey $serverKey, string $tag, string $salt): string;

    private function createInfo(string $type, string $context): string
    {
        $info = 'Content-Encoding: ';
        $info .= $type;
        $info .= chr(0);
        $info .= $context;

        return $info;
    }

    private function getServerKey(): ServerKey
    {
        if (null === $this->cache) {
            return $this->generateServerKey();
        }
        $this->logger->debug('Getting key from the cache');

        return $this->getServerKeyFromCache();
    }

    private function getServerKeyFromCache(): ServerKey
    {
        $item = $this->cache->getItem($this->cacheKey);
        if ($item->isHit()) {
            $this->logger->debug('The key is available from the cache.');

            return $item->get();
        }

        $this->logger->debug('No key from the cache');
        $serverKey = $this->generateServerKey();
        $item = $item
            ->set($serverKey)
            ->expiresAt(new DateTimeImmutable($this->cacheExpirationTime))
        ;
        $this->cache->save($item);
        $this->logger->debug('Key saved');

        return $serverKey;
    }

    private function generateServerKey(): ServerKey
    {
        $this->logger->debug('Generating new key pair');
        $keyResource = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        $details = openssl_pkey_get_details($keyResource);
        openssl_pkey_free($keyResource);

        Assertion::isArray($details, 'Unable to get the key details');

        $publicKey = chr(4);
        $publicKey .= str_pad($details['ec']['x'], 32, chr(0), STR_PAD_LEFT);
        $publicKey .= str_pad($details['ec']['y'], 32, chr(0), STR_PAD_LEFT);
        $privateKey = $details['ec']['d'];
        $key = new ServerKey($publicKey, $privateKey);

        $this->logger->debug('The key has been created.');

        return $key;
    }
}
