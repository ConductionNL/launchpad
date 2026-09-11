<?php

/**
 * PermissionServiceLevelsTest
 *
 * What each permission level allows (REQ-PERM-001, REQ-PERM-002, REQ-PERM-003,
 * REQ-PERM-004, REQ-PERM-007, REQ-PERM-009, REQ-PERM-010).
 *
 * These are the rules that decide whether one user may change another's
 * dashboard, and nothing tested them: the e2e in
 * `tests/e2e/dashboard-permission-levels.spec.ts` drives the levels a user can
 * actually be given through the product (a share), but three of the
 * scenarios describe a dashboard the user OWNS at a restricted level, and one
 * describes a compulsory widget. Neither state is reachable through any API on
 * a normal instance:
 *
 *  - a dashboard a user creates is always `full` (`DashboardFactory`), and the
 *    only path that hands a user an owned template-derived dashboard
 *    (`DashboardService::tryCreateFromTemplate()`) is pre-empted on every
 *    install by the seeded default dashboard;
 *  - `isCompulsory` is only ever set by a template copy or by a role layout
 *    default, never by an API call against an existing placement.
 *
 * So they are pinned here, at the service that decides them.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardShareService;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\RoleService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IGroupManager;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Mirrors the constructor.
 * @SuppressWarnings(PHPMD.TooManyPublicMethods) One test per rule.
 */
class PermissionServiceLevelsTest extends TestCase {
	/** The owner every fixture dashboard belongs to. */
	private const OWNER = 'alice';

	/**
	 * Build the service for one owned dashboard at a given level.
	 *
	 * @param string $level        The dashboard's own permission level.
	 * @param int    $isCompulsory Whether its placement is compulsory.
	 *
	 * @return array{0: PermissionService, 1: Dashboard, 2: WidgetPlacement}
	 */
	private function serviceFor(string $level, int $isCompulsory = 0): array {
		$dashboard = new Dashboard();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$dashboard->setId(5);
		$dashboard->setUuid('dash-uuid');
		$dashboard->setType(Dashboard::TYPE_USER);
		$dashboard->setUserId(self::OWNER);
		$dashboard->setPermissionLevel($level);

		$placement = new WidgetPlacement();
		$placement->setId(10);
		$placement->setDashboardId(5);
		$placement->setWidgetId('text');
		$placement->setIsCompulsory($isCompulsory);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters

		$dashboards = $this->createMock(DashboardMapper::class);
		$dashboards->method('find')->willReturnCallback(
			static function (int $id) use ($dashboard): Dashboard {
				if ($id !== 5) {
					throw new DoesNotExistException(msg: 'no dashboard ' . $id);
				}
				return $dashboard;
			}
		);

		$placements = $this->createMock(WidgetPlacementMapper::class);
		$placements->method('find')->willReturnCallback(
			static function (int $id) use ($placement): WidgetPlacement {
				if ($id !== 10) {
					throw new DoesNotExistException(msg: 'no placement ' . $id);
				}
				return $placement;
			}
		);

		$roles = $this->createMock(RoleService::class);
		$roles->method('isViewer')->willReturn(false);
		$roles->method('isAdmin')->willReturn(false);
		$roles->method('isEditorOrHigher')->willReturn(false);

		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturn(false);

		$shares = $this->createMock(DashboardShareService::class);
		$shares->method('resolveSharedDashboards')->willReturn([]);

		$templates = $this->createMock(AdminTemplateService::class);
		$templates->method('getUserGroupIdsFor')->willReturn([]);

		$service = new PermissionService(
			dashboardMapper: $dashboards,
			placementMapper: $placements,
			settingMapper: $this->createMock(AdminSettingMapper::class),
			shareService: $shares,
			groupManager: $groups,
			adminTemplateService: $templates,
			roleService: $roles,
		);

		return [$service, $dashboard, $placement];
	}//end serviceFor()

