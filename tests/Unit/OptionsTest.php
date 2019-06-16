<?php

namespace Minishlink\WebPush\Tests\Unit;

use Minishlink\WebPush\Options;
use Minishlink\WebPush\Tests\TestCase;

final class OptionsTest extends TestCase
{
    public function testHasDefaultOptions(): void
    {
        $expected = ['TTL' => 2419200, 'batch_size' => 1000];
        $this->assertEquals($expected, (new Options())->toArray());
        $this->assertEquals($expected, (new Options(null, null, null, null))->toArray());
    }

    public function testDoesNotReturnNullOptions(): void
    {
        $null = ['urgent', 'topic'];
        $array = (new Options(2419200, null, null, 1000))->toArray();
        foreach ($null as $option) {
            $this->assertArrayNotHasKey($option, $array);
        }
    }

    public function testCanBeMergedWithAdditionalOptions(): void
    {
        $original = new Options(10000, null, 'foo_topic', 10);
        $additional = new Options(2419200, 'high', 'additional_topic', null);

        $expected = [
            'TTL' => 10000, 'urgency' => 'high', 'topic' => 'foo_topic', 'batch_size' => 10
        ];

        $this->assertEquals($expected, $original->with($additional)->toArray());
    }

    public function testCanBeInstantiatedFromArray(): void
    {
        $this->assertInstanceOf(Options::class, Options::fromArray([]));
    }
}
