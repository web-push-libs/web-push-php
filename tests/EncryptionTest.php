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

use Minishlink\WebPush\Encryption;
use Minishlink\WebPush\Utils;

class EncryptionTest extends PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider payloadProvider
     *
     * @param string $payload
     * @param int    $maxLengthToPad
     * @param int    $expectedResLength
     */
    public function testPadPayload(string $payload, int $maxLengthToPad, int $expectedResLength)
    {
        $res = Encryption::padPayload($payload, $maxLengthToPad);

        $this->assertContains('test', $res);
        $this->assertEquals($expectedResLength, Utils::safeStrlen($res));
    }

    /**
     * @return array
     */
    public function payloadProvider(): array
    {
        return [
            ['testé', 0, 8],
            ['testé', 1, 8],
            ['testé', 6, 8],
            ['testé', 7, 9],
            ['testé', Encryption::MAX_COMPATIBILITY_PAYLOAD_LENGTH, Encryption::MAX_COMPATIBILITY_PAYLOAD_LENGTH + 2],
            ['testé', Encryption::MAX_PAYLOAD_LENGTH, Encryption::MAX_PAYLOAD_LENGTH + 2],
            [str_repeat('test', 1019).'te', Encryption::MAX_PAYLOAD_LENGTH, Encryption::MAX_PAYLOAD_LENGTH + 2],
        ];
    }
}
