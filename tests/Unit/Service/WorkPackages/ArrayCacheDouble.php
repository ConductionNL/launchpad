<?php

/**
 * An in-memory ICache for the work packages reader's tests.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service\WorkPackages
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service\WorkPackages;

use OCP\ICache;

/**
 * Keeps values in an array, ignoring the time to live.
 */
class ArrayCacheDouble implements ICache {
	/**
	 * The values by key.
	 *
	 * @var array<string, mixed>
	 */
	private array $values = [];

	/**
	 * The time to live each key was kept with, in seconds.
	 *
	 * @var array<string, int>
	 */
	public array $ttls = [];

	/**
	 * The value of a key, null when absent.
	 *
	 * @param string $key The key.
	 *
	 * @return mixed
	 */
	public function get($key) {
		return $this->values[$key] ?? null;
	}//end get()

	/**
	 * Keep a value.
	 *
	 * @param string $key   The key.
	 * @param mixed  $value The value.
	 * @param int    $ttl   Seconds to keep it, recorded in {@see $ttls}.
	 *
	 * @return bool
	 */
	public function set($key, $value, $ttl=0) {
		$this->values[$key] = $value;
		$this->ttls[$key]   = (int)$ttl;
		return true;
	}//end set()

	/**
	 * Whether a key is kept.
	 *
	 * @param string $key The key.
	 *
	 * @return bool
	 */
	public function hasKey($key) {
		return array_key_exists($key, $this->values);
	}//end hasKey()

	/**
	 * Forget a key.
	 *
	 * @param string $key The key.
	 *
	 * @return bool
	 */
	public function remove($key) {
		unset($this->values[$key]);
		return true;
	}//end remove()

	/**
	 * Forget every key.
	 *
	 * @param string $prefix Ignored.
	 *
	 * @return bool
	 */
	public function clear($prefix='') {
		$this->values = [];
		return true;
	}//end clear()

	/**
	 * Always available.
	 *
	 * @return bool
	 */
	public static function isAvailable(): bool {
		return true;
	}//end isAvailable()
}//end class
