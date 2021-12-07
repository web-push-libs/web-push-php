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

namespace Minishlink\WebPush;

use Base64Url\Base64Url;
use Brick\Math\BigInteger;
use Jose\Component\Core\JWK;
use Jose\Component\Core\Util\Ecc\PublicKey;

class Utils
{
    public static function safeStrlen(string $value): int
    {
        return mb_strlen($value, '8bit');
    }

    public static function serializePublicKey(PublicKey $publicKey): string
    {
        $hexString = '04';
        $point = $publicKey->getPoint();
        if ($point->getX() instanceof BigInteger) {
            $hexString .= str_pad($point->getX()->toBase(16), 64, '0', STR_PAD_LEFT);
            $hexString .= str_pad($point->getY()->toBase(16), 64, '0', STR_PAD_LEFT);
        } else { // @phpstan-ignore-line
            $hexString .= str_pad(gmp_strval($point->getX(), 16), 64, '0', STR_PAD_LEFT);
            $hexString .= str_pad(gmp_strval($point->getY(), 16), 64, '0', STR_PAD_LEFT); // @phpstan-ignore-line
        }

        return $hexString;
    }

    public static function serializePublicKeyFromJWK(JWK $jwk): string
    {
        $hexString = '04';
        $hexString .= str_pad(bin2hex(Base64Url::decode($jwk->get('x'))), 64, '0', STR_PAD_LEFT);
        $hexString .= str_pad(bin2hex(Base64Url::decode($jwk->get('y'))), 64, '0', STR_PAD_LEFT);

        return $hexString;
    }

    public static function unserializePublicKey(string $data): array
    {
        $data = bin2hex($data);
        if (mb_substr($data, 0, 2, '8bit') !== '04') {
            throw new \InvalidArgumentException('Invalid data: only uncompressed keys are supported.');
        }
        $data = mb_substr($data, 2, null, '8bit');
        $dataLength = self::safeStrlen($data);

        return [
            hex2bin(mb_substr($data, 0, $dataLength / 2, '8bit')),
            hex2bin(mb_substr($data, $dataLength / 2, null, '8bit')),
        ];
    }
}
