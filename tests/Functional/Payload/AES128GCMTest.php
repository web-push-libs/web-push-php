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
use function count;
use DateTimeInterface;
use InvalidArgumentException;
use Minishlink\WebPush\Base64Url;
use Minishlink\WebPush\Keys;
use Minishlink\WebPush\Payload\AES128GCM;
use Minishlink\WebPush\Payload\ServerKey;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\Utils;
use function ord;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use function Safe\openssl_decrypt;
use function Safe\preg_match;
use function Safe\unpack;

/**
 * @internal
 */
final class AES128GCMTest extends TestCase
{
    private static ?string $body = null;

    /**
     * @test
     *
     * @see https://tests.peter.sh/push-encryption-verifier/
     */
    public function decryptPayloadCorrectly(): void
    {
        $body = Base64Url::decode('DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN');
        $userAgentPrivateKey = Base64Url::decode('q1dXpw3UpT5VOmu_cf_v6ih07Aems3njxI-JWgLcM94');
        $userAgentPublicKey = Base64Url::decode('BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcx aOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4');
        $userAgentAuthToken = Base64Url::decode('BTBZMqHH6r4Tts7J_aSIgg');
        $expectedPayload = 'When I grow up, I want to be a watermelon';

        $stream = self::createMock(StreamInterface::class);
        $stream
            ->expects(static::once())
            ->method('rewind')
        ;
        $stream
            ->expects(static::once())
            ->method('getContents')
            ->willReturn($body)
        ;

        $request = self::createMock(RequestInterface::class);
        $request
            ->expects(static::once())
            ->method('getBody')
            ->willReturn($stream)
        ;

        $payload = $this->decryptRequest($request, $userAgentAuthToken, $userAgentPublicKey, $userAgentPrivateKey, true);
        static::assertEquals($expectedPayload, $payload);
    }

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
            ->expects(static::exactly(1))
            ->method('withHeader')
            ->withConsecutive(
                ['Content-Length', static::isType('string')],
            )
            ->willReturnSelf()
        ;
        $request
            ->expects(static::exactly(1))
            ->method('withAddedHeader')
            ->withConsecutive(
                ['Crypto-Key'],
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

        $encoder = new AES128GCM();
        $encoder
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
        static::assertEquals('aes128gcm', $encoder->name());

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

        $encoder = new AES128GCM();

        static::assertEquals('aes128gcm', $encoder->name());
        $payload = str_pad('', 3994, '0');

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
                str_pad('', 3993, '1'),
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

        // Salt
        $salt = mb_substr($ciphertext, 0, 16, '8bit');
        static::assertEquals(mb_strlen($salt, '8bit'), 16);

        // Record size
        $rs = mb_substr($ciphertext, 16, 4, '8bit');
        $rs = unpack('N', $rs)[1];
        static::assertEquals(4096, $rs);

        // idlen
        $idlen = ord(mb_substr($ciphertext, 20, 1, '8bit'));

        //keyid
        $keyid = mb_substr($ciphertext, 21, $idlen, '8bit');

        // IKM
        $keyInfo = 'WebPush: info'.chr(0).($inverted ? $receiverPublicKey.$keyid : $keyid.$receiverPublicKey);
        $ikm = Utils::computeIKM($keyInfo, $authSecret, $keyid, $receiverPrivateKey, $receiverPublicKey);

        // We remove the header
        $ciphertext = mb_substr($ciphertext, 16 + 4 + 1 + $idlen, null, '8bit');

        // We compute the PRK
        $prk = hash_hmac('sha256', $ikm, $salt, true);

        $cekInfo = 'Content-Encoding: aes128gcm'.chr(0);
        $cek = mb_substr(hash_hmac('sha256', $cekInfo.chr(1), $prk, true), 0, 16, '8bit');

        $nonceInfo = 'Content-Encoding: nonce'.chr(0);
        $nonce = mb_substr(hash_hmac('sha256', $nonceInfo.chr(1), $prk, true), 0, 12, '8bit');

        $C = mb_substr($ciphertext, 0, -16, '8bit');
        $T = mb_substr($ciphertext, -16, null, '8bit');

        $rawData = openssl_decrypt($C, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $T);

        $matches = [];
        $r = preg_match('/^(.*)(\x02\x00*)$/', $rawData, $matches);
        if (1 !== $r || 3 !== count($matches)) {
            throw new InvalidArgumentException('Invalid data');
        }

        return $matches[1];
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
