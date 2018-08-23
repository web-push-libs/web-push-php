<?php
/**
 * @author Igor Timoshenkov [igor.timoshenkov@gmail.com]
 */

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class ExtendedWebPush extends WebPush {

	public function hasNotifications(): bool {
		return $this->notifications !== null;
	}

}

/**
 * @covers Minishlink\WebPush\WebPush
 */
class WebPushExtendableTest extends \PHPUnit\Framework\TestCase {

	private static $endpoints;

	/**
	 * {@inheritdoc}
	 */
	public static function setUpBeforeClass()
	{
		self::$endpoints = [
			'standard' => getenv('STANDARD_ENDPOINT'),
			'GCM' => getenv('GCM_ENDPOINT'),
		];
	}

	public function testExtendedCorrectly() {
		$extednded = new ExtendedWebPush();
		$this->assertInstanceOf(WebPush::class, $extednded);
		$this->assertFalse($extednded->hasNotifications());

		$subscription = new Subscription(self::$endpoints['standard']);

		$extednded->sendNotification($subscription);
	}

	public function testGetNotificationsCount() {
		$extednded = new ExtendedWebPush();
		$this->assertEquals(0, $extednded->getNotificationsCount());

		$subscription = new Subscription(self::$endpoints['standard']);
		$extednded->sendNotification($subscription);
		$this->assertEquals(1, $extednded->getNotificationsCount());
	}
}
