<?php

/**
 * QuotaService Test
 *
 * Covers the dashboard-quota-limits enforcement contract: at / below / over
 * limit for both quota kinds, live recount, most-restrictive-wins with the
 * `allow_multiple_dashboards` flag, the provisioning bypass, and the
 * quota-status envelope.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Exception\QuotaExceededException;
use OCA\LaunchPad\Service\QuotaService;
use PHPUnit\Framework\TestCase;

class QuotaServiceTest extends TestCase {

	private QuotaService $service;

	private AdminSettingMapper $settingMapper;

	private DashboardMapper $dashboardMapper;

	private WidgetPlacementMapper $placementMapper;

	protected function setUp(): void {
		$this->settingMapper = $this->createMock(AdminSettingMapper::class);
		$this->dashboardMapper = $this->createMock(DashboardMapper::class);
		$this->placementMapper = $this->createMock(WidgetPlacementMapper::class);
		$this->service = new QuotaService(
			settingMapper: $this->settingMapper,
			dashboardMapper: $this->dashboardMapper,
			placementMapper: $this->placementMapper,
		);
	}//end setUp()

	/**
	 * Wire the setting mapper's getValue() for a given numeric quota +
	 * allow-multiple flag.
	 *
	 * @param string $key The numeric quota key.
	 * @param int $value The numeric quota value.
	 * @param bool $allowMultiple The allow_multiple_dashboards flag.
	 *
	 * @return void
	 */
	private function withSettings(string $key, int $value, bool $allowMultiple = true): void {
		$this->settingMapper->method('getValue')->willReturnCallback(
			function (string $k, $default = null) use ($key, $value, $allowMultiple) {
				if ($k === $key) {
					return $value;
				}

				if ($k === AdminSetting::KEY_ALLOW_MULTIPLE_DASHBOARDS) {
					return $allowMultiple;
				}

				return $default;
			}
		);
	}//end withSettings()

	// ----- REQ-QUOTA-002: dashboard count enforcement -----

	public function testDashboardCreateAllowedBelowLimit(): void {
		$this->withSettings(AdminSetting::KEY_MAX_DASHBOARDS_PER_USER, 5);
		$this->dashboardMapper->method('countPersonalByUserId')->willReturn(4);

		// No exception => allowed.
		$this->service->assertCanCreateDashboard(userId: 'alice');
		$this->addToAssertionCount(1);
	}//end testDashboardCreateAllowedBelowLimit()

	public function testDashboardCreateBlockedAtLimit(): void {
		$this->withSettings(AdminSetting::KEY_MAX_DASHBOARDS_PER_USER, 5);
		$this->dashboardMapper->method('countPersonalByUserId')->willReturn(5);

		try {
			$this->service->assertCanCreateDashboard(userId: 'alice');
			$this->fail('Expected QuotaExceededException');
		} catch (QuotaExceededException $e) {
			$this->assertSame(QuotaExceededException::QUOTA_DASHBOARDS, $e->getQuota());
			$this->assertSame(5, $e->getLimit());
			$this->assertSame(5, $e->getCurrent());
			$this->assertSame(409, $e->getHttpStatus());
			$this->assertSame(
				[
					'error' => 'quota_exceeded',
					'quota' => 'dashboards',
					'limit' => 5,
					'current' => 5,
				],
				$e->toResponseBody()
			);
		}//end try
	}//end testDashboardCreateBlockedAtLimit()

	public function testDashboardCreateUnlimitedWhenZero(): void {
		// REQ-QUOTA-001 — 0 means unlimited; count never queried.
		$this->withSettings(AdminSetting::KEY_MAX_DASHBOARDS_PER_USER, 0);
		$this->dashboardMapper->expects($this->never())
			->method('countPersonalByUserId');

		$this->service->assertCanCreateDashboard(userId: 'alice');
		$this->addToAssertionCount(1);
	}//end testDashboardCreateUnlimitedWhenZero()

	public function testDashboardCreateLiveRecountAfterDelete(): void {
		// REQ-QUOTA-002 — count is computed live, so dropping below the
		// limit immediately permits a new create.
		$this->withSettings(AdminSetting::KEY_MAX_DASHBOARDS_PER_USER, 5);
		$this->dashboardMapper->method('countPersonalByUserId')->willReturn(4);

		$this->service->assertCanCreateDashboard(userId: 'alice');
		$this->addToAssertionCount(1);
	}//end testDashboardCreateLiveRecountAfterDelete()

	public function testGrandfatheringBlocksWhenOverLoweredLimit(): void {
		// REQ-QUOTA-005 — usage (8) exceeds a lowered limit (5): new
		// creation blocked, exception carries the real over-quota count.
		$this->withSettings(AdminSetting::KEY_MAX_DASHBOARDS_PER_USER, 5);
		$this->dashboardMapper->method('countPersonalByUserId')->willReturn(8);

		try {
			$this->service->assertCanCreateDashboard(userId: 'alice');
			$this->fail('Expected QuotaExceededException');
		} catch (QuotaExceededException $e) {
			$this->assertSame(5, $e->getLimit());
			$this->assertSame(8, $e->getCurrent());
		}
	}//end testGrandfatheringBlocksWhenOverLoweredLimit()

	// ----- REQ-QUOTA-002 / D6: most-restrictive-wins -----

	public function testAllowMultipleFalseGivesEffectiveLimitOne(): void {
		// REQ-QUOTA-002 — allow_multiple_dashboards = false ⇒ effective
		// limit 1 regardless of the numeric setting (5).
		$this->withSettings(AdminSetting::KEY_MAX_DASHBOARDS_PER_USER, 5, allowMultiple: false);
		$this->dashboardMapper->method('countPersonalByUserId')->willReturn(1);

		try {
			$this->service->assertCanCreateDashboard(userId: 'alice');
			$this->fail('Expected QuotaExceededException');
		} catch (QuotaExceededException $e) {
			$this->assertSame(1, $e->getLimit());
			$this->assertSame(1, $e->getCurrent());
		}
	}//end testAllowMultipleFalseGivesEffectiveLimitOne()

	public function testNumericQuotaDoesNotLoosenBooleanRestriction(): void {
		// REQ-QUOTA-002 — even with a generous numeric quota, the boolean
		// off-switch keeps the effective limit at 1, so a user with 1
		// dashboard is blocked from a second.
		$this->withSettings(AdminSetting::KEY_MAX_DASHBOARDS_PER_USER, 100, allowMultiple: false);
		$this->dashboardMapper->method('countPersonalByUserId')->willReturn(1);

		$this->expectException(QuotaExceededException::class);
		$this->service->assertCanCreateDashboard(userId: 'alice');
	}//end testNumericQuotaDoesNotLoosenBooleanRestriction()

	// ----- REQ-QUOTA-003: widget count enforcement -----

	public function testWidgetAddAllowedBelowLimit(): void {
		$this->withSettings(AdminSetting::KEY_MAX_WIDGETS_PER_DASHBOARD, 40);
		$this->placementMapper->method('countByDashboardId')->willReturn(39);

		$this->service->assertCanAddPlacement(dashboardId: 7);
		$this->addToAssertionCount(1);
	}//end testWidgetAddAllowedBelowLimit()

	public function testWidgetAddBlockedAtLimit(): void {
		$this->withSettings(AdminSetting::KEY_MAX_WIDGETS_PER_DASHBOARD, 40);
		$this->placementMapper->method('countByDashboardId')->willReturn(40);

		try {
			$this->service->assertCanAddPlacement(dashboardId: 7);
			$this->fail('Expected QuotaExceededException');
		} catch (QuotaExceededException $e) {
			$this->assertSame(QuotaExceededException::QUOTA_WIDGETS, $e->getQuota());
			$this->assertSame(40, $e->getLimit());
			$this->assertSame(40, $e->getCurrent());
			$this->assertSame('widgets', $e->toResponseBody()['quota']);
		}//end try
	}//end testWidgetAddBlockedAtLimit()

	public function testWidgetAddUnlimitedWhenZero(): void {
		$this->withSettings(AdminSetting::KEY_MAX_WIDGETS_PER_DASHBOARD, 0);
		$this->placementMapper->expects($this->never())
			->method('countByDashboardId');

		$this->service->assertCanAddPlacement(dashboardId: 7);
		$this->addToAssertionCount(1);
	}//end testWidgetAddUnlimitedWhenZero()

	// ----- REQ-QUOTA-004: provisioning bypass -----

	public function testProvisioningBypassesDashboardQuota(): void {
		// REQ-QUOTA-004 — inside runProvisioning(), an over-quota user's
		// creation is NOT blocked (template rollout). Count never queried.
		$this->withSettings(AdminSetting::KEY_MAX_DASHBOARDS_PER_USER, 5);
		$this->dashboardMapper->expects($this->never())
			->method('countPersonalByUserId');

		$ran = $this->service->runProvisioning(
			function () {
				$this->service->assertCanCreateDashboard(userId: 'alice');
				return 'rolled-out';
			}
		);

		$this->assertSame('rolled-out', $ran);
	}//end testProvisioningBypassesDashboardQuota()

	public function testProvisioningBypassesWidgetQuota(): void {
		// REQ-QUOTA-004 — compulsory-widget push bypasses the widget quota.
		$this->withSettings(AdminSetting::KEY_MAX_WIDGETS_PER_DASHBOARD, 40);
		$this->placementMapper->expects($this->never())
			->method('countByDashboardId');

		$this->service->runProvisioning(
			function () {
				$this->service->assertCanAddPlacement(dashboardId: 7);
			}
		);
		$this->addToAssertionCount(1);
	}//end testProvisioningBypassesWidgetQuota()

	public function testProvisioningFlagResetsAfterCallEvenOnThrow(): void {
		// REQ-QUOTA-004 — a throwing provisioning call must NOT leave the
		// service permanently bypassed: the next user-initiated assert is
		// enforced again.
		$this->withSettings(AdminSetting::KEY_MAX_DASHBOARDS_PER_USER, 5);
		$this->dashboardMapper->method('countPersonalByUserId')->willReturn(5);

		try {
			$this->service->runProvisioning(
				function () {
					throw new \RuntimeException('boom');
				}
			);
		} catch (\RuntimeException) {
			// expected
		}

		$this->assertFalse($this->service->isProvisioning());
		$this->expectException(QuotaExceededException::class);
		$this->service->assertCanCreateDashboard(userId: 'alice');
	}//end testProvisioningFlagResetsAfterCallEvenOnThrow()

	public function testAdminBoundByQuotaOutsideProvisioning(): void {
		// REQ-QUOTA-004 — an admin creating their own personal dashboard
		// through the normal flow (no provisioning wrapper) is still bound.
		$this->withSettings(AdminSetting::KEY_MAX_DASHBOARDS_PER_USER, 5);
		$this->dashboardMapper->method('countPersonalByUserId')->willReturn(5);

		$this->expectException(QuotaExceededException::class);
		$this->service->assertCanCreateDashboard(userId: 'carol');
	}//end testAdminBoundByQuotaOutsideProvisioning()

	// ----- REQ-QUOTA-006: quota status envelope -----

	public function testGetQuotaStatusEnvelopeShape(): void {
		$this->settingMapper->method('getValue')->willReturnCallback(
			function (string $k, $default = null) {
				return match ($k) {
					AdminSetting::KEY_MAX_DASHBOARDS_PER_USER => 5,
					AdminSetting::KEY_MAX_WIDGETS_PER_DASHBOARD => 40,
					AdminSetting::KEY_ALLOW_MULTIPLE_DASHBOARDS => true,
					default => $default,
				};
			}
		);
		$this->dashboardMapper->method('countPersonalByUserId')->willReturn(3);

		$status = $this->service->getQuotaStatus(userId: 'alice');

		$this->assertSame(
			[
				'maxDashboards' => 5,
				'dashboardsUsed' => 3,
				'maxWidgetsPerDashboard' => 40,
			],
			$status
		);
	}//end testGetQuotaStatusEnvelopeShape()

	public function testGetQuotaStatusReflectsEffectiveLimit(): void {
		// REQ-QUOTA-006 / D6 — the envelope surfaces the EFFECTIVE limit, so
		// allow_multiple_dashboards = false shows maxDashboards = 1.
		$this->settingMapper->method('getValue')->willReturnCallback(
			function (string $k, $default = null) {
				return match ($k) {
					AdminSetting::KEY_MAX_DASHBOARDS_PER_USER => 5,
					AdminSetting::KEY_MAX_WIDGETS_PER_DASHBOARD => 0,
					AdminSetting::KEY_ALLOW_MULTIPLE_DASHBOARDS => false,
					default => $default,
				};
			}
		);
		$this->dashboardMapper->method('countPersonalByUserId')->willReturn(1);

		$status = $this->service->getQuotaStatus(userId: 'alice');

		$this->assertSame(1, $status['maxDashboards']);
		$this->assertSame(0, $status['maxWidgetsPerDashboard']);
	}//end testGetQuotaStatusReflectsEffectiveLimit()
}//end class
