<?php

namespace Minishlink\WebPush;

use InvalidArgumentException;

class SubscriptionFactory
{
    public static function create(array $array): Subscription
    {
        if (array_key_exists('keys', $array) && is_array($array['keys'])) {
            return new Subscription(
                $array['endpoint'],
                $array['keys']['p256dh'],
                $array['keys']['auth'],
                $array['encoding'] ?? 'aesgcm'
            );
        }

        if (array_key_exists('public_key', $array)
            || array_key_exists('auth_token', $array)
            || array_key_exists('encoding', $array)
        ) {
            return new Subscription(
                $array['endpoint'],
                $array['public_key'],
                $array['auth_token'],
                $array['encoding'] ?? 'aesgcm'
            );
        }

        throw new InvalidArgumentException('Unable to create Subscription from the parameters provided.');
    }
}
