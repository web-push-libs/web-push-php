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

namespace Minishlink\Tests\Unit;

use Minishlink\WebPush\Keys;
use PHPUnit\Framework\TestCase;
use function Safe\json_encode;

/**
 * @internal
 */
final class KeysTest extends TestCase
{
    /**
     * @test
     */
    public function keysAreCorrectlyManaged(): void
    {
        $keys = new Keys();
        $keys
            ->set('foo', 'BAR')
        ;

        static::assertTrue($keys->has('foo'));
        static::assertEquals('BAR', $keys->get('foo'));
        static::assertEquals(['foo' => 'BAR'], $keys->all());
        static::assertEquals(['foo'], $keys->list());
        static::assertEquals('{"foo":"BAR"}', json_encode($keys));
        static::assertEquals($keys, Keys::createFromAssociativeArray(['foo' => 'BAR']));
    }
}
