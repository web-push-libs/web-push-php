<?php
/**
 * @author t1gor [igor.timoshenkov@gmail.com]
 * @started: 05/11/2018 11:48
 */

namespace Minishlink\WebPush\Notification;

abstract class EnumContentEncoding {

	public const AES_GCM = 'aesgcm';
	public const AES_128_GCM = 'aes128gcm';

	/**
	 * @return array
	 */
	public static function getSupported(): array {
		return [static::AES_GCM, static::AES_128_GCM];
	}

	/**
	 * @param null|string $encoding
	 *
	 * @return bool
	 */
	public static function isSupported(?string $encoding): bool {
		return \in_array($encoding, static::getSupported(), true);
	}
}
