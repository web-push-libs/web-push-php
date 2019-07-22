<?php

declare(strict_types = 1);

namespace WebPush\Tests\Unit;

use Base64Url\Base64Url;
use WebPush\Authorization;
use WebPush\Tests\TestCase;

final class AuthorizationTest extends TestCase
{
    public function testRequiresPrivateKeyOfValidLength(): void
    {
        [, $public, $subject] = $this->getVapidDetails();

        $this->expectExceptionMessage('Invalid private key provided');
        new Authorization('', $public, $subject);
    }

    public function testRequiresPublicKeysOfValidLength(): void
    {
        [$private, , $subject] = $this->getVapidDetails();

        $this->expectExceptionMessage('Invalid public key provided');
        new Authorization($private, '', $subject);
    }

    public function testRequiresSubjectOfEmailOrUrl(): void
    {
        [$private, $public, ] = $this->getVapidDetails();

        $this->expectExceptionMessage('Invalid subject provided');
        new Authorization($private, $public, 'foobar');
    }

    public function testCanBeSafelySerializedAndUnserialized(): void
    {
        $auth = new Authorization(...$this->getVapidDetails());
        $unserialized = unserialize(serialize($auth));
        $this->assertInstanceOf(Authorization::class, $unserialized);
        $this->assertEquals($auth->getPublicKey(), $unserialized->getPublicKey());
        $this->assertEquals($auth->getPrivateKey(), $unserialized->getPrivateKey());
        $this->assertEquals($auth->getSubject(), $unserialized->getSubject());
    }

    private function getVapidDetails(): array
    {
        $authorization = $this->getAuthorization();

        return [
            Base64Url::encode($authorization->getPrivateKey()),
            Base64Url::encode($authorization->getPublicKey()),
            $authorization->getSubject()
        ];
    }
}
