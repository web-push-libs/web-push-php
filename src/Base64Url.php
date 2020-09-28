<?php

declare(strict_types=1);

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2020 Spomky-Labs
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
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
