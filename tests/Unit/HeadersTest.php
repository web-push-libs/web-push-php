<?php

declare(strict_types = 1);

namespace Minishlink\WebPush\Tests\Unit;

use InvalidArgumentException;
use Minishlink\WebPush\Headers;
use Minishlink\WebPush\Tests\TestCase;
use stdClass;

final class HeadersTest extends TestCase
{
    public function testOptionallyAcceptsArrayAsInitialHeadersValue(): void
    {
        $headers = new Headers(['name' => 'value', 'other' => ['value']]);
        $this->assertEquals(['name' => 'value', 'other' => ['value']], $headers->toArray());
    }

    public function testAddsHeadersWithScalarAndArrayValues(): void
    {
        $headers = new Headers();
        $headers->set('name', 'value');
        $headers->set('other', ['value']);
        $headers->set('number', 0);
        $this->assertEquals(['name' => 'value', 'other' => ['value'], 'number' => 0], $headers->toArray());
        $this->expectException(InvalidArgumentException::class);
        $headers->add(['foo' => new stdClass()]);
    }

    public function testRemovesHeadersByName(): void
    {
        $headers = new Headers(['name' => 'value']);
        $headers->remove('name');
        $this->assertEmpty($headers->toArray());
    }

    public function testAppliesMissingHeadersFromAnArrayOrAnotherHeadersObject(): void
    {
        $default = new Headers(['default' => 'default']);
        $additional = new Headers(['default' => 'additional', 'additional' => 'additional']);

        $this->assertEquals(
            ['default' => 'default', 'additional' => 'additional'],
            $default->with($additional)->toArray()
        );
    }

    public function testAddsAnArrayOfHeadersReplacingDuplicateValues(): void
    {
        $headers = new Headers(['name' => 'value', 'persist' => 'persist']);
        $headers->add(['name' => 'new', 'new' => 'new']);
        $this->assertEquals(['name' => 'new', 'persist' => 'persist', 'new' => 'new'], $headers->toArray());
    }

    public function testGetsHeaderValuesByName(): void
    {
        $headers = new Headers(['name' => 'value']);
        $this->assertEquals('value', $headers->get('name'));
    }

    public function testAppendsValuesToAnExistingHeaderOrSetsIt(): void
    {
        $headers = new Headers(['name' => 'value']);
        $headers->append('name', 'appendme');
        $this->assertEquals('valueappendme', $headers->get('name'));
    }

    public function testAppendsValuesToAnExistingHeaderWithAnOptionalDelimiter(): void
    {
        $headers = new Headers(['name' => 'value']);
        $headers->append('name', 'appendme', ':');
        $this->assertEquals('value:appendme', $headers->get('name'));
    }

    public function testIsIterable(): void
    {
        $array = ['first' => 'foo', 'second' => 'bar', 'third' => 'baz'];
        $headers = new Headers($array);

        foreach ($headers as $key => $value) {
            $this->assertEquals($array[$key], $value);
        }
    }

    public function testRaisesAnExceptionIfTheKeyDoesNotExist(): void
    {
        $headers = new Headers();
        $this->expectExceptionMessage('No value has been set for header with name "foo"');
        $headers->get('foo', false);
    }
}
