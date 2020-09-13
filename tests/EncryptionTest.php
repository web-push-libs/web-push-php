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

use Base64Url\Base64Url;
use Jose\Component\Core\JWK;
use Jose\Component\Core\Util\Ecc\NistCurve;
use Jose\Component\Core\Util\Ecc\PrivateKey;
use Jose\Component\KeyManagement\JWKFactory;
use Minishlink\WebPush\Encryption;
use Minishlink\WebPush\Utils;

final class EncryptionTest extends PHPUnit\Framework\TestCase
{
    public function testDeterministicEncrypt()
    {
        $contentEncoding = "aes128gcm";
        $plaintext = 'When I grow up, I want to be a watermelon';
        $this->assertEquals('V2hlbiBJIGdyb3cgdXAsIEkgd2FudCB0byBiZSBhIHdhdGVybWVsb24', Base64Url::encode($plaintext));

        $payload = Encryption::padPayload($plaintext, 0, $contentEncoding);
        $this->assertEquals('V2hlbiBJIGdyb3cgdXAsIEkgd2FudCB0byBiZSBhIHdhdGVybWVsb24C', Base64Url::encode($payload));

        $userPublicKey = 'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4';
        $userAuthToken = 'BTBZMqHH6r4Tts7J_aSIgg';

        $localPublicKey = Base64Url::decode('BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8');
        $salt = Base64Url::decode('DGv6ra1nlYgDCS1FRnbzlw');

        [$localPublicKeyObjectX, $localPublicKeyObjectY] = Utils::unserializePublicKey($localPublicKey);
        $localJwk = new JWK([
            'kty' => 'EC',
            'crv' => 'P-256',
            'd' => 'yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw',
            'x' => Base64Url::encode($localPublicKeyObjectX),
            'y' => Base64Url::encode($localPublicKeyObjectY),
        ]);

        $expected = [
            'localPublicKey' => $localPublicKey,
            'salt' => $salt,
            'cipherText' => Base64Url::decode('8pfeW0KbunFT06SuDKoJH9Ql87S1QUrd irN6GcG7sFz1y1sqLgVi1VhjVkHsUoEsbI_0LpXMuGvnzQ'),
        ];

        $result = Encryption::deterministicEncrypt(
            $payload,
            $userPublicKey,
            $userAuthToken,
            $contentEncoding,
            [$localJwk],
            $salt
        );

        $this->assertEquals(Utils::safeStrlen($expected['cipherText']), Utils::safeStrlen($result['cipherText']));
        $this->assertEquals(Base64Url::encode($expected['cipherText']), Base64Url::encode($result['cipherText']));
        $this->assertEquals($expected, $result);
    }

    public function testGetContentCodingHeader()
    {
        $localPublicKey = Base64Url::decode('BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8');
        $salt = Base64Url::decode('DGv6ra1nlYgDCS1FRnbzlw');

        $result = Encryption::getContentCodingHeader($salt, $localPublicKey, "aes128gcm");
        $expected = Base64Url::decode('DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8');

        $this->assertEquals(Utils::safeStrlen($expected), Utils::safeStrlen($result));
        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider payloadProvider
     *
     * @param string $payload
     * @param int $maxLengthToPad
     * @param int $expectedResLength
     * @throws ErrorException
     */
    public function testPadPayload(string $payload, int $maxLengthToPad, int $expectedResLength)
    {
        $res = Encryption::padPayload($payload, $maxLengthToPad, "aesgcm");

        $this->assertStringContainsString('test', $res);
        $this->assertEquals($expectedResLength, Utils::safeStrlen($res));
    }

    /**
     * @return array
     */
    public function payloadProvider(): array
    {
        return [
            ['testé', 0, 8],
            ['testé', 1, 8],
            ['testé', 6, 8],
            ['testé', 7, 9],
            ['testé', Encryption::MAX_COMPATIBILITY_PAYLOAD_LENGTH, Encryption::MAX_COMPATIBILITY_PAYLOAD_LENGTH + 2],
            ['testé', Encryption::MAX_PAYLOAD_LENGTH, Encryption::MAX_PAYLOAD_LENGTH + 2],
            [str_repeat('test', 1019).'te', Encryption::MAX_PAYLOAD_LENGTH, Encryption::MAX_PAYLOAD_LENGTH + 2],
        ];
    }
}
