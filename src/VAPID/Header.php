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
