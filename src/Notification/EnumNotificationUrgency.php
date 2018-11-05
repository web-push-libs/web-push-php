<?php
/**
 * @author t1gor [igor.timoshenkov@gmail.com]
 * @started: 05/11/2018 11:19
 */

namespace Minishlink\WebPush\Notification;

abstract class EnumNotificationUrgency {

	public const VERY_LOW = 'very-low';
	public const LOW = 'low';
	public const NORMAL = 'normal';
	public const HIGH = 'high';
}
