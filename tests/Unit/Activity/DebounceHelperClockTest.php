<?php

/**
 * DebounceHelper Clock Test
 *
 * Exercises the deterministic in-memory backend the helper uses under
 * an injected clock: each claim kind (reaction, global fan-out, daily
 * update) is granted once per window, keyed on its own parameters, and
 * granted again exactly when its TTL has passed.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Activity
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
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
 * Unit tests for DebounceHelper under an injected clock.
 *
 * @covers \OCA\LaunchPad\Activity\DebounceHelper
 */
class DebounceHelperClockTest extends TestCase {
	/**
	 * The fake clock.
	 *
	 * @var integer
	 */
	private int $now = 5_000;

	/**
	 * The helper under test.
	 *
	 * @var DebounceHelper
	 */
	private DebounceHelper $helper;

	/**
	 * Build the helper on the fake clock.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->helper = new DebounceHelper(clock: fn (): int => $this->now);
	}

	/**
	 * A reaction is allowed once per actor and dashboard within the window.
	 *
	 * @return void
	 */
	public function testReactionIsAllowedOncePerActorAndDashboard(): void {
		$this->assertTrue(condition: $this->helper->allowReaction(actorUserId: 'alice', dashboardUuid: 'd1'));
		$this->assertFalse(condition: $this->helper->allowReaction(actorUserId: 'alice', dashboardUuid: 'd1'));
		$this->assertTrue(condition: $this->helper->allowReaction(actorUserId: 'bob', dashboardUuid: 'd1'));
		$this->assertTrue(condition: $this->helper->allowReaction(actorUserId: 'alice', dashboardUuid: 'd2'));
	}

	/**
	 * A reaction claim expires exactly after TTL_SECONDS.
	 *
	 * @return void
	 */
	public function testReactionClaimExpiresAfterTheTtl(): void {
		$this->assertTrue(condition: $this->helper->allowReaction(actorUserId: 'alice', dashboardUuid: 'd1'));
		$this->now += (DebounceHelper::TTL_SECONDS - 1);
		$this->assertFalse(condition: $this->helper->allowReaction(actorUserId: 'alice', dashboardUuid: 'd1'));
		$this->now += 1;
		$this->assertTrue(condition: $this->helper->allowReaction(actorUserId: 'alice', dashboardUuid: 'd1'));
	}

	/**
	 * Global fan-out is keyed on dashboard and event type.
	 *
	 * @return void
	 */
	public function testGlobalFanoutIsKeyedOnDashboardAndEventType(): void {
		$this->assertTrue(condition: $this->helper->allowGlobalFanout(dashboardUuid: 'd1', eventType: 'published'));
		$this->assertFalse(condition: $this->helper->allowGlobalFanout(dashboardUuid: 'd1', eventType: 'published'));
		$this->assertTrue(condition: $this->helper->allowGlobalFanout(dashboardUuid: 'd1', eventType: 'updated'));
		$this->assertTrue(condition: $this->helper->allowGlobalFanout(dashboardUuid: 'd2', eventType: 'published'));
	}

	/**
	 * The daily update claim lasts a day, not the short TTL.
	 *
	 * @return void
	 */
	public function testDailyUpdateLastsADay(): void {
		$this->assertTrue(condition: $this->helper->allowDailyUpdate(dashboardUuid: 'd1'));
		$this->now += DebounceHelper::TTL_SECONDS;
		$this->assertFalse(condition: $this->helper->allowDailyUpdate(dashboardUuid: 'd1'));
		$this->now += (DebounceHelper::DAILY_TTL_SECONDS - DebounceHelper::TTL_SECONDS - 1);
		$this->assertFalse(condition: $this->helper->allowDailyUpdate(dashboardUuid: 'd1'));
		$this->now += 1;
		$this->assertTrue(condition: $this->helper->allowDailyUpdate(dashboardUuid: 'd1'));
	}

	/**
	 * Claim kinds do not share keys: a reaction does not block the daily update.
	 *
	 * @return void
	 */
	public function testClaimKindsAreIndependent(): void {
		$this->assertTrue(condition: $this->helper->allowReaction(actorUserId: 'u', dashboardUuid: 'd1'));
		$this->assertTrue(condition: $this->helper->allowGlobalFanout(dashboardUuid: 'd1', eventType: 'u'));
		$this->assertTrue(condition: $this->helper->allowDailyUpdate(dashboardUuid: 'd1'));
	}

	/**
	 * Under an injected clock the distributed cache is never consulted.
	 *
	 * @return void
	 */
	public function testAFakeClockNeverUsesTheDistributedCache(): void {
		$cache = $this->createMock(originalClassName: ICache::class);
		$cache->expects($this->never())->method($this->anything());
		$helper = new DebounceHelper(clock: fn (): int => $this->now, cache: $cache);

		$this->assertTrue(condition: $helper->allowDailyUpdate(dashboardUuid: 'd1'));
		$this->assertFalse(condition: $helper->allowDailyUpdate(dashboardUuid: 'd1'));
	}
}
