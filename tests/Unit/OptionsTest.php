<?php

declare(strict_types=1);

namespace Minishlink\WebPush\Tests\Unit;

use Minishlink\WebPush\Options;
use Minishlink\WebPush\Tests\TestCase;

final class OptionsTest extends TestCase
{
    public function testHasDefaultOptions(): void
    {
        $expected = ['ttl' => 2419200];
        $this->assertEquals($expected, (new Options())->toArray());
    }

    public function testDoesNotReturnNullOptions(): void
    {
        $null = ['urgent', 'topic'];
        $array = (new Options(['ttl' => 2419200, 'urgency' => null, 'topic' => null]))->toArray();
        foreach ($null as $option) {
            $this->assertArrayNotHasKey($option, $array);
        }
    }

    public function testCanBeMergedWithAdditionalOptions(): void
    {
        $original = new Options(['ttl' => 10000, 'urgency' => null, 'topic' => 'foo_topic']);
        $additional = new Options(['ttl' => 2419200, 'urgency' => 'high', 'topic' => 'additional_topic']);

        $expected = [
            'ttl' => 10000, 'urgency' => 'high', 'topic' => 'foo_topic'
        ];

        $this->assertEquals($expected, $original->with($additional)->toArray());
    }
}
