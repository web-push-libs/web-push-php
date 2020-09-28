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

namespace Minishlink\WebPush\VAPID;

class Header
{
    private string $token;
    private string $key;

    public function __construct(string $token, string $key)
    {
        $this->token = $token;
        $this->key = $key;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getKey(): string
    {
        return $this->key;
    }
}
