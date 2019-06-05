<?php

namespace Minishlink\WebPush\Tests\Unit;

use Minishlink\WebPush\Notification;
use Minishlink\WebPush\Subscription;
use PHPUnit\Framework\TestCase;

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
        $this->assertEquals($options, $instance->getOptions());
    }

    public function testOnlySetsAllowedOptions(): void
    {
        $options = [
            'TTL' => 1000, 'urgency' => 'normal', 'topic' => null
        ];
        $instance = new Notification(new Subscription(''), '', array_merge($options, ['foo' => 'bar']), []);
        $this->assertEquals($options, $instance->getOptions());
    }

    public function testOverridesMissingOptionsWithOptionsParameter(): void
    {
        $options = [
            'TTL' => 1000, 'urgency' => 'normal', 'topic' => null
        ];
        $instance = new Notification(new Subscription(''), '', $options, []);

        $this->assertEquals($options, $instance->getOptions(['TTL' => 2000]));
        $this->assertEquals(
            array_merge($options, ['topic' => 'foo_topic']),
            $instance->getOptions(['topic' => 'foo_topic'])
        );
    }

    public function testOnlyOverridesAllowedOptions(): void
    {
        $options = [
            'TTL' => 1000, 'urgency' => 'normal', 'topic' => null
        ];
        $instance = new Notification(new Subscription(''), '', $options, []);
        $this->assertEquals($options, $instance->getOptions(['foo' => 'bar']));
    }

    public function testGetsNullValuesForOptionsWhichHaveNotBeenSpecified(): void
    {
        $instance = new Notification(new Subscription(''), '', [], []);
        $this->assertEquals(['TTL' => null, 'urgency' => null, 'topic' => null], $instance->getOptions());
    }
}
