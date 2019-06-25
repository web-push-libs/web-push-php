<?php

namespace Minishlink\WebPush;

class CacheFactory
{
    private static $caches = [];

    public static function create(string $name = null): Cache
    {
        if (empty($name)) {
            return new Cache();
        }

        if (array_key_exists($name, static::$caches) === false) {
            static::$caches[$name] = new Cache();
        }

        return static::$caches[$name];
    }
}
