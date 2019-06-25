<?php

namespace Minishlink\WebPush\Tests\Unit;

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\Tests\TestCase;

final class SubscriptionTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $subscription = new Subscription('http://toto.com', 'public_key', 'auth_token', 'aesgcm');
        $this->assertEquals('http://toto.com', $subscription->getEndpoint());
        $this->assertEquals('public_key', $subscription->getPublicKey());
        $this->assertEquals('auth_token', $subscription->getAuthToken());
        $this->assertEquals('aesgcm', $subscription->getEncoding());
    }

    public function testInitializesWithDefaultEncodingOfAesGcm(): void
    {
        $subscription = $this->getSubscription();

        $this->assertEquals('aesgcm', (new Subscription(
            $subscription->getEndpoint(),
            $subscription->getPublicKey(),
            $subscription->getAuthToken()
        ))->getEncoding());
    }
}
