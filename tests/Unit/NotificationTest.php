<?php

namespace Minishlink\WebPush\Tests\Unit;

use Exception;
use Minishlink\WebPush\Notification;
use Minishlink\WebPush\Options;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\Tests\TestCase;

/**
 * @covers \Minishlink\WebPush\Notification
 */
final class NotificationTest extends TestCase
{
    public function testDeterminesWhetherItHasAuth(): void
    {
        $instance = new Notification(new Subscription(''), '', new Options(), ['VAPID' => 'foobar']);
        $this->assertTrue($instance->hasAuth());
        $instance = new Notification(new Subscription(''), '', new Options(), []);
        $this->assertFalse($instance->hasAuth());
        $instance = new Notification(new Subscription(''), '', new Options(), ['invalid']);
        $this->assertFalse($instance->hasAuth());
    }

    public function testHasAuth(): void
    {
        $instance = new Notification(new Subscription(''), '', new Options(), ['VAPID' => ['foobar']]);
        $this->assertEquals(['foobar'], $instance->getAuth());
    }

    public function testDeterminesWhichAuthService(): void
    {
        $instance = new Notification(new Subscription(''), '', new Options(), ['VAPID' => 'foobar']);
        $this->assertEquals('VAPID', $instance->getAuthType());
        $instance = new Notification(new Subscription(''), '', new Options(), ['GCM' => 'foobar']);
        $this->assertEquals('GCM', $instance->getAuthType());
        $instance = new Notification(new Subscription(''), '', new Options(), ['FOO' => 'foobar']);
        $this->assertEquals('', $instance->getAuthType());
    }

    public function testOnlySetsAllowedAuth(): void
    {
        $instance = new Notification(new Subscription(''), '', new Options(), ['foo' => 'bar']);
        $this->assertEquals(null, $instance->getAuth());
    }

    public function testThrowsAnExceptionIfYouSetMultipleAuth(): void
    {
        $this->expectException(Exception::class);
        new Notification(new Subscription(''), '', new Options(), ['VAPID' => 'foobar', 'GCM' => 'foobar']);
    }
}
