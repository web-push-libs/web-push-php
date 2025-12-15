<?php declare(strict_types=1);

namespace Minishlink\WebPush;

enum ContentEncoding: string
{
    /** Not recommended. Outdated historic encoding. Was used by some browsers before rfc standard. */
    case aesgcm = "aesgcm";
    /** Defined in rfc8291. */
    case aes128gcm = "aes128gcm";
}
