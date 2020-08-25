<?php

use Jose\Component\Core\Util\Ecc\NistCurve;
use Jose\Component\KeyManagement\JWKFactory;
use Minishlink\WebPush\Utils;
use PHPUnit\Framework\TestCase;

final class UtilsTest extends TestCase
{

    public function testSerializePublicKey()
    {
        $jwk = JWKFactory::createECKey('P-256');
        $serializedPublicKey = Utils::serializePublicKeyFromJWK($jwk);
        $this->assertEquals(130, Utils::safeStrlen($serializedPublicKey));
    }

}
