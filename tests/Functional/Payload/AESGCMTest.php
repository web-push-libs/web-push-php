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

namespace Minishlink\Tests\Functional\Payload;

use function chr;
use DateTimeInterface;
use InvalidArgumentException;
use Minishlink\WebPush\Base64Url;
use Minishlink\WebPush\Keys;
use Minishlink\WebPush\Payload\AESGCM;
use Minishlink\WebPush\Payload\ServerKey;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\Utils;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use function Safe\openssl_decrypt;
use function Safe\sprintf;
use function Safe\unpack;

/**
 * @internal
 */
final class AESGCMTest extends TestCase
{
    private static ?string $body = null;
    private static ?string $salt = null;
    private static ?string $cryptoKey = null;

    /**
     * @test
     * @dataProvider dataEncryptPayload
     *
     * @see https://tests.peter.sh/push-encryption-verifier/
     */
    public function encryptPayload(string $userAgentPrivateKey, string $userAgentPublicKey, string $userAgentAuthToken, string $payload, string $padding, LoggerInterface $logger, CacheItemPoolInterface $cache): void
    {
        self::$body = null;
        $stream = self::createMock(StreamInterface::class);
        $stream
            ->expects(static::once())
            ->method('write')
            ->with(static::isType('string'))
            ->willReturnCallback(static function (string $body): void {
                self::$body = $body;
            })
        ;

        $request = self::createMock(RequestInterface::class);
        $request
            ->expects(static::exactly(2))
            ->method('getBody')
            ->willReturn($stream)
        ;
        $request
            ->expects(static::exactly(2))
            ->method('withHeader')
            ->withConsecutive(
                ['Encryption', static::callback(static function (string $data): bool {
                    $pos = mb_strpos($data, 'salt=', 0, '8bit');
                    if (0 !== $pos) {
                        return false;
                    }
                    $salt = Base64Url::decode(mb_substr($data, 5, null));
                    self::$salt = $salt;

                    return 16 === mb_strlen($salt, '8bit');
                })],
                ['Content-Length', static::isType('string')],
            )
            ->willReturnSelf()
        ;
        $request
            ->expects(static::exactly(1))
            ->method('withAddedHeader')
            ->withConsecutive(
                ['Crypto-Key', static::callback(static function (string $data): bool {
                    $pos = mb_strpos($data, 'dh=', 0, '8bit');
                    if (0 !== $pos) {
                        return false;
                    }
                    $cryptoKey = Base64Url::decode(mb_substr($data, 3, null));
                    self::$cryptoKey = $cryptoKey;

                    return 65 === mb_strlen($cryptoKey, '8bit');
                })],
            )
            ->willReturnSelf()
        ;

        $keys = self::createMock(Keys::class);
        $keys
            ->expects(static::exactly(2))
            ->method('has')
            ->willReturnCallback(static function (string $name): bool {
                switch ($name) {
                    case 'p256dh':
                    case 'auth':
                        return true;
                    default:
                        return false;
                }
            })
        ;
        $keys
            ->expects(static::exactly(2))
            ->method('get')
            ->willReturnCallback(static function (string $name) use ($userAgentPublicKey, $userAgentAuthToken): string {
                switch ($name) {
                    case 'p256dh':
                        return $userAgentPublicKey;
                    case 'auth':
                        return $userAgentAuthToken;
                    default:
                        throw new InvalidArgumentException('Undefined key');
                }
            })
        ;

        $subscription = self::createMock(Subscription::class);
        $subscription
            ->expects(static::once())
            ->method('getKeys')
            ->willReturn($keys)
        ;

        $encoder = AESGCM::create()
            ->setCache($cache)
            ->setLogger($logger)
        ;

        switch ($padding) {
            case 'noPadding':
                $encoder->noPadding();
                break;
            case 'recommendedPadding':
                $encoder->recommendedPadding();
                break;
            case 'maxPadding':
                $encoder->maxPadding();
                break;
            default:
                break;
        }
        static::assertEquals('aesgcm', $encoder->name());

        $encoder->encode($payload, $request, $subscription);

        $stream
            ->expects(static::once())
            ->method('rewind')
        ;
        $stream
            ->expects(static::once())
            ->method('getContents')
            ->willReturn(self::$body)
        ;
        $decryptedPayload = $this->decryptRequest(
            $request,
            Base64Url::decode($userAgentAuthToken),
            Base64Url::decode($userAgentPublicKey),
            Base64Url::decode($userAgentPrivateKey),
            true
        );

        static::assertEquals($payload, $decryptedPayload);
    }

