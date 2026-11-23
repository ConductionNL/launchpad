<?php

/**
 * GroupDashboardCreationTest
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

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\RoleAssignment;
use OCA\LaunchPad\Db\RoleAssignmentMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\GroupDashboardCreation;
use OCA\LaunchPad\Service\RoleService;
use OCP\IGroupManager;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\GroupDashboardCreation
 *
 * @uses \OCA\LaunchPad\Service\RoleService
 * @uses \OCA\LaunchPad\Db\RoleAssignment
 */
class GroupDashboardCreationTest extends TestCase {

	/**
	 * The rule for bob, who is in group "engineering", over the real RoleService.
	 *
	 * @param string $kind `nc-admin`, `lp-admin`, `editor`, `viewer` or `user`.
	 *
	 * @return GroupDashboardCreation The rule.
	 */
	private function ruleFor(string $kind): GroupDashboardCreation {
		$assigned = [
			'lp-admin' => RoleAssignment::ROLE_ADMIN,
			'editor'   => RoleAssignment::ROLE_EDITOR,
			'viewer'   => RoleAssignment::ROLE_VIEWER,
		];
		$direct = [];
		if (isset($assigned[$kind]) === true) {
			$assignment = new RoleAssignment();
			$assignment->setUserId('bob');
			$assignment->setRole($assigned[$kind]);
			$direct[] = $assignment;
		}

		$mapper = $this->createMock(originalClassName: RoleAssignmentMapper::class);
		$mapper->method('findByUser')->willReturn($direct);
		$mapper->method('findByGroupIds')->willReturn([]);

		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isAdmin')->willReturn($kind === 'nc-admin');

		$templates = $this->createMock(originalClassName: AdminTemplateService::class);
		$templates->method('getUserGroupIdsFor')->willReturn(['engineering']);

		$roles = new RoleService(
			mapper: $mapper,
			userManager: $this->createMock(originalClassName: IUserManager::class),
			groupManager: $groups,
			adminTemplateService: $templates,
		);

		return new GroupDashboardCreation(roles: $roles, templates: $templates);
	}//end ruleFor()

	/**
	 * Each kind of user against their own group, another group and the default group.
	 *
	 * @return array<string, array{0: string, 1: string, 2: bool}>
	 */
	public static function cases(): array {
		return [
			'nc admin, other group'     => ['nc-admin', 'finance', true],
			'nc admin, default group'   => ['nc-admin', Dashboard::DEFAULT_GROUP_ID, true],
			'lp admin, own group'       => ['lp-admin', 'engineering', true],
			'lp admin, other group'     => ['lp-admin', 'finance', true],
			'lp admin, default group'   => ['lp-admin', Dashboard::DEFAULT_GROUP_ID, true],
			'editor, own group'         => ['editor', 'engineering', true],
			'editor, other group'       => ['editor', 'finance', false],
			'editor, default group'     => ['editor', Dashboard::DEFAULT_GROUP_ID, false],
			'viewer, own group'         => ['viewer', 'engineering', false],
			'no role, own group'        => ['user', 'engineering', false],
			'no role, default group'    => ['user', Dashboard::DEFAULT_GROUP_ID, false],
		];
	}//end cases()

	/**
	 * Whether bob may create a group dashboard for the group.
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
			actual: $this->ruleFor(kind: $kind)->allows(userId: 'bob', groupId: $groupId)
		);
	}//end testWhoMayCreateAGroupDashboard()
}//end class
