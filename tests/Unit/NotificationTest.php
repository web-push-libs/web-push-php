<?php

namespace Minishlink\WebPush\Tests\Unit;

use Exception;
use Minishlink\WebPush\Notification;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\Tests\TestCase;

/**
 * @covers \Minishlink\WebPush\Notification
 */
final class NotificationTest extends TestCase
{
    public function testHasOptions(): void
    {
        $options = [
            'TTL' => 1000, 'urgency' => 'normal', 'topic' => null
        ];
        $instance = new Notification(new Subscription(''), '', $options, []);
        $this->assertEquals(array_filter($options), $instance->getOptions());
    }

    public function testOnlySetsAllowedOptions(): void
    {
        $options = [
            'TTL' => 1000, 'urgency' => 'normal', 'foo' => 'bar'
        ];
        $instance = new Notification(new Subscription(''), '', $options, []);
        unset($options['foo']);
        $this->assertEquals(array_filter($options), $instance->getOptions());
    }

    public function testDeterminesWhetherItHasAuth(): void
    {
        $instance = new Notification(new Subscription(''), '', [], ['VAPID' => 'foobar']);
        $this->assertTrue($instance->hasAuth());
        $instance = new Notification(new Subscription(''), '', [], []);
        $this->assertFalse($instance->hasAuth());
        $instance = new Notification(new Subscription(''), '', [], ['invalid']);
        $this->assertFalse($instance->hasAuth());
    }

    public function testHasAuth(): void
    {
        $instance = new Notification(new Subscription(''), '', [], ['VAPID' => ['foobar']]);
        $this->assertEquals(['foobar'], $instance->getAuth());
    }

    public function testDeterminesWhichAuthService(): void
    {
        $instance = new Notification(new Subscription(''), '', [], ['VAPID' => 'foobar']);
        $this->assertEquals('VAPID', $instance->getAuthType());
        $instance = new Notification(new Subscription(''), '', [], ['GCM' => 'foobar']);
        $this->assertEquals('GCM', $instance->getAuthType());
        $instance = new Notification(new Subscription(''), '', [], ['FOO' => 'foobar']);
        $this->assertEquals('', $instance->getAuthType());
    }

    public function testOnlySetsAllowedAuth(): void
    {
        $instance = new Notification(new Subscription(''), '', [], ['foo' => 'bar']);
        $this->assertEquals(null, $instance->getAuth());
    }

    public function testThrowsAnExceptionIfYouSetMultipleAuth(): void
    {
        $this->expectException(Exception::class);
        new Notification(new Subscription(''), '', [], ['VAPID' => 'foobar', 'GCM' => 'foobar']);
    }
}
