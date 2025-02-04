<?php declare(strict_types=1);

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
