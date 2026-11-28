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
	 * @param bool   $viewer       Whether every user holds the Viewer role.
	 * @param array  $admins       The Nextcloud admins.
	 *
	 * @return array{0: PermissionService, 1: Dashboard, 2: WidgetPlacement}
	 */
	private function serviceFor(string $level, int $isCompulsory = 0, bool $viewer = false, array $admins = []): array {
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
		$roles->method('isViewer')->willReturn($viewer);
		$roles->method('isAdmin')->willReturn(false);
		$roles->method('isEditorOrHigher')->willReturn(false);

		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(static fn (string $userId): bool => in_array($userId, $admins, true));

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

	/**
	 * REQ-ROLE-008: a Viewer may neither rename nor delete even a dashboard
	 * they own; the owner without the role may do both.
	 *
	 * @return void
	 */
	public function testAViewerMayNotRenameOrDeleteEvenTheirOwnDashboard(): void {
		[$viewerService] = $this->serviceFor(level: Dashboard::PERMISSION_FULL, viewer: true);
		[$ownerService]  = $this->serviceFor(level: Dashboard::PERMISSION_FULL);

		$this->assertFalse($viewerService->canEditDashboardMetadata(userId: self::OWNER, dashboardId: 5));
		$this->assertFalse($viewerService->canDeleteDashboards(userId: self::OWNER));
		$this->assertTrue($ownerService->canEditDashboardMetadata(userId: self::OWNER, dashboardId: 5));
		$this->assertTrue($ownerService->canDeleteDashboards(userId: self::OWNER));
	}//end testAViewerMayNotRenameOrDeleteEvenTheirOwnDashboard()
	/**
	 * REQ-PERM-011: an admin template belongs to nobody; a Nextcloud admin
	 * may rename it, an ordinary user may not.
	 *
	 * @return void
	 */
	public function testANextcloudAdminRenamesAnAdminTemplateThatBelongsToNobody(): void {
		[$service, $dashboard] = $this->serviceFor(level: Dashboard::PERMISSION_FULL, admins: ['admin']);
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$dashboard->setType(Dashboard::TYPE_ADMIN_TEMPLATE);
		$dashboard->setUserId(null);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters

		$this->assertTrue($service->canEditDashboardMetadata(userId: 'admin', dashboardId: 5));
		$this->assertFalse($service->canEditDashboardMetadata(userId: self::OWNER, dashboardId: 5));
	}//end testANextcloudAdminRenamesAnAdminTemplateThatBelongsToNobody()

	/**
	 * REQ-TMPL-007: a Nextcloud admin adds, styles and removes the widgets
	 * of an admin template and may mark a new one compulsory; an ordinary
	 * user may do none of it.
	 *
	 * @return void
	 */
	public function testANextcloudAdminManagesTheWidgetsOfAnAdminTemplate(): void {
		[$service, $dashboard] = $this->serviceFor(level: Dashboard::PERMISSION_VIEW_ONLY, isCompulsory: 1, admins: ['admin']);
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$dashboard->setType(Dashboard::TYPE_ADMIN_TEMPLATE);
		$dashboard->setUserId(null);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters

		$this->assertTrue(condition: $service->canAddWidget(userId: 'admin', dashboardId: 5));
		$this->assertTrue(condition: $service->canStyleWidget(userId: 'admin', placementId: 10));
		$this->assertTrue(condition: $service->canRemoveWidget(userId: 'admin', placementId: 10));
		$this->assertTrue(condition: $service->canEditDashboard(userId: 'admin', dashboardId: 5));
		$this->assertTrue(condition: $service->mayPlaceCompulsory(userId: 'admin', dashboardId: 5));

		$this->assertFalse(condition: $service->canAddWidget(userId: self::OWNER, dashboardId: 5));
		$this->assertFalse(condition: $service->canRemoveWidget(userId: self::OWNER, placementId: 10));
		$this->assertFalse(condition: $service->canEditDashboard(userId: self::OWNER, dashboardId: 5));
		$this->assertFalse(condition: $service->mayPlaceCompulsory(userId: self::OWNER, dashboardId: 5));
	}//end testANextcloudAdminManagesTheWidgetsOfAnAdminTemplate()

	/**
	 * REQ-TMPL-007: the compulsory flag is for admin templates only; an
	 * admin adding a widget to an ordinary dashboard does not get it.
	 *
	 * @return void
	 */
	public function testTheCompulsoryFlagIsForAdminTemplatesOnly(): void {
		[$service] = $this->serviceFor(level: Dashboard::PERMISSION_FULL, admins: ['admin', self::OWNER]);

		$this->assertFalse(condition: $service->mayPlaceCompulsory(userId: self::OWNER, dashboardId: 5));
		$this->assertFalse(condition: $service->mayPlaceCompulsory(userId: 'admin', dashboardId: 99));
	}//end testTheCompulsoryFlagIsForAdminTemplatesOnly()
	/**
	 * REQ-PERM-001 / REQ-PERM-004: a refusal says why: a view-only
	 * dashboard, or a compulsory widget at add_only; else "Access denied".
	 *
	 * @return void
	 */
	public function testARefusedWidgetChangeSaysWhy(): void {
		[$viewOnly] = $this->serviceFor(level: Dashboard::PERMISSION_VIEW_ONLY);
		[$addOnly]  = $this->serviceFor(level: Dashboard::PERMISSION_ADD_ONLY, isCompulsory: 1);
		[$loose]    = $this->serviceFor(level: Dashboard::PERMISSION_ADD_ONLY);

		$this->assertSame('This dashboard is view-only', $viewOnly->widgetRefusal(userId: self::OWNER, dashboardId: 5));
		$this->assertSame('This dashboard is view-only', $viewOnly->widgetRefusal(userId: self::OWNER, placementId: 10));
		$this->assertSame(
			'Compulsory widgets cannot be removed at this permission level',
			$addOnly->widgetRefusal(userId: self::OWNER, placementId: 10)
		);
		$this->assertSame('Access denied', $loose->widgetRefusal(userId: self::OWNER, placementId: 10));
		$this->assertSame('Access denied', $viewOnly->widgetRefusal(userId: 'bob', dashboardId: 5));
	}//end testARefusedWidgetChangeSaysWhy()
}//end class
