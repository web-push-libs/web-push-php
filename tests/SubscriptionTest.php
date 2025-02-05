<?php declare(strict_types=1);

use Minishlink\WebPush\ContentEncoding;
use Minishlink\WebPush\Subscription;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Subscription::class)]
class SubscriptionTest extends PHPUnit\Framework\TestCase
{
    public function testCreateMinimal(): void
    {
        $subscriptionArray = [
            "endpoint" => "http://toto.com",
        ];
        $subscription = Subscription::create($subscriptionArray);
        $this->assertEquals("http://toto.com", $subscription->getEndpoint());
        $this->assertNull($subscription->getPublicKey());
        $this->assertNull($subscription->getAuthToken());
        $this->assertNull($subscription->getContentEncoding());
        $this->assertNull($subscription->getContentEncodingTyped());
    }

    public function testConstructMinimal(): void
    {
        $subscription = new Subscription("http://toto.com");
        $this->assertEquals("http://toto.com", $subscription->getEndpoint());
        $this->assertNull($subscription->getPublicKey());
        $this->assertNull($subscription->getAuthToken());
        $this->assertNull($subscription->getContentEncoding());
        $this->assertNull($subscription->getContentEncodingTyped());
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
        $this->assertEquals("aesgcm", $subscription->getContentEncoding());
        $this->assertSame(ContentEncoding::aesgcm, $subscription->getContentEncodingTyped());
    }

    public function testConstructPartial(): void
    {
        $subscription = new Subscription("http://toto.com", "publicKey", "authToken");
        $this->assertEquals("http://toto.com", $subscription->getEndpoint());
        $this->assertEquals("publicKey", $subscription->getPublicKey());
        $this->assertEquals("authToken", $subscription->getAuthToken());
        $this->assertSame("aesgcm", $subscription->getContentEncoding());
        $this->assertSame(ContentEncoding::aesgcm, $subscription->getContentEncodingTyped());
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
        $this->assertSame("aes128gcm", $subscription->getContentEncoding());
        $this->assertSame(ContentEncoding::aes128gcm, $subscription->getContentEncodingTyped());

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
        $this->assertSame("aes128gcm", $subscription->getContentEncoding());
        $this->assertSame(ContentEncoding::aes128gcm, $subscription->getContentEncodingTyped());
    }

    public function testConstructFull(): void
    {
        $subscription = new Subscription("http://toto.com", "publicKey", "authToken", ContentEncoding::aes128gcm);
        $this->assertEquals("http://toto.com", $subscription->getEndpoint());
        $this->assertEquals("publicKey", $subscription->getPublicKey());
        $this->assertEquals("authToken", $subscription->getAuthToken());
        $this->assertSame("aes128gcm", $subscription->getContentEncoding());
        $this->assertSame(ContentEncoding::aes128gcm, $subscription->getContentEncodingTyped());

        // Test with type string contentEncoding
        $subscription = new Subscription("http://toto.com", "publicKey", "authToken", "aesgcm");
        $this->assertEquals("http://toto.com", $subscription->getEndpoint());
        $this->assertEquals("publicKey", $subscription->getPublicKey());
        $this->assertEquals("authToken", $subscription->getAuthToken());
        $this->assertSame("aesgcm", $subscription->getContentEncoding());
        $this->assertSame(ContentEncoding::aesgcm, $subscription->getContentEncodingTyped());
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
        $this->assertSame("aesgcm", $subscription->getContentEncoding());
        $this->assertSame(ContentEncoding::aesgcm, $subscription->getContentEncodingTyped());
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
        $this->assertSame("aes128gcm", $subscription->getContentEncoding());
        $this->assertSame(ContentEncoding::aes128gcm, $subscription->getContentEncodingTyped());
    }
}