	/**
	 * REQ-PERM-001: view_only permits nothing but reading.
	 *
	 * @return void
	 */
	public function testViewOnlyAllowsNoChanges(): void {
		[$service] = $this->serviceFor(level: Dashboard::PERMISSION_VIEW_ONLY);

		$this->assertFalse(
			$service->canAddWidget(userId: self::OWNER, dashboardId: 5),
			'view_only must not permit adding a widget'
		);
		$this->assertFalse(
			$service->canStyleWidget(userId: self::OWNER, placementId: 10),
			'view_only must not permit styling a widget'
		);
		$this->assertFalse(
			$service->canRemoveWidget(userId: self::OWNER, placementId: 10),
			'view_only must not permit removing a widget'
		);
	}//end testViewOnlyAllowsNoChanges()

	/**
	 * REQ-PERM-002 and REQ-PERM-009: add_only permits adding, styling and
	 * removing a widget that is not compulsory.
	 *
	 * @return void
	 */
	public function testAddOnlyAllowsEverythingButRemovingACompulsoryWidget(): void {
		[$service] = $this->serviceFor(level: Dashboard::PERMISSION_ADD_ONLY);

		$this->assertTrue($service->canAddWidget(userId: self::OWNER, dashboardId: 5));
		$this->assertTrue($service->canStyleWidget(userId: self::OWNER, placementId: 10));
		$this->assertTrue($service->canRemoveWidget(userId: self::OWNER, placementId: 10));

		[$compulsory] = $this->serviceFor(
			level: Dashboard::PERMISSION_ADD_ONLY,
			isCompulsory: 1
		);
		$this->assertFalse(
			$compulsory->canRemoveWidget(userId: self::OWNER, placementId: 10),
			'add_only must not permit removing a compulsory widget'
		);
	}//end testAddOnlyAllowsEverythingButRemovingACompulsoryWidget()

	/**
	 * REQ-PERM-003: full permits removing a compulsory widget too.
	 *
	 * @return void
	 */
	public function testFullAllowsRemovingACompulsoryWidget(): void {
		[$service] = $this->serviceFor(
			level: Dashboard::PERMISSION_FULL,
			isCompulsory: 1
		);

		$this->assertTrue(
			$service->canRemoveWidget(userId: self::OWNER, placementId: 10),
			'full must permit removing a compulsory widget'
		);
	}//end testFullAllowsRemovingACompulsoryWidget()

	/**
	 * REQ-PERM-007: the permission level governs widgets, not the dashboard's
	 * own name. An owner may rename a view_only dashboard.
	 *
	 * @return void
	 */
	public function testMetadataEditingFollowsOwnershipNotLevel(): void {
		[$service] = $this->serviceFor(level: Dashboard::PERMISSION_VIEW_ONLY);

		$this->assertTrue(
			$service->canEditDashboardMetadata(userId: self::OWNER, dashboardId: 5),
			'the owner of a view_only dashboard may still rename it'
		);
		$this->assertFalse(
			$service->canEditDashboardMetadata(userId: 'bob', dashboardId: 5),
			'someone who does not own it may not rename it'
		);
	}//end testMetadataEditingFollowsOwnershipNotLevel()

	/**
	 * REQ-PERM-010: a user with no relationship to the dashboard is refused
	 * whatever its level says, and the refusal does not depend on the level.
	 *
	 * @return void
	 */
	public function testAStrangerIsRefusedEvenOnAFullDashboard(): void {
		[$service] = $this->serviceFor(level: Dashboard::PERMISSION_FULL);

		$this->assertFalse($service->canAddWidget(userId: 'bob', dashboardId: 5));
		$this->assertFalse($service->canStyleWidget(userId: 'bob', placementId: 10));
		$this->assertFalse($service->canRemoveWidget(userId: 'bob', placementId: 10));
	}//end testAStrangerIsRefusedEvenOnAFullDashboard()
}//end class
