<?php declare(strict_types=1);
/*
 * This file is part of the WebPush library.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Minishlink\WebPush;

enum ContentEncoding: string
{
    /** Not recommended. Outdated historic encoding. Was used by some browsers before rfc standard. */
    case aesgcm = 'aesgcm';
    /** Defined in rfc8291. */
    case aes128gcm = 'aes128gcm';
}
