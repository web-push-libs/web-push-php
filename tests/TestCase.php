<?php

declare(strict_types = 1);

namespace Minishlink\WebPush\Tests;

use Base64Url\Base64Url;
use Minishlink\WebPush\Authorization;
use Minishlink\WebPush\Contracts\SubscriptionInterface;
use Minishlink\WebPush\Options;
use Minishlink\WebPush\Payload;
use Minishlink\WebPush\Subscription;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class TestCase extends \PHPUnit\Framework\TestCase
{
    use MockeryPHPUnitIntegration;

    public function getOptions(): Options
    {
        return new Options();
    }

    public function getAuthorization(): Authorization
    {
        $subjects = [
            'mailto:foo@bar.com', 'https://foobar.com'
        ];

        return new Authorization(
            'vplfkITvu0cwHqzK9Kj-DYStbCH_9AhGx9LqMyaeI6w',
            'BMBlr6YznhYMX3NgcWIDRxZXs0sh7tCv7_YCsWcww0ZCv9WGg-tRCXfMEHTiBPCksSqeve1twlbmVAZFv7GSuj0',
            $subjects[array_rand($subjects)]
        );
    }

    public function getSubscription(): Subscription
    {
        return new Subscription(
            'https://foo.bar',
            'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4',
            'BTBZMqHH6r4Tts7J_aSIgg',
            'aesgcm'
        );
    }

    public function getPayload(SubscriptionInterface $subscription = null): Payload
    {
        return Payload::create($subscription ?? $this->getSubscription(), 'foobar', 0);
    }
}
