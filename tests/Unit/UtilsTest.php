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

namespace Minishlink\Tests\Unit;

use InvalidArgumentException;
use Minishlink\WebPush\Base64Url;
use Minishlink\WebPush\Keys;
use Minishlink\WebPush\Payload\AESGCM;
use Minishlink\WebPush\Subscription;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

/**
 * @internal
 */
final class UtilsTest extends TestCase
{
    /**
     * @test
     * @dataProvider dataEncryptPayload
     *
     * @see https://tests.peter.sh/push-encryption-verifier/
     */
    public function encryptPayload(string $userAgentPublicKey, string $serverPrivateKey, string $serverPublicKey, string $salt, string $userAgentAuthToken, string $expectedSharedSecret, string $expectedIKM, string $expectedPRK, string $expectedCEKInfo, string $expectedCEK, string $expectedNonceInfo, string $expectedNonce): void
    {
        $stream = self::createMock(StreamInterface::class);
        $stream
            ->expects(static::once())
            ->method('write')
            ->with(static::isType('string'))
        ;

        $request = self::createMock(RequestInterface::class);
        $request
            ->expects(static::once())
            ->method('getBody')
            ->willReturn($stream)
        ;
        $request
            ->expects(static::exactly(3))
            ->method('withHeader')
            ->withConsecutive(
                ['Encryption', static::callback(static function (string $data) {
                    return 0 === mb_strpos($data, 'salt=');
                })],
                ['Crypto-Key', static::callback(static function (string $data) {
                    return 0 === mb_strpos($data, 'dh=');
                })],
                ['Content-Length', 3140],
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

        $encoder = new AESGCM($serverPrivateKey, $serverPublicKey);
        $encoder->encode('Hello world!', $request, $subscription);

        static::assertEquals('aesgcm', $encoder->name());
    }

    public function dataEncryptPayload(): array
    {
        return [
            [
                'uaPublicKey' => 'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcx aOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4',
                'sPrivateKey' => 'yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw',
                'sPublicKey' => 'BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8',
                'salt' => Base64Url::decode('DGv6ra1nlYgDCS1FRnbzlw'),
                'uaAuthSecret' => 'BTBZMqHH6r4Tts7J_aSIgg',

                'expectedSharedSecret' => Base64Url::decode('kyrL1jIIOHEzg3sM2ZWRHDRB62YACZhhSlknJ672kSs'),
                'expectedIKM' => Base64Url::decode('S4lYMb_L0FxCeq0WhDx813KgSYqU26kOyzWUdsXYyrg'),
                'expectedPRK' => Base64Url::decode('09_eUZGrsvxChDCGRCdkLiDXrReGOEVeSCdCcPBSJSc'),
                'expectedCEKInfo' => Base64Url::decode('Q29udGVudC1FbmNvZGluZzogYWVzZ2NtAFAtMjU2AEEEJXGyvs3942BVGq8e0PTNNmwRzr5VX4m8t7GGpTM5FzFo7OLr4BhZe9MEebhuPI-OztV3ylkYfpJGmQ22ggCLDkEE_jP0qw3qcZFNtVgj9ztUlI9BMG2SBzLbuaWaUyhkgiAOWXp7e8JguhwieZhYCZLpOXMALzASooro8Gu7eOXsDw'),
                'expectedCEK' => Base64Url::decode('I0aNF_8JSyYrNs9hvs6a_A'),
                'expectedNonceInfo' => Base64Url::decode('Q29udGVudC1FbmNvZGluZzogbm9uY2UAUC0yNTYAQQQlcbK-zf3jYFUarx7Q9M02bBHOvlVfiby3sYalMzkXMWjs4uvgGFl70wR5uG48j47O1XfKWRh-kkaZDbaCAIsOQQT-M_SrDepxkU21WCP3O1SUj0EwbZIHMtu5pZpTKGSCIA5Zent7wmC6HCJ5mFgJkuk5cwAvMBKiiujwa7t45ewP'),
                'expectedNonce' => Base64Url::decode('Ig5YZ6b2gFFQinhs'),
            ],
        ];
    }
}
