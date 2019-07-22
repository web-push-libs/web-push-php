<?php

declare(strict_types = 1);

namespace WebPush\Tests\Unit;

use WebPush\CacheFactory;
use WebPush\Tests\TestCase;

final class CacheFactoryTest extends TestCase
{
    public function testReusesNamedInstances(): void
    {
        $original = CacheFactory::create('foo');
        $clone = CacheFactory::create('foo');
        $original->set('foo', 'bar');
        $this->assertEquals($clone->get('foo'), 'bar');
    }

    public function testCanMaintainMultipleUniqueNamedInstances(): void
    {
        $one = CacheFactory::create('one');
        $two = CacheFactory::create('two');
        $one->set('foo', 'bar');
        $this->assertFalse($two->has('foo'));
    }
}
