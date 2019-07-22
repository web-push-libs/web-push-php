<?php

declare(strict_types = 1);

namespace WebPush\Tests\Unit;

use WebPush\PayloadBuilder;
use WebPush\Subscription;
use WebPush\SubscriptionFactory;
use WebPush\Tests\TestCase;

final class PayloadBuilderTest extends TestCase
{
    public function testBuildsAPayloadFromAValidPublicKey(): void
    {
        $this->assertFalse((new PayloadBuilder())->build(
            $this->getSubscription(),
            'foobar',
            0
        )->isEmpty());
    }

    public function testRaisesAnExceptionWhenUsingAnInvalidPublicKey(): void
    {
        $this->expectExceptionMessage('Invalid data: only uncompressed keys are supported.');
        $subscription = $this->getSubscription();
        (new PayloadBuilder())->build(SubscriptionFactory::create([
            'endpoint' => $subscription->getEndpoint(),
            'public_key' => '',
            'auth_token' => $subscription->getAuthToken(),
            'encoding' => $subscription->getEncoding()
        ]), 'foobar', 0);
    }

    public function testRaisesAnExceptionWhenInvalidEncodingIsSpecified(): void
    {
        $this->expectExceptionMessage('This content encoding is not supported');
        $subscription = $this->getSubscription();
        (new PayloadBuilder())->build(SubscriptionFactory::create([
            'endpoint' => $subscription->getEndpoint(),
            'public_key' => $subscription->getPublicKey(),
            'auth_token' => $subscription->getAuthToken(),
            'encoding' => 'aesinvalid'
        ]), 'foobar', 0);
    }

    public function testRaisesAnExceptionWhenPayloadIsTooLong(): void
    {
        $this->expectExceptionMessage('Size of payload must not be greater than 4078 octets.');
        (new PayloadBuilder())->build($this->getSubscription(), str_repeat('f', 4079), 0);
    }
}
