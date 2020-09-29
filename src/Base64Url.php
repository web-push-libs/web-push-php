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

use function base64_encode;
use function rtrim;
use function Safe\base64_decode;
use function strtr;

/**
 * Encode and decode data into Base64 Url Safe.
 */
final class Base64Url
{
    public static function encode(string $data): string
    {
        $encoded = strtr(base64_encode($data), '+/', '-_');

        return rtrim($encoded, '=');
    }

    public static function decode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'), true);
    }
}
