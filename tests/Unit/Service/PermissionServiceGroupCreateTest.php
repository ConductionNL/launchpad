<?php

/**
 * PermissionServiceGroupCreateTest
 *
 * Who may create a group dashboard for a group (REQ-DASH-014): a Nextcloud
 * administrator or a LaunchPad Admin for any group, an Editor only for a
 * group they belong to and never for the default group, anyone else never.
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\PermissionService
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Mirrors the constructor.
 */
class PermissionServiceGroupCreateTest extends TestCase {

	/**
	 * A PermissionService for one user, who is in group "engineering".
	 *
	 * @param string $kind `nc-admin`, `lp-admin`, `editor` or `user`.
	 *
	 * @return PermissionService The service.
	 */
	private function serviceFor(string $kind): PermissionService {
		$roles = $this->createMock(originalClassName: RoleService::class);
		$roles->method('isAdmin')->willReturn($kind === 'lp-admin');
		$roles->method('isEditorOrHigher')->willReturn(in_array(needle: $kind, haystack: ['lp-admin', 'editor'], strict: true));

		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isAdmin')->willReturn($kind === 'nc-admin');

		$templates = $this->createMock(originalClassName: AdminTemplateService::class);
		$templates->method('getUserGroupIdsFor')->willReturn(['engineering']);

		return new PermissionService(
			dashboardMapper: $this->createMock(originalClassName: DashboardMapper::class),
			placementMapper: $this->createMock(originalClassName: WidgetPlacementMapper::class),
			settingMapper: $this->createMock(originalClassName: AdminSettingMapper::class),
			shareService: $this->createMock(originalClassName: DashboardShareService::class),
			groupManager: $groups,
			adminTemplateService: $templates,
			roleService: $roles,
		);
	}//end serviceFor()

	/**
	 * Each kind of user against their own group, another group and the default group.
	 *
	 * @return array<string, array{0: string, 1: string, 2: bool}>
	 */
	public static function cases(): array {
		return [
			'nc admin, own group'       => ['nc-admin', 'engineering', true],
			'nc admin, other group'     => ['nc-admin', 'finance', true],
			'nc admin, default group'   => ['nc-admin', Dashboard::DEFAULT_GROUP_ID, true],
			'lp admin, other group'     => ['lp-admin', 'finance', true],
			'lp admin, default group'   => ['lp-admin', Dashboard::DEFAULT_GROUP_ID, true],
			'editor, own group'         => ['editor', 'engineering', true],
			'editor, other group'       => ['editor', 'finance', false],
			'editor, default group'     => ['editor', Dashboard::DEFAULT_GROUP_ID, false],
			'plain user, own group'     => ['user', 'engineering', false],
			'plain user, default group' => ['user', Dashboard::DEFAULT_GROUP_ID, false],
		];
	}//end cases()

	/**
	 * Whether the user may create a group dashboard for the group.
	 *
	 * @param string $kind    The kind of user.
	 * @param string $groupId The group.
	 * @param bool   $allowed Whether it is allowed.
	 *
	 * @return void
	 */
	#[DataProvider('cases')]
	public function testWhoMayCreateAGroupDashboard(string $kind, string $groupId, bool $allowed): void {
		$this->assertSame(
			expected: $allowed,
			actual: $this->serviceFor(kind: $kind)->canCreateGroupDashboard(userId: 'bob', groupId: $groupId)
		);
	}//end testWhoMayCreateAGroupDashboard()
}//end class
