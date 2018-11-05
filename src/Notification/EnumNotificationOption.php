<?php
/**
 * @author t1gor [igor.timoshenkov@gmail.com]
 * @started: 05/11/2018 11:08
 */

namespace Minishlink\WebPush\Notification;

abstract class EnumNotificationOption {

	public const TTL = 'TTL';
	public const URGENCY = 'urgency';
	public const TOPIC = 'topic';
	public const BATCH_SIZE = 'batchSize';
}
