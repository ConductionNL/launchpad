<?php

/**
 * PermissionServiceGroupSharedTest
 *
 * Who may edit an installed demo showcase (REQ-DEMO-008).
 *
 * A showcase installs as a `group_shared` dashboard in the `default` sentinel
 * group with `permissionLevel: view_only`. The spec used to say an installed
 * showcase is "fully editable", which read as a contradiction of that
 * `view_only`. Neither statement was the whole rule, and no test pinned the
 * rule at all: for a group-shared dashboard, Nextcloud admins, LaunchPad
 * admins and Editors get `full`, and everyone else gets `view_only`, whatever
 * the row's own permission level says.
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
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardShareService;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\RoleService;
use OCP\IGroupManager;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Mirrors the constructor.
 */
class PermissionServiceGroupSharedTest extends TestCase {
	/**
	 * Build a PermissionService for one kind of user and an installed showcase.
	 *
	 * @param bool $launchpadAdmin Whether the user holds the LaunchPad admin role.
	 * @param bool $editor         Whether the user holds the Editor role or higher.
	 *
	 * @return PermissionService
	 */
	private function serviceFor(bool $launchpadAdmin, bool $editor): PermissionService {
		$showcase = new Dashboard();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$showcase->setId(12);
		$showcase->setType(Dashboard::TYPE_GROUP_SHARED);
		$showcase->setGroupId(Dashboard::DEFAULT_GROUP_ID);
		$showcase->setPermissionLevel(Dashboard::PERMISSION_VIEW_ONLY);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters

		$dashboards = $this->createMock(DashboardMapper::class);
		$dashboards->method('find')->willReturn($showcase);

		$roles = $this->createMock(RoleService::class);
		$roles->method('isViewer')->willReturn(false);
		$roles->method('isAdmin')->willReturn($launchpadAdmin);
		$roles->method('isEditorOrHigher')->willReturn($editor || $launchpadAdmin);

		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturn(false);

		return new PermissionService(
			dashboardMapper: $dashboards,
			placementMapper: $this->createMock(WidgetPlacementMapper::class),
			settingMapper: $this->createMock(AdminSettingMapper::class),
			shareService: $this->createMock(DashboardShareService::class),
			groupManager: $groups,
			adminTemplateService: $this->createMock(AdminTemplateService::class),
			roleService: $roles,
		);
	}//end serviceFor()

	/**
	 * A plain member of the default group sees an installed showcase and
	 * cannot change it.
	 *
	 * @return void
	 */
	public function testAMemberSeesAShowcaseReadOnly(): void {
		$service = $this->serviceFor(launchpadAdmin: false, editor: false);

		$this->assertTrue($service->canViewDashboard(userId: 'alice', dashboardId: 12));
		$this->assertFalse(
			$service->canEditDashboard(userId: 'alice', dashboardId: 12),
			'a plain member must not be able to edit an installed showcase'
		);
	}//end testAMemberSeesAShowcaseReadOnly()

	/**
	 * An Editor can edit an installed showcase despite its view_only level.
	 *
	 * @return void
	 */
	public function testAnEditorCanEditAShowcase(): void {
		$service = $this->serviceFor(launchpadAdmin: false, editor: true);

		$this->assertTrue($service->canEditDashboard(userId: 'eve', dashboardId: 12));
	}//end testAnEditorCanEditAShowcase()

	/**
	 * A LaunchPad admin can edit an installed showcase despite its view_only
	 * level, which is what the spec's old "fully editable" was reaching for.
	 *
	 * @return void
	 */
	public function testALaunchpadAdminCanEditAShowcase(): void {
		$service = $this->serviceFor(launchpadAdmin: true, editor: false);

		$this->assertTrue($service->canEditDashboard(userId: 'admin', dashboardId: 12));
	}//end testALaunchpadAdminCanEditAShowcase()
}//end class
