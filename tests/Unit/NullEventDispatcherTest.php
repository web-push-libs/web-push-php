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
