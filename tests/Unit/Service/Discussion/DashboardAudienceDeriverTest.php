<?php

/**
 * DashboardAudienceDeriver Test
 *
 * The audience a dashboard's discussion object carries is the audience
 * PermissionService lets view the dashboard.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service\Discussion
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/openregister-leaf-integrations/tasks.md#task-7
 */

declare(strict_types=1);

namespace Unit\Service\Discussion;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardShare;
use OCA\LaunchPad\Service\Discussion\DashboardAudienceDeriver;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see DashboardAudienceDeriver}.
 */
class DashboardAudienceDeriverTest extends TestCase {

	/**
	 * A personal dashboard nobody shares is its owner's alone.
	 *
	 * @return void
	 */
	public function testAnUnsharedPersonalDashboardIsNotCollaborative(): void {
		$audience = (new DashboardAudienceDeriver())->derive(dashboard: $this->dashboard(type: Dashboard::TYPE_USER, owner: 'alice'), shares: []);

		$this->assertFalse(condition: $audience->collaborative);
		$this->assertSame(expected: ['alice'], actual: $audience->users);
		$this->assertSame(expected: [], actual: $audience->groups);
	}//end testAnUnsharedPersonalDashboardIsNotCollaborative()

	/**
	 * A shared dashboard is seen by its owner and every user and group it is shared with.
	 *
	 * @return void
	 */
	public function testASharedDashboardNamesTheOwnerAndEveryRecipient(): void {
		$audience = (new DashboardAudienceDeriver())->derive(
			dashboard: $this->dashboard(type: Dashboard::TYPE_USER, owner: 'alice'),
			shares: [
				$this->share(type: DashboardShare::SHARE_TYPE_USER, with: 'bob'),
				$this->share(type: DashboardShare::SHARE_TYPE_GROUP, with: 'finance'),
				$this->share(type: DashboardShare::SHARE_TYPE_USER, with: 'bob'),
			]
		);

		$this->assertTrue(condition: $audience->collaborative);
		$this->assertSame(expected: ['alice', 'bob'], actual: $audience->users);
		$this->assertSame(expected: ['finance'], actual: $audience->groups);
		$this->assertFalse(condition: $audience->everyone);
	}//end testASharedDashboardNamesTheOwnerAndEveryRecipient()

	/**
	 * A group dashboard is seen by its group; the default group dashboard by everyone.
	 *
	 * @return void
	 */
	public function testAGroupDashboardIsSeenByItsGroupAndTheDefaultOneByEveryone(): void {
		$group = (new DashboardAudienceDeriver())->derive(dashboard: $this->dashboard(type: Dashboard::TYPE_GROUP_SHARED, group: 'sales'), shares: []);
		$this->assertSame(expected: ['sales'], actual: $group->groups);
		$this->assertTrue(condition: $group->collaborative);
		$this->assertFalse(condition: $group->everyone);

		$defaultDashboard = $this->dashboard(type: Dashboard::TYPE_GROUP_SHARED, group: Dashboard::DEFAULT_GROUP_ID);
		$default          = (new DashboardAudienceDeriver())->derive(dashboard: $defaultDashboard, shares: []);
		$this->assertTrue(condition: $default->everyone);
		$this->assertSame(expected: [], actual: $default->groups);
	}//end testAGroupDashboardIsSeenByItsGroupAndTheDefaultOneByEveryone()

	/**
	 * An admin template is never discussed.
	 *
	 * @return void
	 */
	public function testAnAdminTemplateIsNotCollaborative(): void {
		$audience = (new DashboardAudienceDeriver())->derive(
			dashboard: $this->dashboard(type: Dashboard::TYPE_ADMIN_TEMPLATE, owner: 'admin'),
			shares: [$this->share(type: DashboardShare::SHARE_TYPE_USER, with: 'bob')]
		);

		$this->assertFalse(condition: $audience->collaborative);
		$this->assertSame(expected: [], actual: $audience->users);
	}//end testAnAdminTemplateIsNotCollaborative()

	/**
	 * A dashboard.
	 *
	 * @param string      $type  Its type.
	 * @param string|null $owner Its owner.
	 * @param string|null $group Its group.
	 *
	 * @return Dashboard
	 */
	private function dashboard(string $type, ?string $owner = null, ?string $group = null): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setType($type);
		$dashboard->setUserId($owner);
		$dashboard->setGroupId($group);
		return $dashboard;
	}//end dashboard()

	/**
	 * A share.
	 *
	 * @param string $type Its type.
	 * @param string $with Its recipient.
	 *
	 * @return DashboardShare
	 */
	private function share(string $type, string $with): DashboardShare {
		$share = new DashboardShare();
		$share->setShareType($type);
		$share->setShareWith($with);
		return $share;
	}//end share()
}//end class
