<?php

/**
 * RoleLayoutSeedingDatabaseTest
 *
 * REQ-RFP-002 against a real database: a new user whose group carries
 * RoleLayoutDefault rows is seeded from them.
 *
 * 🔴 FOUR DEFECTS THIS PINS, each measured on a live instance before the fix.
 *
 *  1. The seeding never ran. `getEffectiveDashboard()` resolved the
 *     instance-wide `default` group dashboard, seeded on install since #361,
 *     before it reached `tryCreateFromTemplate()`.
 *  2. The second user ever to auto-provision got HTTP 500. Root slugs share
 *     one namespace across owners and every auto-provisioned dashboard is
 *     named 'My Dashboard', so the second failed `validateSlugUnique()`.
 *  3. The hardcoded tile/tile/tile/files set was created on top of the role
 *     layout, though the comment beside it called it a fallback.
 *  4. `isCompulsory` was dropped, so a compulsory role widget seeded
 *     removable, which the scenario names explicitly.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Database
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Database;

use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Server;

/**
 * @SuppressWarnings(PHPMD.StaticAccess) Server::get() is the only way in from a test.
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) One scenario, several services.
 */
class RoleLayoutSeedingDatabaseTest extends RealDatabaseTestCase {
	/**
	 * Give a fresh group one layout default and put it in `group_order`.
	 *
	 * @param bool $compulsory Whether the seeded widget is compulsory.
	 *
	 * @return string The group id.
	 */
	private function groupWithLayoutDefault(bool $compulsory = true): string {
		$groupId = $this->uniqueId(prefix: 'db-role');
		Server::get(IGroupManager::class)->createGroup($groupId);
		$this->cleanup(static function () use ($groupId): void {
			Server::get(IGroupManager::class)->get($groupId)?->delete();
		});

		Server::get(RoleFeaturePermissionService::class)->saveLayoutDefault(
			data: [
				'groupId' => $groupId,
				'name' => 'Seeded by the database test',
				'widgetId' => 'activity',
				'gridX' => 0,
				'gridY' => 0,
				'gridWidth' => 8,
				'gridHeight' => 6,
				'sortOrder' => 1,
				'isCompulsory' => $compulsory,
			]
		);
		$this->cleanup(fn () => $this->deleteRows('launchpad_role_layout_def', 'group_id', $groupId));

		$settings = Server::get(AdminSettingsService::class);
		$previousOrder = $settings->getGroupOrder();
		$settings->setGroupOrder(array_values([...$previousOrder, $groupId]));
		$this->cleanup(static function () use ($settings, $previousOrder): void {
			$settings->setGroupOrder($previousOrder);
		});

		$settings->updateSettings(allowUserDash: true);

		return $groupId;
	}//end groupWithLayoutDefault()

	/**
	 * A member of that group, whose dashboards are removed in tearDown.
	 *
	 * @param string $groupId The group.
	 *
	 * @return string The user id.
	 */
	private function memberOf(string $groupId): string {
		$uid = $this->makeUser(prefix: 'db-member');
		$user = Server::get(IUserManager::class)->get($uid);
		$this->assertNotNull($user, 'CONTROL: the member account exists');
		Server::get(IGroupManager::class)->get($groupId)?->addUser($user);
		$this->cleanup(function () use ($uid): void {
			foreach ($this->storedRows('launchpad_dashboards', 'user_id', $uid) as $row) {
				$this->deleteRows('launchpad_widget_placements', 'dashboard_id', (int)$row['id']);
			}

			$this->deleteRows('launchpad_dashboards', 'user_id', $uid);
		});

		return $uid;
	}//end memberOf()

	/**
	 * The user's own dashboard carries exactly their role layout.
	 *
	 * @return void
	 */
	public function testANewUserIsSeededFromTheirGroupsRoleLayout(): void {
		$groupId = $this->groupWithLayoutDefault();
		$uid = $this->memberOf(groupId: $groupId);

		Server::get(DashboardService::class)->getEffectiveDashboard(userId: $uid);

		$dashboards = $this->storedRows('launchpad_dashboards', 'user_id', $uid);
		$this->assertCount(1, $dashboards, 'the user was not given their own dashboard');

		$placements = $this->storedRows('launchpad_widget_placements', 'dashboard_id', (int)$dashboards[0]['id']);
		$this->assertCount(
			1,
			$placements,
			'the role layout did not land alone: ' . json_encode(array_column($placements, 'widget_id'))
		);
		$this->assertSame('activity', $placements[0]['widget_id']);
		$this->assertSame(8, (int)$placements[0]['grid_width']);
		$this->assertSame(6, (int)$placements[0]['grid_height']);
		$this->assertSame(1, (int)$placements[0]['is_compulsory'], 'isCompulsory was dropped on the way in');
	}//end testANewUserIsSeededFromTheirGroupsRoleLayout()

	/**
	 * A second user provisions too, rather than colliding on the root slug.
	 *
	 * @return void
	 */
	public function testASecondUserAlsoGetsADashboard(): void {
		$groupId = $this->groupWithLayoutDefault(compulsory: false);
		$first = $this->memberOf(groupId: $groupId);
		$second = $this->memberOf(groupId: $groupId);

		$service = Server::get(DashboardService::class);
		$service->getEffectiveDashboard(userId: $first);
		$service->getEffectiveDashboard(userId: $second);

		$firstRows = $this->storedRows('launchpad_dashboards', 'user_id', $first);
		$secondRows = $this->storedRows('launchpad_dashboards', 'user_id', $second);
		$this->assertCount(1, $firstRows, 'the first user was not given a dashboard');
		$this->assertCount(1, $secondRows, 'the second user was refused a dashboard');
		$this->assertNotSame(
			$firstRows[0]['slug'],
			$secondRows[0]['slug'],
			'both dashboards took the same root slug'
		);
	}//end testASecondUserAlsoGetsADashboard()
}//end class
