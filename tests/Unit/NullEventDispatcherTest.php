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

use Minishlink\WebPush\NullEventDispatcher;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 */
final class NullEventDispatcherTest extends TestCase
{
    /**
     * @test
     */
    public function invalidInputCannotBeLoaded(): void
    {
        $dispatcher = new NullEventDispatcher();

        $object = new stdClass();
        static::assertSame($object, $dispatcher->dispatch($object));
    }
}
