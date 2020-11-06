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

namespace Minishlink\Tests\Functional;

use function array_key_exists;
use DateTimeInterface;
use InvalidArgumentException;
use Minishlink\WebPush\Base64Url;
use Minishlink\WebPush\Keys;
use Minishlink\WebPush\Notification;
use Minishlink\WebPush\Payload\ServerKey;
use Minishlink\WebPush\SimpleWebPush;
use Minishlink\WebPush\StatusReportFailure;
use Minishlink\WebPush\StatusReportSuccess;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID\Header;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
class WebPushTest extends TestCase
{
    /**
     * @test
     */
    public function aNotificationCanBeSent(): void
    {
        $response = self::createMock(ResponseInterface::class);
        $response
            ->expects(static::once())
            ->method('getStatusCode')
            ->willReturn(201)
        ;
        $response
            ->expects(static::once())
            ->method('getHeaderLine')
            ->with('location')
            ->willReturn('https://BAR-FOO.IO/0123456789')
        ;
        $response
            ->expects(static::once())
            ->method('getHeader')
            ->with('Link')
            ->willReturn(['https://BAR-FOO.IO/THIS-IS-ASYNC'])
        ;

        $body = self::createMock(StreamInterface::class);
        $body
            ->expects(static::once())
            ->method('write')
            ->willReturnSelf()
        ;

        $request = self::createMock(RequestInterface::class);
        $request
            ->expects(static::once())
            ->method('getBody')
            ->willReturn($body)
        ;
        $request
            ->expects(static::exactly(2))
            ->method('withAddedHeader')
            ->withConsecutive(
                ['Crypto-Key'],
                ['Authorization'],
            )
            ->willReturnSelf()
        ;
        $request
            ->expects(static::exactly(7))
            ->method('withHeader')
            ->withConsecutive(
                ['TTL', '3600'],
                ['Topic', 'topic'],
                ['Urgency', 'high'],
                ['Content-Type', 'application/octet-stream'],
                ['Content-Encoding', 'aesgcm'],
                ['Encryption', static::callback(static function (string $data): bool {
                    $pos = mb_strpos($data, 'salt=', 0, '8bit');
                    if (0 !== $pos) {
                        return false;
                    }
                    $salt = Base64Url::decode(mb_substr($data, 5, null));

                    return 16 === mb_strlen($salt, '8bit');
                })],
                ['Content-Length', '3070'],
            )
            ->willReturnSelf()
        ;

        $subscription = self::createMock(Subscription::class);
        $subscription
            ->expects(static::exactly(2))
            ->method('getEndpoint')
            ->willReturn('https://foo.bar')
        ;
        $subscription
            ->expects(static::once())
            ->method('getContentEncoding')
            ->willReturn('aesgcm')
        ;

        $keys = new Keys();
        $keys
            ->set('auth', 'wSfP1pfACMwFesCEfJx4-w')
            ->set('p256dh', 'BIlDpD05YLrVPXfANOKOCNSlTvjpb5vdFo-1e0jNcbGlFrP49LyOjYyIIAZIVCDAHEcX-135b859bdsse-PgosU')
        ;
        $subscription
            ->expects(static::once())
            ->method('getKeys')
            ->willReturn($keys)
        ;

        $notification = Notification::create()
            ->sync()
            ->high()
            ->withTopic('topic')
            ->withPayload('Hello World')
            ->withTTL(3600)
        ;

        $client = self::createMock(ClientInterface::class);
        $client
            ->expects(static::once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response)
        ;

        $requestFactory = self::createMock(RequestFactoryInterface::class);
        $requestFactory
            ->expects(static::once())
            ->method('createRequest')
            ->with('POST', 'https://foo.bar')
            ->willReturn($request)
        ;

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(static::exactly(32))
            ->method('debug')
            ->withConsecutive(
                ['Sending notification', static::callback(static function (array $data) use ($notification): bool {
                    if (!array_key_exists('notification', $data)) {
                        return false;
                    }

                    return $data['notification'] === $notification;
                })],
                ['Processing the request'],
                ['Processing with the TTL extension'],
                ['Processing with the Topic extension'],
                ['Processing with the Urgency extension'],
                ['Sending synchronous notification'],
                ['Processing with payload'],
                ['Encoder found: aesgcm. Processing with the encoder.'],
                ['Trying to encode the following payload.'],
                ['User-agent public key: BIlDpD05YLrVPXfANOKOCNSlTvjpb5vdFo-1e0jNcbGlFrP49LyOjYyIIAZIVCDAHEcX-135b859bdsse-PgosU'],
                ['User-agent auth token: wSfP1pfACMwFesCEfJx4-w'],
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
                ['Processing with VAPID header'],
                ['Caching feature is available'],
                ['Computing the JWS'],
                ['JWS computed'],
                ['Header from cache'],
                ['Processing done'],
                ['Request ready', static::callback(static function (array $data) use ($request): bool {
                    if (!array_key_exists('request', $data)) {
                        return false;
                    }

                    return $data['request'] === $request;
                })],
                ['Response received', static::callback(static function (array $data) use ($response): bool {
                    if (!array_key_exists('response', $data)) {
                        return false;
                    }

                    return $data['response'] === $response;
                })],
            )
        ;

        $eventDispatcher = self::createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(StatusReportSuccess::class))
        ;

