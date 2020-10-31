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

namespace Minishlink\Tests\Unit\Payload;

use InvalidArgumentException;
use Minishlink\WebPush\Payload\ServerKey;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ServerKeyTest extends TestCase
{
    /**
     * @test
     */
    public function invalidPublicKeyLength(): void
    {
        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage('Invalid public key length');

        new ServerKey('', '');
    }

    /**
     * @test
     */
    public function invalidPrivateKeyLength(): void
    {
        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage('Invalid private key length');

        $fakePublicKey = str_pad('', 65, '-');

        new ServerKey($fakePublicKey, '');
    }
}
