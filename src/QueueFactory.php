<?php

namespace WebPush;

class QueueFactory
{
    private static $queues = [];

    public static function create(string $name = null): Queue
    {
        if (empty($name)) {
            return new Queue();
        }

        if (array_key_exists($name, static::$queues) === false) {
            static::$queues[$name] = new Queue();
        }

        return static::$queues[$name];
    }
}
