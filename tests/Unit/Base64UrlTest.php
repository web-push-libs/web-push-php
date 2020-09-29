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

use Minishlink\WebPush\Base64Url;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class Base64UrlTest extends TestCase
{
    /**
     * @dataProvider getTestVectors
     *
     * @test
     */
    public function encodeAndDecode(string $message, string $expectedResult): void
    {
        $encoded = Base64Url::encode($message);
        $decoded = Base64Url::decode($expectedResult);

        static::assertEquals($expectedResult, $encoded);
        static::assertEquals($message, $decoded);
    }

    /**
     * @see https://tools.ietf.org/html/rfc4648#section-10
     */
    public function getTestVectors(): array
    {
        return [
            [
                '000000', 'MDAwMDAw',
            ],
            [
                "\0\0\0\0", 'AAAAAA',
            ],
            [
                "\xff", '_w',
            ],
            [
                "\xff\xff", '__8',
            ],
            [
                "\xff\xff\xff", '____',
            ],
            [
                "\xff\xff\xff\xff", '_____w',
            ],
            [
                "\xfb", '-w',
            ],
            [
                '', '',
            ],
            [
                'f', 'Zg',
            ],
            [
                'fo', 'Zm8',
            ],
        ];
    }

    /**
     * @dataProvider getTestBadVectors
     *
     * @test
     */
    public function badInput(string $input): void
    {
        $decoded = Base64Url::decode($input);
        static::assertEquals("\00", $decoded);
    }

    public function getTestBadVectors(): array
    {
        return [
            [
                ' AA',
            ],
            [
                "\tAA",
            ],
            [
                "\rAA",
            ],
            [
                "\nAA",
            ],
        ];
    }

    public function getTestNonsenseVectors(): array
    {
        return [
            [
                'cxr0fdsezrewklerewxoz423ocfsa3bw432yjydsa9lhdsalw',
            ],
        ];
    }
}
