<?php declare(strict_types=1);
/*
 * This file is part of the WebPush library.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Jose\Component\KeyManagement\JWKFactory;
use Minishlink\WebPush\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Utils::class)]
final class UtilsTest extends TestCase
{
    public function testSerializePublicKey(): void
    {
        $jwk = JWKFactory::createECKey('P-256');
        $serializedPublicKey = Utils::serializePublicKeyFromJWK($jwk);
        $this->assertEquals(130, Utils::safeStrlen($serializedPublicKey));
    }
}
