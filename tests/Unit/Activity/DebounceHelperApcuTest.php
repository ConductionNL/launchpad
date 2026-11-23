<?php

/**
 * DebounceHelperApcuTest
 *
 * Unit tests for the APCu tier of {@see \OCA\LaunchPad\Activity\DebounceHelper}:
 * the race-free `apcu_add` claim, and the runtime probe that decides whether
 * APCu is usable (`apcu_enabled()`, or the `apc.enabled` ini setting on builds
 * without it). The runner ships without APCu, so the APCu boundary is replaced
 * by namespace-level doubles defined in the helper's namespace below; each
 * double delegates to the real global function unless a test switches it on.
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

namespace OCA\LaunchPad\Activity {

	use Unit\Activity\ApcuDouble;

	/**
	 * Namespace double for `function_exists`: reports the APCu functions the
	 * test declares present or absent, and delegates every other name.
	 *
	 * @param string $function The function name.
	 *
	 * @return bool
	 */
	function function_exists(string $function): bool {
		if (ApcuDouble::$active === true && str_starts_with($function, 'apcu_') === true) {
			return in_array($function, ApcuDouble::$present, true);
		}

		return \function_exists($function);
	}//end function_exists()

	/**
	 * Namespace double for `apcu_add`: while active, a key-exists-refuses store
	 * that records every call; otherwise the real function.
	 *
	 * @param string $key   The key.
	 * @param mixed  $value The value.
	 * @param int    $ttl   The TTL in seconds.
	 *
	 * @return bool
	 */
	function apcu_add(string $key, mixed $value, int $ttl=0): bool {
		if (ApcuDouble::$active === false) {
			return (bool)\apcu_add($key, $value, $ttl);
		}

		ApcuDouble::$adds[] = [$key, $value, $ttl];
		if (array_key_exists($key, ApcuDouble::$store) === true) {
			return false;
		}

		ApcuDouble::$store[$key] = $value;
		return true;
	}//end apcu_add()

	/**
	 * Namespace double for `apcu_enabled`: the test's answer while active,
	 * otherwise the real function.
	 *
	 * @return bool
	 */
	function apcu_enabled(): bool {
		if (ApcuDouble::$active === false) {
			return (bool)\apcu_enabled();
		}

		return ApcuDouble::$enabled;
	}//end apcu_enabled()

	/**
	 * Namespace double for `ini_get`: answers `apc.enabled` while active.
	 *
	 * @param string $option The ini option.
	 *
	 * @return string|false
	 */
	function ini_get(string $option): string|false {
		if (ApcuDouble::$active === true && $option === 'apc.enabled') {
			return ApcuDouble::$iniEnabled;
		}

		return \ini_get($option);
	}//end ini_get()
}//end namespace

namespace Unit\Activity {

	use OCA\LaunchPad\Activity\DebounceHelper;
	use OCP\ICache;
	use PHPUnit\Framework\TestCase;

	/**
	 * State of the APCu doubles above.
	 */
	final class ApcuDouble {

		/**
		 * Whether the doubles answer instead of the real functions.
		 *
		 * @var bool
		 */
		public static bool $active = false;

		/**
		 * The APCu function names reported present.
		 *
		 * @var array<int, string>
		 */
		public static array $present = [];

		/**
		 * What `apcu_enabled()` answers.
		 *
		 * @var bool
		 */
		public static bool $enabled = false;

		/**
		 * What `ini_get('apc.enabled')` answers.
		 *
		 * @var string|false
		 */
		public static string|false $iniEnabled = false;

		/**
		 * The claimed keys.
		 *
		 * @var array<string, mixed>
		 */
		public static array $store = [];

		/**
		 * Every `apcu_add` call as [key, value, ttl].
		 *
		 * @var array<int, array{0: string, 1: mixed, 2: int}>
		 */
		public static array $adds = [];

		/**
		 * Back to delegating, with nothing stored.
		 *
		 * @return void
		 */
		public static function reset(): void {
			self::$active     = false;
			self::$present    = [];
			self::$enabled    = false;
			self::$iniEnabled = false;
			self::$store      = [];
			self::$adds       = [];
		}//end reset()
	}//end class

	/**
	 * Tests for the APCu tier and its usability probe.
	 *
	 * @covers \OCA\LaunchPad\Activity\DebounceHelper
	 */
	class DebounceHelperApcuTest extends TestCase {

		/**
		 * Switch the doubles on with no claims.
		 *
		 * @return void
		 */
		protected function setUp(): void {
			parent::setUp();
			ApcuDouble::reset();
			ApcuDouble::$active = true;
		}//end setUp()

		/**
		 * Switch the doubles off again.
		 *
		 * @return void
		 */
		protected function tearDown(): void {
			ApcuDouble::reset();
			parent::tearDown();
		}//end tearDown()

