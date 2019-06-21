<?php

namespace Minishlink\WebPush\Tests\Unit;

use ErrorException;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\Tests\TestCase;

final class SubscriptionTest extends TestCase
{
    public function testCreateMinimal()
    {
        $subscriptionArray = [
            "endpoint" => "http://toto.com"
        ];
        $subscription = Subscription::create($subscriptionArray);
        $this->assertEquals("http://toto.com", $subscription->getEndpoint());
        $this->assertEquals(null, $subscription->getPublicKey());
        $this->assertEquals(null, $subscription->getAuthToken());
        $this->assertEquals('aesgcm', $subscription->getContentEncoding());
    }

    public function testConstructMinimal()
    {
        $subscription = new Subscription("http://toto.com");
        $this->assertEquals("http://toto.com", $subscription->getEndpoint());
        $this->assertEquals(null, $subscription->getPublicKey());
        $this->assertEquals(null, $subscription->getAuthToken());
        $this->assertEquals('aesgcm', $subscription->getContentEncoding());
    }

    public function testCreatePartial()
    {
        $subscriptionArray = [
            "endpoint" => "http://toto.com",
            "publicKey" => "publicKey",
            "authToken" => "authToken",
        ];
        $subscription = Subscription::create($subscriptionArray);
        $this->assertEquals("http://toto.com", $subscription->getEndpoint());
        $this->assertEquals("publicKey", $subscription->getPublicKey());
        $this->assertEquals("authToken", $subscription->getAuthToken());
        $this->assertEquals("aesgcm", $subscription->getContentEncoding());
    }

    public function testConstructPartial()
    {
        $subscription = new Subscription("http://toto.com", "publicKey", "authToken");
        $this->assertEquals("http://toto.com", $subscription->getEndpoint());
        $this->assertEquals("publicKey", $subscription->getPublicKey());
        $this->assertEquals("authToken", $subscription->getAuthToken());
        $this->assertEquals("aesgcm", $subscription->getContentEncoding());
    }

    public function testCreateFull()
    {
        $subscriptionArray = [
            "endpoint" => "http://toto.com",
            "publicKey" => "publicKey",
            "authToken" => "authToken",
            "contentEncoding" => "aes128gcm",
        ];
        $subscription = Subscription::create($subscriptionArray);
        $this->assertEquals("http://toto.com", $subscription->getEndpoint());
        $this->assertEquals("publicKey", $subscription->getPublicKey());
        $this->assertEquals("authToken", $subscription->getAuthToken());
        $this->assertEquals("aes128gcm", $subscription->getContentEncoding());
    }

    public function testConstructFull()
    {
        $subscription = new Subscription("http://toto.com", "publicKey", "authToken", "aes128gcm");
        $this->assertEquals("http://toto.com", $subscription->getEndpoint());
        $this->assertEquals("publicKey", $subscription->getPublicKey());
        $this->assertEquals("authToken", $subscription->getAuthToken());
        $this->assertEquals("aes128gcm", $subscription->getContentEncoding());
    }

    public function testCreatePartialWithNewStructure()
    {
        $subscription = Subscription::create([
            "endpoint" => "http://toto.com",
            "keys" => [
                'p256dh' => 'publicKey',
                'auth' => 'authToken'
            ]
        ]);
        $this->assertEquals("http://toto.com", $subscription->getEndpoint());
        $this->assertEquals("publicKey", $subscription->getPublicKey());
        $this->assertEquals("authToken", $subscription->getAuthToken());
    }

    public function testCreatePartialWithNewStructureAndContentEncoding()
    {
        $subscription = Subscription::create([
            "endpoint" => "http://toto.com",
            "contentEncoding" => 'aes128gcm',
            "keys" => [
                'p256dh' => 'publicKey',
                'auth' => 'authToken'
            ]
        ]);
        $this->assertEquals("http://toto.com", $subscription->getEndpoint());
        $this->assertEquals("publicKey", $subscription->getPublicKey());
        $this->assertEquals("authToken", $subscription->getAuthToken());
        $this->assertEquals("aes128gcm", $subscription->getContentEncoding());
    }

    /**
     * @dataProvider providesServices
     *
     * @param string $service
     * @param string $url
     *
     * @throws ErrorException
     */
    public function testDeterminesServiceProvider($service, $url): void
    {
        $subscription = Subscription::create([
            'endpoint' => $url,
            'contentEncoding' => 'aes128gcm',
            'keys' => [
                'p256dh' => 'publicKey',
                'auth' => 'authToken'
            ]
        ]);

        $this->assertEquals($service, $subscription->getServiceName());
    }

    public function providesServices(): array
    {
        return [
            'GCM' => ['GCM', 'https://android.googleapis.com'],
            'FCM' => ['FCM', 'https://fcm.googleapis.com'],
            'Unknown' => ['', 'https://foo.bar']
        ];
    }


}
