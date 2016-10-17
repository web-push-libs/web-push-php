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
     */
    public function testPadPayload($payload)
    {
        $res = Encryption::padPayload($payload, true);

        $this->assertContains('test', $res);
        $this->assertEquals(4080, Utils::safeStrlen($res));
    }

    public function payloadProvider()
    {
        return array(
            array('testé'),
            array(str_repeat('test', 1019)),
            array(str_repeat('test', 1019).'te'),
        );
    }
}
