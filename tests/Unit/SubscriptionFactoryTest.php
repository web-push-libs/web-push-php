<?php

declare(strict_types = 1);

namespace WebPush\Tests\Unit;

use WebPush\SubscriptionFactory;
use WebPush\Tests\TestCase;

class SubscriptionFactoryTest extends TestCase
{
    public function testCreatesASubscriptionFromAVapidPublicKeyAndAuthToken(): void
    {
        $array = [
            'endpoint' => 'http://toto.com',
            'encoding' => 'aesgcm',
            'public_key' => 'public_key',
            'auth_token' => 'auth_token',
        ];

        $subscription = SubscriptionFactory::create($array);

        $this->assertEquals('http://toto.com', $subscription->getEndpoint());
        $this->assertEquals('public_key', $subscription->getPublicKey());
        $this->assertEquals('auth_token', $subscription->getAuthToken());
        $this->assertEquals('aesgcm', $subscription->getEncoding());
    }

    public function testCreatesASubscriptionFromTheNewlyProposedKeyStructure(): void
    {
        $array = [
            'endpoint' => 'http://toto.com',
            'encoding' => 'aes128gcm',
            'keys' => [
                'p256dh' => 'public_key',
                'auth' => 'auth_token'
            ]
        ];

        $subscription = SubscriptionFactory::create($array);

        $this->assertEquals('http://toto.com', $subscription->getEndpoint());
        $this->assertEquals('public_key', $subscription->getPublicKey());
        $this->assertEquals('auth_token', $subscription->getAuthToken());
        $this->assertEquals('aes128gcm', $subscription->getEncoding());
    }

    public function testCreatesWithADefaultEncodingValueOfAesgcm(): void
    {
        $array = [
            'endpoint' => 'http://toto.com',
            'public_key' => 'public_key',
            'auth_token' => 'auth_token',
        ];

        $this->assertEquals('aesgcm', SubscriptionFactory::create($array)->getEncoding());
    }
}
