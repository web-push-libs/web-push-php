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

namespace Minishlink\Tests\Functional\VAPID;

use Minishlink\WebPush\VAPID\Header;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class HeaderTest extends TestCase
{
    /**
     * @test
     */
    public function createHeader(): void
    {
        $header = new Header('token', 'key');

        static::assertEquals('token', $header->getToken());
        static::assertEquals('key', $header->getKey());
    }
}
