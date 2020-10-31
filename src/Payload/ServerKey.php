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

namespace Minishlink\WebPush\Payload;

use Assert\Assertion;

class ServerKey
{
    private const PUBLIC_KEY_SIZE = 65;
    private const PRIVATE_KEY_SIZE = 32;

    private string $publicKey;
    private string $privateKey;

    public function __construct(string $publicKey, string $privateKey)
    {
        Assertion::length($publicKey, self::PUBLIC_KEY_SIZE, 'Invalid public key length', null, '8bit');
        Assertion::length($privateKey, self::PRIVATE_KEY_SIZE, 'Invalid private key length', null, '8bit');
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }
}
