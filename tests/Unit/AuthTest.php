<?php

declare(strict_types = 1);

namespace Minishlink\WebPush\Tests\Unit;

use InvalidArgumentException;
use Minishlink\WebPush\Auth;
use Minishlink\WebPush\Tests\TestCase;

final class AuthTest extends TestCase
{
    public function testRequiresPrivateKeyOfValidLength(): void
    {
        [, $public, $subject] = $this->getVapidDetails();

        $this->expectException(InvalidArgumentException::class);
        new Auth('', $public, $subject);
    }

    public function testRequiresPublicKeysOfValidLength(): void
    {
        [$private, , $subject] = $this->getVapidDetails();

        $this->expectException(InvalidArgumentException::class);
        new Auth($private, '', $subject);
    }

    public function testRequiresSubjectOfEmailOrUrl(): void
    {
        [$private, $public, ] = $this->getVapidDetails();

        $this->expectException(InvalidArgumentException::class);
        new Auth($private, $public, 'foobar');
    }

    private function getVapidDetails(): array
    {
        $subjects = [
            'mailto:foo@bar.com', 'https://foobar.com'
        ];

        return [
            random_bytes(32), random_bytes(64), $subjects[array_rand($subjects)]
        ];
    }
}
