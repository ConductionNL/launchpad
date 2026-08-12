<?php

/**
 * DebounceHelperTest
 *
 * Unit tests for the three-tier debounce backend introduced by
 * harden-activity-debounce-cache-reliability:
 *
 *  - REQ-ACT-007/REQ-ACT-008: when APCu is unusable the helper MUST use
 *    the injected distributed `ICache` so the 900-second debounce claim
 *    survives across requests/workers — not degrade to "always allow".
 *  - Defensive tier: with neither APCu nor a cache the helper MUST still
 *    fall back to the in-memory array without throwing.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Activity
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/specs/activity-feed-integration/spec.md
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Activity;

use OCA\LaunchPad\Activity\DebounceHelper;
use OCP\ICache;
use PHPUnit\Framework\TestCase;

/**
 * Minimal in-memory {@see ICache} used to exercise the distributed-cache
 * debounce path without a live Nextcloud cache backend.
 */
final class FakeDebounceCache implements ICache {

	/**
	 * The backing store, keyed exactly like the real cache.
	 *
	 * @var array<string, mixed>
	 */
	public array $store = [];

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key The cache key.
	 *
	 * @return mixed
	 */
	public function get(string $key) {
		return ($this->store[$key] ?? null);
	}//end get()

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key The cache key.
	 * @param mixed $value The value to store.
	 * @param int $ttl The TTL in seconds (ignored by the fake).
	 *
	 * @return bool
	 */
	public function set(string $key, $value, int $ttl = 0): bool {
		$this->store[$key] = $value;
		return true;
	}//end set()

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key The cache key.
	 *
	 * @return bool
	 */
	public function hasKey(string $key): bool {
		return array_key_exists($key, $this->store);
	}//end hasKey()

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key The cache key.
	 *
	 * @return bool
	 */
	public function remove(string $key): bool {
		unset($this->store[$key]);
		return true;
	}//end remove()

	/**
	 * {@inheritDoc}
	 *
	 * @param string $prefix Optional key prefix to clear.
	 *
	 * @return bool
	 */
	public function clear(string $prefix = ''): bool {
		$this->store = [];
		return true;
	}//end clear()

	/**
	 * {@inheritDoc}
	 *
	 * @return bool
	 */
	public static function isAvailable(): bool {
		return true;
	}//end isAvailable()
}//end class

/**
 * Tests for the distributed-cache and defensive fallback tiers.
 */
class DebounceHelperTest extends TestCase {

	/**
	 * Flush any APCu state so the real-clock path deterministically
	 * exercises the requested backend and not a leftover APCu claim.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		if (function_exists('apcu_clear_cache') === true) {
			apcu_clear_cache();
		}
	}//end setUp()

	/**
	 * REQ-ACT-007: with a real clock, APCu unusable, and a distributed
	 * cache injected, a second reaction claim for the same
	 * `(actor, dashboard)` pair inside the window MUST be suppressed —
	 * proving the cache path (not the per-request in-memory array) holds
	 * the debounce guarantee.
	 *
	 * The php:8.3-cli builder image ships without APCu, so `apcuUsable()`
	 * returns false and the cache tier is the one under test. This is
	 * skipped if a runner DOES have usable APCu, since then the APCu fast
	 * path — not the cache path — would answer the claim.
	 *
	 * @return void
	 */
	public function testDistributedCachePathDebouncesAcrossClaims(): void {
		if ($this->apcuIsUsable() === true) {
			$this->markTestSkipped(
				'APCu is usable on this runner; the cache fallback path is not exercised.'
			);
		}

		$cache = new FakeDebounceCache();
		$helper = new DebounceHelper(cache: $cache);

		$first = $helper->allowReaction('alice', 'dash-1');
		$second = $helper->allowReaction('alice', 'dash-1');

		$this->assertTrue($first, 'First reaction claim must be allowed.');
		$this->assertFalse($second, 'Second claim inside the window must be suppressed via ICache.');
		$this->assertNotEmpty($cache->store, 'The claim must have been written to the distributed cache.');

		// A different key is an independent window and must be allowed.
		$this->assertTrue(
			$helper->allowReaction('bob', 'dash-1'),
			'A distinct (actor, dashboard) pair is an independent debounce window.'
		);
	}//end testDistributedCachePathDebouncesAcrossClaims()

	/**
	 * REQ-ACT-008: the global fan-out claim is likewise debounced via the
	 * distributed cache.
	 *
	 * @return void
	 */
	public function testDistributedCachePathDebouncesGlobalFanout(): void {
		if ($this->apcuIsUsable() === true) {
			$this->markTestSkipped(
				'APCu is usable on this runner; the cache fallback path is not exercised.'
			);
		}

		$cache = new FakeDebounceCache();
		$helper = new DebounceHelper(cache: $cache);

		$this->assertTrue($helper->allowGlobalFanout('dash-1', 'reacted'));
		$this->assertFalse($helper->allowGlobalFanout('dash-1', 'reacted'));
		// Distinct event type = independent window.
		$this->assertTrue($helper->allowGlobalFanout('dash-1', 'commented'));
	}//end testDistributedCachePathDebouncesGlobalFanout()

	/**
	 * Defensive tier: with neither usable APCu nor an injected cache, the
	 * helper MUST fall back to the in-memory array without throwing and
	 * still debounce within the process.
	 *
	 * @return void
	 */
	public function testDefensiveInMemoryFallbackWhenNoCache(): void {
		if ($this->apcuIsUsable() === true) {
			$this->markTestSkipped(
				'APCu is usable on this runner; the in-memory defensive tier is not exercised.'
			);
		}

		$helper = new DebounceHelper();

		$this->assertTrue($helper->allowReaction('carol', 'dash-2'));
		$this->assertFalse($helper->allowReaction('carol', 'dash-2'));
	}//end testDefensiveInMemoryFallbackWhenNoCache()

	/**
	 * Mirror of the helper's own `apcuUsable()` gate for real-clock use,
	 * so the tests skip on runners where APCu would answer the claim.
	 *
	 * @return bool
	 */
	private function apcuIsUsable(): bool {
		if (function_exists('apcu_add') === false || function_exists('apcu_exists') === false) {
			return false;
		}

		if (function_exists('apcu_enabled') === true) {
			return (bool)apcu_enabled();
		}

		return (bool)ini_get('apc.enabled');
	}//end apcuIsUsable()
}//end class