    /**
     * @test
     */
    public function largePayloadForbidden(): void
    {
        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage('The size of payload must not be greater than 4096 bytes.');

        $userAgentPrivateKey = 'q1dXpw3UpT5VOmu_cf_v6ih07Aems3njxI-JWgLcM94';
        $userAgentPublicKey = 'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcx aOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4';
        $userAgentAuthToken = 'BTBZMqHH6r4Tts7J_aSIgg';

        $stream = self::createMock(StreamInterface::class);
        $stream
            ->expects(static::once())
            ->method('write')
            ->with(static::isType('string'))
            ->willReturnCallback(static function (string $body): void {
                self::$body = $body;
            })
        ;

        $request = self::createMock(RequestInterface::class);
        $request
            ->expects(static::once())
            ->method('getBody')
            ->willReturn($stream)
        ;
        $request
            ->expects(static::never())
            ->method('withHeader')
        ;
        $request
            ->expects(static::never())
            ->method('withAddedHeader')
        ;

        $keys = self::createMock(Keys::class);
        $keys
            ->expects(static::exactly(2))
            ->method('has')
            ->willReturnCallback(static function (string $name): bool {
                switch ($name) {
                    case 'p256dh':
                    case 'auth':
                        return true;
                    default:
                        return false;
                }
            })
        ;
        $keys
            ->expects(static::exactly(2))
            ->method('get')
            ->willReturnCallback(static function (string $name) use ($userAgentPublicKey, $userAgentAuthToken): string {
                switch ($name) {
                    case 'p256dh':
                        return $userAgentPublicKey;
                    case 'auth':
                        return $userAgentAuthToken;
                    default:
                        throw new InvalidArgumentException('Undefined key');
                }
            })
        ;

        $subscription = self::createMock(Subscription::class);
        $subscription
            ->expects(static::once())
            ->method('getKeys')
            ->willReturn($keys)
        ;

        $encoder = AESGCM::create();

        static::assertEquals('aesgcm', $encoder->name());
        $payload = str_pad('', 4079, '0');

        $encoder->encode($payload, $request, $subscription);

        $stream
            ->expects(static::once())
            ->method('rewind')
        ;
        $stream
            ->expects(static::once())
            ->method('getContents')
            ->willReturn(self::$body)
        ;
        $decryptedPayload = $this->decryptRequest(
            $request,
            Base64Url::decode($userAgentAuthToken),
            Base64Url::decode($userAgentPublicKey),
            Base64Url::decode($userAgentPrivateKey),
            true
        );

        static::assertEquals($payload, $decryptedPayload);
    }

    public function dataEncryptPayload(): array
    {
        $withoutCache = $this->getMissingCache();
        $withoutLogger = $this->getLoggerForMissingCache();
        $withCache = $this->getExistingCache();
        $withLogger = $this->getLoggerForExistingCache();
        $uaPrivateKey = 'q1dXpw3UpT5VOmu_cf_v6ih07Aems3njxI-JWgLcM94';
        $uaPublicKey = 'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcx aOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4';
        $uaAuthSecret = 'BTBZMqHH6r4Tts7J_aSIgg';
        $payload = 'When I grow up, I want to be a watermelon';

        return [
            [
                $uaPrivateKey,
                $uaPublicKey,
                $uaAuthSecret,
                $payload,
                'noPadding',
                $withoutLogger,
                $withoutCache,
            ],
            [
                $uaPrivateKey,
                $uaPublicKey,
                $uaAuthSecret,
                str_pad('', 4078, '1'),
                'noPadding',
                $withoutLogger,
                $withoutCache,
            ],
            [
                $uaPrivateKey,
                $uaPublicKey,
                $uaAuthSecret,
                $payload,
                'recommendedPadding',
                $withoutLogger,
                $withoutCache,
            ],
            [
                $uaPrivateKey,
                $uaPublicKey,
                $uaAuthSecret,
                $payload,
                'maxPadding',
                $withoutLogger,
                $withoutCache,
            ],
            [
                $uaPrivateKey,
                $uaPublicKey,
                $uaAuthSecret,
                $payload,
                'noPadding',
                $withLogger,
                $withCache,
            ],
            [
                $uaPrivateKey,
                $uaPublicKey,
                $uaAuthSecret,
                $payload,
                'recommendedPadding',
                $withLogger,
                $withCache,
            ],
            [
                $uaPrivateKey,
                $uaPublicKey,
                $uaAuthSecret,
                $payload,
                'maxPadding',
                $withLogger,
                $withCache,
            ],
        ];
    }

