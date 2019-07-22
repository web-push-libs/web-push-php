<?php

namespace WebPush\Tests\Unit;

use Jose\Component\Core\Util\Ecc\NistCurve;
use WebPush\Tests\TestCase;
use WebPush\Utils;

final class UtilsTest extends TestCase
{
    public function testSerializePublicKey()
    {
        $curve = NistCurve::curve256();
        $privateKey = $curve->createPrivateKey();
        $publicKey = $curve->createPublicKey($privateKey);
        $serializedPublicKey = Utils::serializePublicKey($publicKey);
        $this->assertEquals(130, Utils::safeStrlen($serializedPublicKey));
    }
}
