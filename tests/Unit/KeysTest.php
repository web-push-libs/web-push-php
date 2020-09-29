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

namespace Minishlink\Tests\Unit;

use InvalidArgumentException;
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

    /**
     * @test
     */
    public function cannotGetAnUndefinedKey(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Undefined key name "foo"');

        $keys = new Keys();
        $keys->get('foo');
    }
}
