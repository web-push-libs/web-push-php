<?php declare(strict_types=1);

use Jose\Component\KeyManagement\JWKFactory;
use Minishlink\WebPush\Utils;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Minishlink\WebPush\Utils
 */
final class UtilsTest extends TestCase
{
    public function testSerializePublicKey(): void
    {
        $jwk = JWKFactory::createECKey('P-256');
        $serializedPublicKey = Utils::serializePublicKeyFromJWK($jwk);
        $this->assertEquals(130, Utils::safeStrlen($serializedPublicKey));
    }
}
