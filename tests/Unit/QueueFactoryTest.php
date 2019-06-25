<?php

declare(strict_types = 1);

namespace Minishlink\WebPush\Tests\Unit;

use Minishlink\WebPush\CacheFactory;
use Minishlink\WebPush\QueueFactory;
use Minishlink\WebPush\Tests\TestCase;

final class QueueFactoryTest extends TestCase
{
    public function testReusesNamedInstances(): void
    {
        $original = QueueFactory::create('foo');
        $clone = QueueFactory::create('foo');
        $original->push('foobar');
        $this->assertEquals($clone->pop(), 'foobar');
    }

    public function testCanMaintainMultipleUniqueNamedInstances(): void
    {
        $one = QueueFactory::create('one');
        $two = QueueFactory::create('two');
        $one->push('foo');
        $this->assertEquals(0, $two->count());
    }
}
