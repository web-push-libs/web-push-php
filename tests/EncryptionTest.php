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

class EncryptionTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider payloadProvider
     *
     * @param string $payload
     */
    public function testAutomaticPadding($payload)
    {
        $res = Encryption::automaticPadding($payload);

        $this->assertContains('test', $res);
        $this->assertEquals(4078, strlen($res));
    }

    public function payloadProvider()
    {
        return array(
            array('test'),
            array(str_repeat('test', 1019)),
            array(str_repeat('test', 1019).'te'),
        );
    }
}
