<?php declare(strict_types=1);
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
use Minishlink\WebPush\ContentEncoding;
use Minishlink\WebPush\Encryption;
use Minishlink\WebPush\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Encryption::class)]
final class EncryptionTest extends PHPUnit\Framework\TestCase
{
    public function testBase64Encode(): void
    {
        $plaintext = 'When I grow up, I want to be a watermelon';
        $this->assertEquals('V2hlbiBJIGdyb3cgdXAsIEkgd2FudCB0byBiZSBhIHdhdGVybWVsb24', $this->base64Encode($plaintext));
    }

    public function testBase64Decode(): void
    {
        // Base64 URL-safe, no padding
        $this->assertEquals('When I grow up, I want to be a watermelon', $this->base64Decode('V2hlbiBJIGdyb3cgdXAsIEkgd2FudCB0byBiZSBhIHdhdGVybWVsb24'));
        $this->assertEquals('<<???>>', $this->base64Decode('PDw_Pz8-Pg'));

        // Standard Base64
        $this->assertEquals('When I grow up, I want to be a watermelon', $this->base64Decode('V2hlbiBJIGdyb3cgdXAsIEkgd2FudCB0byBiZSBhIHdhdGVybWVsb24='));
        $this->assertEquals('<<???>>', $this->base64Decode('PDw/Pz8+Pg=='));
    }

    public function testDeterministicEncrypt(): void
    {
        $contentEncoding = ContentEncoding::aes128gcm;
        $plaintext = 'When I grow up, I want to be a watermelon';

        $payload = Encryption::padPayload($plaintext, 0, $contentEncoding);
        $this->assertEquals('V2hlbiBJIGdyb3cgdXAsIEkgd2FudCB0byBiZSBhIHdhdGVybWVsb24C', $this->base64Encode($payload));

        $userPublicKey = 'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4';
        $userAuthToken = 'BTBZMqHH6r4Tts7J_aSIgg';

        $localPublicKey = $this->base64Decode('BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8');
        $salt = $this->base64Decode('DGv6ra1nlYgDCS1FRnbzlw');

        [$localPublicKeyObjectX, $localPublicKeyObjectY] = Utils::unserializePublicKey($localPublicKey);
        $localJwk = new JWK([
            'kty' => 'EC',
            'crv' => 'P-256',
            'd' => 'yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw',
            'x' => $this->base64Encode($localPublicKeyObjectX),
            'y' => $this->base64Encode($localPublicKeyObjectY),
        ]);

        $expected = [
            'localPublicKey' => $localPublicKey,
            'salt' => $salt,
            'cipherText' => $this->base64Decode('8pfeW0KbunFT06SuDKoJH9Ql87S1QUrdirN6GcG7sFz1y1sqLgVi1VhjVkHsUoEsbI_0LpXMuGvnzQ'),
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
        $this->assertEquals($this->base64Encode($expected['cipherText']), $this->base64Encode($result['cipherText']));
        $this->assertEquals($expected, $result);
    }

    public function testGetContentCodingHeader(): void
    {
        $localPublicKey = $this->base64Decode('BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8');
        $salt = $this->base64Decode('DGv6ra1nlYgDCS1FRnbzlw');

        $result = Encryption::getContentCodingHeader($salt, $localPublicKey, ContentEncoding::aes128gcm);
        $expected = $this->base64Decode('DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8');

        $this->assertEquals(Utils::safeStrlen($expected), Utils::safeStrlen($result));
        $this->assertEquals($expected, $result);
    }

    /**
     * @throws ErrorException
     */
    #[dataProvider('payloadProvider')]
    public function testPadPayload(string $payload, int $maxLengthToPad, int $expectedResLength): void
    {
        $res = Encryption::padPayload($payload, $maxLengthToPad, ContentEncoding::aesgcm);

        $this->assertStringContainsString('test', $res);
        $this->assertEquals($expectedResLength, Utils::safeStrlen($res));
    }

    public static function payloadProvider(): array
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

    protected function base64Decode(string $value): string
    {
        return Base64Url::decode($value);
    }

    protected function base64Encode(string $value): string
    {
        return Base64Url::encode($value);
    }
}
