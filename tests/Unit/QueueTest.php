<?php

declare(strict_types  = 1);

namespace WebPush\Tests\Unit;

use WebPush\Queue;
use WebPush\Tests\TestCase;

final class QueueTest extends TestCase
{
    public function testInstantiatesWithADefaultCountOf0(): void
    {
        $this->assertEquals(0, (new Queue())->count());
    }
    public function testAddsItemToTheQueue(): void
    {
        $queue = new Queue();
        $queue->push('foo');
        $queue->push(1);
        $queue->push([]);
        $this->assertEquals(3, $queue->count());
    }
    public function testPopsAnItemTheQueueInFifoAndRemovesIt(): void
    {
        $queue = new Queue();
        $queue->push('foo');
        $queue->push('bar');
        $this->assertEquals('foo', $queue->pop());
        $this->assertEquals(1, $queue->count());
    }

    public function testDeterminesWhetherTheQueueIsEmptyOrNot(): void
    {
        $queue = new Queue();
        $this->assertTrue($queue->isEmpty());
        $this->assertFalse($queue->isNotEmpty());
        $queue->push('foo');
        $this->assertFalse($queue->isEmpty());
        $this->assertTrue($queue->isNotEmpty());
    }
}
