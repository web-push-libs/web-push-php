<?php

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

class EncryptionTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider payloadProvider
     *
     * @param string $payload
     * @param integer $maxLengthToPad
     * @param integer $expectedResLength
     */
    public function testPadPayload($payload, $maxLengthToPad, $expectedResLength)
    {
        $res = Encryption::padPayload($payload, $maxLengthToPad);

        $this->assertContains('test', $res);
        $this->assertEquals($expectedResLength, Utils::safeStrlen($res));
    }

    public function payloadProvider()
    {
        return array(
            array('testé', 0, 8),
            array('testé', 1, 8),
            array('testé', 6, 8),
            array('testé', 7, 9),
            array('testé', Encryption::MAX_COMPATIBILITY_PAYLOAD_LENGTH, Encryption::MAX_COMPATIBILITY_PAYLOAD_LENGTH + 2),
            array('testé', Encryption::MAX_PAYLOAD_LENGTH, Encryption::MAX_PAYLOAD_LENGTH + 2),
            array(str_repeat('test', 1019).'te', Encryption::MAX_PAYLOAD_LENGTH, Encryption::MAX_PAYLOAD_LENGTH + 2),
        );
    }
}
