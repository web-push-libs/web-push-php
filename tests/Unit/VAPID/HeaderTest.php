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

namespace Minishlink\Tests\Unit\VAPID;

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
