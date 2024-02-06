<?php declare(strict_types=1);

use Minishlink\WebPush\ContentEncoding;
use Minishlink\WebPush\Subscription;

/**
 * @covers \Minishlink\WebPush\Subscription
 */
class SubscriptionTest extends PHPUnit\Framework\TestCase
{
    /**
     * Throw exception on outdated call.
     */
    public function testCreateMinimal(): void
    {
        $this->expectException(ValueError::class);
        $subscriptionArray = [
            "endpoint" => "http://toto.com",
        ];
        Subscription::create($subscriptionArray);
    }

    /**
     * Throw exception on outdated call.
     */
    public function testConstructMinimal(): void
    {
        $this->expectException(ArgumentCountError::class);
        new Subscription("http://toto.com");
    }
    public function testExceptionEmpty(): void
    {
        $this->expectException(ValueError::class);
        new Subscription("", "", "");
    }
    public function testExceptionEmptyKey(): void
    {
        $this->expectException(ValueError::class);
        $subscriptionArray = [
            "endpoint" => "http://toto.com",
            "publicKey" => "",
            "authToken" => "authToken",
        ];
        Subscription::create($subscriptionArray);
    }
    public function testExceptionEmptyToken(): void
    {
        $this->expectException(ValueError::class);
        $subscriptionArray = [
            "endpoint" => "http://toto.com",
            "publicKey" => "publicKey",
            "authToken" => "",
        ];
        Subscription::create($subscriptionArray);
    }

    public function testCreatePartial(): void
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
        $this->assertEquals(ContentEncoding::aes128gcm, $subscription->getContentEncoding());
    }

    public function testConstructPartial(): void
    {
        $subscription = new Subscription("http://toto.com", "publicKey", "authToken");
        $this->assertEquals("http://toto.com", $subscription->getEndpoint());
        $this->assertEquals("publicKey", $subscription->getPublicKey());
        $this->assertEquals("authToken", $subscription->getAuthToken());
        $this->assertEquals(ContentEncoding::aes128gcm, $subscription->getContentEncoding());
    }

    public function testCreateFull(): void
    {
        $subscriptionArray = [
            "endpoint" => "http://toto.com",
            "publicKey" => "publicKey",
            "authToken" => "authToken",
            "contentEncoding" => ContentEncoding::aes128gcm,
        ];
        $subscription = Subscription::create($subscriptionArray);
        $this->assertEquals("http://toto.com", $subscription->getEndpoint());
        $this->assertEquals("publicKey", $subscription->getPublicKey());
        $this->assertEquals("authToken", $subscription->getAuthToken());
        $this->assertEquals(ContentEncoding::aes128gcm, $subscription->getContentEncoding());

        // Test with type string contentEncoding
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
        $this->assertEquals(ContentEncoding::aes128gcm, $subscription->getContentEncoding());
    }

    public function testConstructFull(): void
    {
        $subscription = new Subscription("http://toto.com", "publicKey", "authToken", ContentEncoding::aes128gcm);
        $this->assertEquals("http://toto.com", $subscription->getEndpoint());
        $this->assertEquals("publicKey", $subscription->getPublicKey());
        $this->assertEquals("authToken", $subscription->getAuthToken());
        $this->assertEquals(ContentEncoding::aes128gcm, $subscription->getContentEncoding());

        // Test with type string contentEncoding
        $subscription = new Subscription("http://toto.com", "publicKey", "authToken", "aesgcm");
        $this->assertEquals("http://toto.com", $subscription->getEndpoint());
        $this->assertEquals("publicKey", $subscription->getPublicKey());
        $this->assertEquals("authToken", $subscription->getAuthToken());
        $this->assertEquals(ContentEncoding::aesgcm, $subscription->getContentEncoding());
    }
    public function testCreatePartialWithNewStructure(): void
    {
        $subscription = Subscription::create([
            "endpoint" => "http://toto.com",
            "keys" => [
                'p256dh' => 'publicKey',
                'auth' => 'authToken',
            ],
        ]);
        $this->assertEquals("http://toto.com", $subscription->getEndpoint());
        $this->assertEquals("publicKey", $subscription->getPublicKey());
        $this->assertEquals("authToken", $subscription->getAuthToken());
        $this->assertEquals(ContentEncoding::aes128gcm, $subscription->getContentEncoding());
    }

    public function testCreatePartialWithNewStructureAndContentEncoding(): void
    {
        $subscription = Subscription::create([
            "endpoint" => "http://toto.com",
            "contentEncoding" => 'aes128gcm',
            "keys" => [
                'p256dh' => 'publicKey',
                'auth' => 'authToken',
            ],
        ]);
        $this->assertEquals("http://toto.com", $subscription->getEndpoint());
        $this->assertEquals("publicKey", $subscription->getPublicKey());
        $this->assertEquals("authToken", $subscription->getAuthToken());
        $this->assertEquals(ContentEncoding::aes128gcm, $subscription->getContentEncoding());
    }
}
