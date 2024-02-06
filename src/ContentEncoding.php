<?php

namespace Minishlink\WebPush;

enum ContentEncoding: string
{
    /** Outdated historic encoding. Was used by some browsers before rfc standard. Not recommended. */
    case aesgcm = "aesgcm";
    /** Defined in rfc8291. */
    case aes128gcm = "aes128gcm";
}