		/**
		 * With APCu enabled the claim is APCu's `add`: the first reaction
		 * claims the key for 900 seconds, the second is refused, and the
		 * distributed cache is never consulted.
		 *
		 * @return void
		 */
		public function testAnEnabledApcuAnswersTheClaimAndTheCacheIsNotUsed(): void {
			ApcuDouble::$present = ['apcu_add', 'apcu_exists', 'apcu_enabled'];
			ApcuDouble::$enabled = true;
			$cache = $this->createMock(originalClassName: ICache::class);
			$cache->expects($this->never())->method('hasKey');
			$cache->expects($this->never())->method('set');
			$helper = new DebounceHelper(cache: $cache);

			$this->assertTrue(condition: $helper->allowReaction('alice', 'dash-1'));
			$this->assertFalse(condition: $helper->allowReaction('alice', 'dash-1'));

			$this->assertCount(expectedCount: 2, haystack: ApcuDouble::$adds);
			$this->assertSame(expected: 'launchpad_act_react_alice_dash-1', actual: ApcuDouble::$adds[0][0]);
			$this->assertSame(expected: DebounceHelper::TTL_SECONDS, actual: ApcuDouble::$adds[0][2]);
			$this->assertIsInt(actual: ApcuDouble::$adds[0][1]);
		}//end testAnEnabledApcuAnswersTheClaimAndTheCacheIsNotUsed()

		/**
		 * The daily update claim passes the one-day TTL to APCu, and the
		 * global fan-out claim is keyed on dashboard and event type.
		 *
		 * @return void
		 */
		public function testTheApcuClaimCarriesEachKindsKeyAndTtl(): void {
			ApcuDouble::$present = ['apcu_add', 'apcu_exists', 'apcu_enabled'];
			ApcuDouble::$enabled = true;
			$helper = new DebounceHelper();

			$this->assertTrue(condition: $helper->allowDailyUpdate('dash-2'));
			$this->assertTrue(condition: $helper->allowGlobalFanout('dash-2', 'dashboard_published'));
			$this->assertFalse(condition: $helper->allowGlobalFanout('dash-2', 'dashboard_published'));

			$this->assertSame(
				expected: [
					['launchpad_act_daily_update_dash-2', DebounceHelper::DAILY_TTL_SECONDS],
					['launchpad_act_global_dash-2_dashboard_published', DebounceHelper::TTL_SECONDS],
					['launchpad_act_global_dash-2_dashboard_published', DebounceHelper::TTL_SECONDS],
				],
				actual: array_map(static fn (array $add): array => [$add[0], $add[2]], ApcuDouble::$adds)
			);
		}//end testTheApcuClaimCarriesEachKindsKeyAndTtl()

		/**
		 * APCu loaded but disabled at runtime (`apcu_enabled()` false, as under
		 * CLI with `apc.enable_cli=0`) is not used: the claim goes to the
		 * distributed cache instead of refusing every emission.
		 *
		 * @return void
		 */
		public function testADisabledApcuFallsBackToTheDistributedCache(): void {
			ApcuDouble::$present = ['apcu_add', 'apcu_exists', 'apcu_enabled'];
			ApcuDouble::$enabled = false;
			$cache = $this->createMock(originalClassName: ICache::class);
			$cache->expects($this->once())->method('hasKey')->with('launchpad_act_react_bob_dash-3')->willReturn(false);
			$cache->expects($this->once())->method('set')->with(
				'launchpad_act_react_bob_dash-3',
				$this->isType(type: 'int'),
				DebounceHelper::TTL_SECONDS
			);
			$helper = new DebounceHelper(cache: $cache);

			$this->assertTrue(condition: $helper->allowReaction('bob', 'dash-3'));
			$this->assertSame(expected: [], actual: ApcuDouble::$adds);
		}//end testADisabledApcuFallsBackToTheDistributedCache()

		/**
		 * On an APCu build without `apcu_enabled()` the `apc.enabled` ini
		 * setting decides: on, the claim is APCu's.
		 *
		 * @return void
		 */
		public function testWithoutApcuEnabledAnOnIniSettingUsesApcu(): void {
			ApcuDouble::$present    = ['apcu_add', 'apcu_exists'];
			ApcuDouble::$iniEnabled = '1';
			$helper = new DebounceHelper();

			$this->assertTrue(condition: $helper->allowReaction('carol', 'dash-4'));
			$this->assertFalse(condition: $helper->allowReaction('carol', 'dash-4'));
			$this->assertCount(expectedCount: 2, haystack: ApcuDouble::$adds);
		}//end testWithoutApcuEnabledAnOnIniSettingUsesApcu()

		/**
		 * On an APCu build without `apcu_enabled()` and `apc.enabled` off,
		 * APCu is not used and, with no cache injected, the in-memory store
		 * still debounces.
		 *
		 * @return void
		 */
		public function testWithoutApcuEnabledAnOffIniSettingSkipsApcu(): void {
			ApcuDouble::$present    = ['apcu_add', 'apcu_exists'];
			ApcuDouble::$iniEnabled = '0';
			$helper = new DebounceHelper();

			$this->assertTrue(condition: $helper->allowReaction('dave', 'dash-5'));
			$this->assertFalse(condition: $helper->allowReaction('dave', 'dash-5'));
			$this->assertSame(expected: [], actual: ApcuDouble::$adds);
		}//end testWithoutApcuEnabledAnOffIniSettingSkipsApcu()

		/**
		 * `apcu_add` present without `apcu_exists` is not a usable APCu.
		 *
		 * @return void
		 */
		public function testAnApcuWithoutApcuExistsIsNotUsed(): void {
			ApcuDouble::$present = ['apcu_add', 'apcu_enabled'];
			ApcuDouble::$enabled = true;
			$helper = new DebounceHelper();

			$this->assertTrue(condition: $helper->allowReaction('erin', 'dash-6'));
			$this->assertSame(expected: [], actual: ApcuDouble::$adds);
		}//end testAnApcuWithoutApcuExistsIsNotUsed()
	}//end class
}//end namespace
