<?php

declare(strict_types = 1);

namespace Minishlink\WebPush\Tests\Unit;

use Minishlink\WebPush\Cache;
use Minishlink\WebPush\Tests\TestCase;

final class CacheTest extends TestCase
{
    public function testDeterminesWhetherAnItemIsNotInTheCache(): void
    {
        $this->assertFalse((new Cache())->has('foo'));
    }

    public function testSetsAndGetsItemsInTheCache(): void
    {
        $cache = new Cache();
        $cache->set('key', 'value');
        $this->assertTrue($cache->has('key'));
    }

    public function testGetsItemsFromTheCacheIfTheyExist(): void
    {
        $cache = new Cache();
        $cache->set('foo', 'bar');
        $this->assertEquals('bar', $cache->get('foo'));
    }

    public function testReceivesANullResponseIfAnItemDoesNotExistInTheCache(): void
    {
        $cache = new Cache();
        $this->assertNull($cache->get('foo'));
    }

    public function testClearsTheCache(): void
    {
        $cache = new Cache();
        $cache->set('foo', 'bar');
        $cache->clear();
        $this->assertNull($cache->get('foo'));
    }

    public function testInitializesInADisabledState(): void
    {
        $cache = new Cache();
        $this->assertFalse($cache->isEnabled());
    }

    public function testEnablesTheCache(): void
    {
        $cache = new Cache();
        $cache->enable();
        $this->assertTrue($cache->isEnabled());
    }

    public function testDisablesTheCache(): void
    {
        $cache = new Cache();
        $cache->enable();
        $cache->disable();
        $this->assertFalse($cache->isEnabled());
    }

    public function testEvaluatesACallbackAsAKeyValue(): void
    {
        $cache = new Cache();
        $cache->set('foo', static function () {
            return 'bar';
        });
        $this->assertEquals('bar', $cache->get('foo'));
    }

    public function testAttemptsToFetchAnItemFromTheCacheAndStoresItOtherwise(): void
    {
        $cache = new Cache();
        $this->assertEquals('bar', $cache->remember('foo', static function () {
            return 'bar';
        }));
        $this->assertEquals('bar', $cache->remember('foo', static function () {
            return 'foobar';
        }));
    }
}
