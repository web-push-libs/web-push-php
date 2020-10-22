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
use InvalidArgumentException;
use Minishlink\WebPush\Base64Url;
use Minishlink\WebPush\Keys;
use Minishlink\WebPush\Payload\AES128GCM;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\Utils;
use function ord;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use function Safe\openssl_decrypt;
use function Safe\preg_match;

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
    public function encryptPayload(string $userAgentPrivateKey, string $userAgentPublicKey, string $userAgentAuthToken, string $payload, string $padding): void
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
                ['Content-Length'],
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

    public function dataEncryptPayload(): array
    {
        return [
            [
                'uaPrivateKey' => 'q1dXpw3UpT5VOmu_cf_v6ih07Aems3njxI-JWgLcM94',
                'uaPublicKey' => 'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcx aOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4',
                'uaAuthSecret' => 'BTBZMqHH6r4Tts7J_aSIgg',
                'payload' => 'When I grow up, I want to be a watermelon',
                'padding' => 'noPadding',
            ],
            [
                'uaPrivateKey' => 'q1dXpw3UpT5VOmu_cf_v6ih07Aems3njxI-JWgLcM94',
                'uaPublicKey' => 'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcx aOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4',
                'uaAuthSecret' => 'BTBZMqHH6r4Tts7J_aSIgg',
                'payload' => 'When I grow up, I want to be a watermelon',
                'padding' => 'recommendedPadding',
            ],
            [
                'uaPrivateKey' => 'q1dXpw3UpT5VOmu_cf_v6ih07Aems3njxI-JWgLcM94',
                'uaPublicKey' => 'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcx aOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4',
                'uaAuthSecret' => 'BTBZMqHH6r4Tts7J_aSIgg',
                'payload' => 'When I grow up, I want to be a watermelon',
                'padding' => 'maxPadding',
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
        //$rs = mb_substr($ciphertext, 16, 4, '8bit');
        //$rs = unpack('N', $rs)[1];

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
}