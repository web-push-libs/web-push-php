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

use Jose\Component\Core\Util\Ecc\PublicKey;

class Utils
{
    /**
     * @param string $value
     *
     * @return int
     */
    public static function safeStrlen(string $value): int
    {
        return mb_strlen($value, '8bit');
    }

    /**
     * @param PublicKey $publicKey
     *
     * @return string
     */
    public static function serializePublicKey(PublicKey $publicKey): string
    {
        $hexString = '04';
        $hexString .= str_pad(gmp_strval($publicKey->getPoint()->getX(), 16), 64, '0', STR_PAD_LEFT);
        $hexString .= str_pad(gmp_strval($publicKey->getPoint()->getY(), 16), 64, '0', STR_PAD_LEFT);

        return $hexString;
    }

    /**
     * @param string $data
     *
     * @return array
     */
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