    private function decryptRequest(RequestInterface $request, string $authSecret, string $receiverPublicKey, string $receiverPrivateKey, bool $inverted = false): string
    {
        $requestBody = $request->getBody();
        $requestBody->rewind();

        $ciphertext = $requestBody->getContents();

        $salt = self::$salt;
        $keyid = self::$cryptoKey;
        self::$salt = null;
        self::$cryptoKey = null;

        $context = sprintf('%s%s%s%s',
            "P-256\0\0A",
            $inverted ? $receiverPublicKey : $keyid,
            "\0A",
            $inverted ? $keyid : $receiverPublicKey
        );

        // IKM
        $keyInfo = 'Content-Encoding: auth'.chr(0);
        $ikm = Utils::computeIKM($keyInfo, $authSecret, $keyid, $receiverPrivateKey, $receiverPublicKey);

        // We compute the PRK
        $prk = hash_hmac('sha256', $ikm, $salt, true);

        $cekInfo = 'Content-Encoding: aesgcm'.chr(0).$context;
        $cek = mb_substr(hash_hmac('sha256', $cekInfo.chr(1), $prk, true), 0, 16, '8bit');

        $nonceInfo = 'Content-Encoding: nonce'.chr(0).$context;
        $nonce = mb_substr(hash_hmac('sha256', $nonceInfo.chr(1), $prk, true), 0, 12, '8bit');

        $C = mb_substr($ciphertext, 0, -16, '8bit');
        $T = mb_substr($ciphertext, -16, null, '8bit');

        $rawData = openssl_decrypt($C, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $T);
        $padding = mb_substr($rawData, 0, 2, '8bit');
        $paddingLength = unpack('n', $padding)[1];

        return mb_substr($rawData, 2 + $paddingLength, null, '8bit');
    }

    private function getMissingCache(): CacheItemPoolInterface
    {
        $item = self::createMock(CacheItemInterface::class);
        $item
            ->expects(static::atLeastOnce())
            ->method('isHit')
            ->willReturn(false)
        ;
        $item
            ->expects(static::atLeastOnce())
            ->method('set')
            ->with(static::isInstanceOf(ServerKey::class))
            ->willReturnSelf()
        ;
        $item
            ->expects(static::atLeastOnce())
            ->method('expiresAt')
            ->with(static::isInstanceOf(DateTimeInterface::class))
            ->willReturnSelf()
        ;
        $cache = self::createMock(CacheItemPoolInterface::class);
        $cache
            ->expects(static::atLeastOnce())
            ->method('getItem')
            ->with('WEB_PUSH_PAYLOAD_ENCRYPTION')
            ->willReturn($item)
        ;
        $cache
            ->expects(static::atLeastOnce())
            ->method('save')
            ->with(static::isInstanceOf(CacheItemInterface::class))
        ;

        return $cache;
    }

    private function getLoggerForExistingCache(): LoggerInterface
    {
        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(static::atLeast(13))
            ->method('debug')
            ->withConsecutive(
                ['Trying to encode the following payload.'],
                ['User-agent public key: BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4'],
                ['User-agent auth token: BTBZMqHH6r4Tts7J_aSIgg'],
                [static::callback(static function (string $data): bool {
                    $pos = mb_strpos($data, 'Salt: ', 0, '8bit');
                    if (0 !== $pos) {
                        return false;
                    }
                    $salt = Base64Url::decode(mb_substr($data, 6, null));

                    return 16 === mb_strlen($salt, '8bit');
                })],
                ['Getting key from the cache'],
                ['The key is available from the cache.'],
                [static::callback(static function (string $data): bool {
                    $pos = mb_strpos($data, 'IKM: ', 0, '8bit');
                    if (0 !== $pos) {
                        return false;
                    }
                    $salt = Base64Url::decode(mb_substr($data, 5, null));

                    return 32 === mb_strlen($salt, '8bit');
                })],
                [static::callback(static function (string $data): bool {
                    $pos = mb_strpos($data, 'PRK: ', 0, '8bit');
                    if (0 !== $pos) {
                        return false;
                    }
                    $salt = Base64Url::decode(mb_substr($data, 5, null));

                    return 32 === mb_strlen($salt, '8bit');
                })],
                [static::callback(static function (string $data): bool {
                    $pos = mb_strpos($data, 'CEK: ', 0, '8bit');
                    if (0 !== $pos) {
                        return false;
                    }
                    $salt = Base64Url::decode(mb_substr($data, 5, null));

                    return 16 === mb_strlen($salt, '8bit');
                })],
                [static::callback(static function (string $data): bool {
                    $pos = mb_strpos($data, 'NONCE: ', 0, '8bit');
                    if (0 !== $pos) {
                        return false;
                    }
                    $salt = Base64Url::decode(mb_substr($data, 7, null));

                    return 12 === mb_strlen($salt, '8bit');
                })],
                ['Payload with padding', static::callback(static function (array $data): bool {
                    return isset($data['padded_payload']);
                })],
                [static::callback(static function (string $data): bool {
                    return 0 === mb_strpos($data, 'Encrypted payload: ', 0, '8bit');
                })],
                [static::callback(static function (string $data): bool {
                    $pos = mb_strpos($data, 'Tag: ', 0, '8bit');
                    if (0 !== $pos) {
                        return false;
                    }
                    $salt = Base64Url::decode(mb_substr($data, 5, null));

                    return 16 === mb_strlen($salt, '8bit');
                })],
            )
        ;

        return $logger;
    }