        $report = SimpleWebPush::create($client, $requestFactory)
            ->enableVapid(
                'http://localhost:8000',
                'BB4W1qfBi7MF_Lnrc6i2oL-glAuKF4kevy9T0k2vyKV4qvuBrN3T6o9-7-NR3mKHwzDXzD3fe7XvIqIU1iADpGQ',
                'C40jLFSa5UWxstkFvdwzT3eHONE2FIJSEsVIncSCAqU'
            )
            ->setLogger($logger)
            ->setCache($this->getMissingCache())
            ->setEventDispatcher($eventDispatcher)
            ->send($notification, $subscription)
        ;

        static::assertInstanceOf(StatusReportSuccess::class, $report);
        static::assertTrue($report->isSuccess());
        static::assertSame($notification, $report->getNotification());
        static::assertSame($subscription, $report->getSubscription());
    }

    /**
     * @test
     */
    public function aNotificationCannotBeSent(): void
    {
        $response = self::createMock(ResponseInterface::class);
        $response
            ->expects(static::once())
            ->method('getStatusCode')
            ->willReturn(409)
        ;
        $response
            ->expects(static::once())
            ->method('getReasonPhrase')
            ->willReturn('Too Many Requests')
        ;

        $body = self::createMock(StreamInterface::class);
        $body
            ->expects(static::once())
            ->method('write')
            ->willReturnSelf()
        ;

        $request = self::createMock(RequestInterface::class);
        $request
            ->expects(static::once())
            ->method('getBody')
            ->willReturn($body)
        ;
        $request
            ->expects(static::exactly(2))
            ->method('withAddedHeader')
            ->withConsecutive(
                ['Crypto-Key'],
                ['Authorization'],
            )
            ->willReturnSelf()
        ;
        $request
            ->expects(static::exactly(6))
            ->method('withHeader')
            ->withConsecutive(
                ['TTL', '3600'],
                ['Topic', 'topic'],
                ['Urgency', 'high'],
                ['Content-Type', 'application/octet-stream'],
                ['Content-Encoding', 'aes128gcm'],
                ['Content-Length', '3154'],
            )
            ->willReturnSelf()
        ;

        $subscription = self::createMock(Subscription::class);
        $subscription
            ->expects(static::exactly(2))
            ->method('getEndpoint')
            ->willReturn('https://foo.bar')
        ;
        $subscription
            ->expects(static::once())
            ->method('getContentEncoding')
            ->willReturn('aes128gcm')
        ;

        $keys = new Keys();
        $keys
            ->set('auth', 'wSfP1pfACMwFesCEfJx4-w')
            ->set('p256dh', 'BIlDpD05YLrVPXfANOKOCNSlTvjpb5vdFo-1e0jNcbGlFrP49LyOjYyIIAZIVCDAHEcX-135b859bdsse-PgosU')
        ;
        $subscription
            ->expects(static::once())
            ->method('getKeys')
            ->willReturn($keys)
        ;

        $notification = Notification::create()
            ->sync()
            ->high()
            ->withTopic('topic')
            ->withPayload('Hello World')
            ->withTTL(3600)
        ;

        $client = self::createMock(ClientInterface::class);
        $client
            ->expects(static::once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response)
        ;

        $requestFactory = self::createMock(RequestFactoryInterface::class);
        $requestFactory
            ->expects(static::once())
            ->method('createRequest')
            ->with('POST', 'https://foo.bar')
            ->willReturn($request)
        ;

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(static::exactly(32))
            ->method('debug')
            ->withConsecutive(
                ['Sending notification', static::callback(static function (array $data) use ($notification): bool {
                    if (!array_key_exists('notification', $data)) {
                        return false;
                    }

                    return $data['notification'] === $notification;
                })],
                ['Processing the request'],
                ['Processing with the TTL extension'],
                ['Processing with the Topic extension'],
                ['Processing with the Urgency extension'],
                ['Sending synchronous notification'],
                ['Processing with payload'],
                ['Encoder found: aes128gcm. Processing with the encoder.'],
                ['Trying to encode the following payload.'],
                ['User-agent public key: BIlDpD05YLrVPXfANOKOCNSlTvjpb5vdFo-1e0jNcbGlFrP49LyOjYyIIAZIVCDAHEcX-135b859bdsse-PgosU'],
                ['User-agent auth token: wSfP1pfACMwFesCEfJx4-w'],
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
                ['Processing with VAPID header'],
                ['Caching feature is available'],
                ['Computing the JWS'],
                ['JWS computed'],
                ['Header from cache'],
                ['Processing done'],
                ['Request ready', static::callback(static function (array $data) use ($request): bool {
                    if (!array_key_exists('request', $data)) {
                        return false;
                    }

                    return $data['request'] === $request;
                })],
                ['Response received', static::callback(static function (array $data) use ($response): bool {
                    if (!array_key_exists('response', $data)) {
                        return false;
                    }

                    return $data['response'] === $response;
                })],
            )
        ;

        $eventDispatcher = self::createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(StatusReportFailure::class))
        ;

        $report = SimpleWebPush::create($client, $requestFactory)
            ->enableVapid(
                'http://localhost:8000',
                'BB4W1qfBi7MF_Lnrc6i2oL-glAuKF4kevy9T0k2vyKV4qvuBrN3T6o9-7-NR3mKHwzDXzD3fe7XvIqIU1iADpGQ',
                'C40jLFSa5UWxstkFvdwzT3eHONE2FIJSEsVIncSCAqU'
            )
            ->setLogger($logger)
            ->setCache($this->getMissingCache())
            ->setEventDispatcher($eventDispatcher)
            ->send($notification, $subscription)
        ;

        static::assertInstanceOf(StatusReportFailure::class, $report);
        static::assertFalse($report->isSuccess());
        static::assertSame('Too Many Requests', $report->getReason());
        static::assertSame(409, $report->getCode());
        static::assertSame($notification, $report->getNotification());
        static::assertSame($subscription, $report->getSubscription());
    }

    /**
     * @test
     */
    public function vapidCannotBeEnabledMoreThanOnce(): void
    {
        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage('VAPID has already been enabled');

        $client = self::createMock(ClientInterface::class);
        $requestFactory = self::createMock(RequestFactoryInterface::class);

        SimpleWebPush::create($client, $requestFactory)
            ->enableVapid(
                'http://localhost:8000',
                'BB4W1qfBi7MF_Lnrc6i2oL-glAuKF4kevy9T0k2vyKV4qvuBrN3T6o9-7-NR3mKHwzDXzD3fe7XvIqIU1iADpGQ',
                'C40jLFSa5UWxstkFvdwzT3eHONE2FIJSEsVIncSCAqU'
            )
            ->enableVapid(
                'http://localhost:8000',
                'BB4W1qfBi7MF_Lnrc6i2oL-glAuKF4kevy9T0k2vyKV4qvuBrN3T6o9-7-NR3mKHwzDXzD3fe7XvIqIU1iADpGQ',
                'C40jLFSa5UWxstkFvdwzT3eHONE2FIJSEsVIncSCAqU'
            )
        ;
    }

    private function getMissingCache(): CacheItemPoolInterface
    {
        $encryptionKeyItem = self::createMock(CacheItemInterface::class);
        $encryptionKeyItem
            ->expects(static::atLeastOnce())
            ->method('isHit')
            ->willReturn(false)
        ;
        $encryptionKeyItem
            ->expects(static::atLeastOnce())
            ->method('set')
            ->with(static::isInstanceOf(ServerKey::class))
            ->willReturnSelf()
        ;
        $encryptionKeyItem
            ->expects(static::atLeastOnce())
            ->method('expiresAt')
            ->with(static::isInstanceOf(DateTimeInterface::class))
            ->willReturnSelf()
        ;

        $vapidHeaderItem = self::createMock(CacheItemInterface::class);
        $vapidHeaderItem
            ->expects(static::atLeastOnce())
            ->method('isHit')
            ->willReturn(false)
        ;
        $vapidHeaderItem
            ->expects(static::atLeastOnce())
            ->method('set')
            ->with(static::isInstanceOf(Header::class))
            ->willReturnSelf()
        ;
        $vapidHeaderItem
            ->expects(static::atLeastOnce())
            ->method('expiresAt')
            ->with(static::isInstanceOf(DateTimeInterface::class))
            ->willReturnSelf()
        ;
        $cache = self::createMock(CacheItemPoolInterface::class);
        $cache
            ->expects(static::atLeastOnce())
            ->method('getItem')
            ->withConsecutive(
                ['WEB_PUSH_PAYLOAD_ENCRYPTION'], // Payload encryption keys
                ['9398f6c316e4704e8c0c03516ec74609b402474940150be08bca50cc354f93fde895c082e1bca9e5e0ecdb0928d5a461f2819fbfc7f2f09ec372966afbf11d8c'], //Vapid header
            )
            ->willReturnOnConsecutiveCalls(
                $encryptionKeyItem,
                $vapidHeaderItem
            )
        ;
        $cache
            ->expects(static::atLeastOnce())
            ->method('save')
            ->withConsecutive(
                [static::isInstanceOf(CacheItemInterface::class)],
                [static::isInstanceOf(CacheItemInterface::class)],
            )
        ;

        return $cache;
    }
}
