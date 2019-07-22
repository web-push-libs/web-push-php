<?php

declare(strict_types = 1);

namespace WebPush\Tests\Unit;

use WebPush\CacheFactory;
use WebPush\QueueFactory;
use WebPush\Tests\TestCase;

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