    private function getLoggerForMissingCache(): LoggerInterface
    {
        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(static::atLeast(16))
            ->method('debug')
            ->withConsecutive(
                ['Trying to encode the following payload.'],
                ['User-agent public key: BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4'],
                ['User-agent auth token: BTBZMqHH6r4Tts7J_aSIgg'],
                [static::callback(static function (string $data): bool {
                    $pos = mb_strpos($data, 'Salt: ', 0, '8bit');
                    if (0 !== $pos) {
                        return false;
                    }
                    $salt = Base64Url::decode(mb_substr($data, 6, null));

                    return 16 === mb_strlen($salt, '8bit');
                })],
                ['Getting key from the cache'],
                ['No key from the cache'],
                ['Generating new key pair'],
                ['The key has been created.'],
                ['Key saved'],
                [static::callback(static function (string $data): bool {
                    $pos = mb_strpos($data, 'IKM: ', 0, '8bit');
                    if (0 !== $pos) {
                        return false;
                    }
                    $salt = Base64Url::decode(mb_substr($data, 5, null));

                    return 32 === mb_strlen($salt, '8bit');
                })],
                [static::callback(static function (string $data): bool {
                    $pos = mb_strpos($data, 'PRK: ', 0, '8bit');
                    if (0 !== $pos) {
                        return false;
                    }
                    $salt = Base64Url::decode(mb_substr($data, 5, null));

                    return 32 === mb_strlen($salt, '8bit');
                })],
                [static::callback(static function (string $data): bool {
                    $pos = mb_strpos($data, 'CEK: ', 0, '8bit');
                    if (0 !== $pos) {
                        return false;
                    }
                    $salt = Base64Url::decode(mb_substr($data, 5, null));

                    return 16 === mb_strlen($salt, '8bit');
                })],
                [static::callback(static function (string $data): bool {
                    $pos = mb_strpos($data, 'NONCE: ', 0, '8bit');
                    if (0 !== $pos) {
                        return false;
                    }
                    $salt = Base64Url::decode(mb_substr($data, 7, null));

                    return 12 === mb_strlen($salt, '8bit');
                })],
                ['Payload with padding'],
                [static::callback(static function (string $data): bool {
                    return 0 === mb_strpos($data, 'Encrypted payload: ', 0, '8bit');
                })],
                [static::callback(static function (string $data): bool {
                    $pos = mb_strpos($data, 'Tag: ', 0, '8bit');
                    if (0 !== $pos) {
                        return false;
                    }
                    $salt = Base64Url::decode(mb_substr($data, 5, null));

                    return 16 === mb_strlen($salt, '8bit');
                })],
            )
        ;

        return $logger;
    }

    private function getExistingCache(): CacheItemPoolInterface
    {
        $item = self::createMock(CacheItemInterface::class);
        $item
            ->expects(static::atLeastOnce())
            ->method('isHit')
            ->willReturn(true)
        ;
        $item
            ->expects(static::atLeastOnce())
            ->method('get')
            ->willReturn(new ServerKey(
                Base64Url::decode('BNuH4FkvKM50iG9sNLmJxSJL-H5B7KzxdpVOMp8OCmJZIaiZhXWFEolBD3xAXpJbjqMuny5jznfDnjYKueWngnM'),
                Base64Url::decode('Bw10H72jYRnlGZQytw8ruC9uJzqkWJqlOyFEEqQqYZ0')
            ))
        ;
        $item
            ->expects(static::once())
            ->method('expiresAt')
            ->with(static::isInstanceOf(DateTimeInterface::class))
            ->willReturnSelf()
        ;
        $cache = self::createMock(CacheItemPoolInterface::class);
        $cache
            ->expects(static::atLeastOnce())
            ->method('getItem')
            ->with('WEB_PUSH_PAYLOAD_ENCRYPTION')
            ->willReturn($item)
        ;

        return $cache;
    }
}
