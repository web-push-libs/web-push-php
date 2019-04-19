<?php

use Minishlink\WebPush\Subscription;

class SubscriptionTest extends PHPUnit\Framework\TestCase
{

    public function testCreateMinimal()
    {
        $subscriptionArray = array(
            "endpoint" => "http://toto.com"
        );
        $subscription = Subscription::create($subscriptionArray);
        $this->assertEquals("http://toto.com", $subscription->getEndpoint());
        $this->assertEquals(null, $subscription->getPublicKey());
        $this->assertEquals(null, $subscription->getAuthToken());
        $this->assertEquals(null, $subscription->getContentEncoding());
        $this->assertEquals(null, $subscription->getSharedSecret());
    }

    public function testConstructMinimal()
    {
        $subscription = new Subscription("http://toto.com");
        $this->assertEquals("http://toto.com", $subscription->getEndpoint());
        $this->assertEquals(null, $subscription->getPublicKey());
        $this->assertEquals(null, $subscription->getAuthToken());
        $this->assertEquals(null, $subscription->getContentEncoding());
        $this->assertEquals(null, $subscription->getSharedSecret());
    }

    public function testCreatePartial()
    {
        $subscriptionArray = array(
            "endpoint" => "http://toto.com",
            "publicKey" => "publicKey",
            "authToken" => "authToken",
        );
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
        $subscriptionArray = array(
            "endpoint" => "http://toto.com",
            "publicKey" => "publicKey",
            "authToken" => "authToken",
            "contentEncoding" => "aes128gcm",
            "sharedSecret" => "sharedSecret",
        );
        $subscription = Subscription::create($subscriptionArray);
        $this->assertEquals("http://toto.com", $subscription->getEndpoint());
        $this->assertEquals("publicKey", $subscription->getPublicKey());
        $this->assertEquals("authToken", $subscription->getAuthToken());
        $this->assertEquals("aes128gcm", $subscription->getContentEncoding());
        $this->assertEquals("sharedSecret", $subscription->getSharedSecret());
    }

    public function testConstructFull()
    {
        $subscription = new Subscription("http://toto.com", "publicKey", "authToken", "aes128gcm", "sharedSecret");
        $this->assertEquals("http://toto.com", $subscription->getEndpoint());
        $this->assertEquals("publicKey", $subscription->getPublicKey());
        $this->assertEquals("authToken", $subscription->getAuthToken());
        $this->assertEquals("aes128gcm", $subscription->getContentEncoding());
        $this->assertEquals("sharedSecret", $subscription->getSharedSecret());
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

    public function testCreatePartialWithNewStructureAndSharedSecret()
    {
        $subscription = Subscription::create([
            "endpoint" => "http://toto.com",
            "sharedSecret" => 'sharedSecret',
            "keys" => [
                'p256dh' => 'publicKey',
                'auth' => 'authToken'
            ]
        ]);
        $this->assertEquals("http://toto.com", $subscription->getEndpoint());
        $this->assertEquals("publicKey", $subscription->getPublicKey());
        $this->assertEquals("authToken", $subscription->getAuthToken());
        $this->assertEquals("sharedSecret", $subscription->getSharedSecret());
    }
}
