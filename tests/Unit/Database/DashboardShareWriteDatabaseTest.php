<?php

/**
 * DashboardShareWriteDatabaseTest
 *
 * Sharing a dashboard with a user, through DashboardShareService, against a
 * real database: the stored share must carry every column the table requires.
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

use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardShareService;
use OCP\Server;

/**
 * @SuppressWarnings(PHPMD.StaticAccess) Server::get() is the only way in from a test.
 */
class DashboardShareWriteDatabaseTest extends RealDatabaseTestCase {
	/**
	 * A new share is stored with every required column filled.
	 *
	 * @return void
	 */
	public function testAddShareStoresEveryRequiredColumn(): void {
		$owner = $this->makeUser(prefix: 'db-owner');
		$recipient = $this->makeUser(prefix: 'db-recipient');

		$dashboard = Server::get(DashboardService::class)->createDashboard(
			userId: $owner,
			name: 'Share write test'
		);
		$dashboardId = (int)$dashboard->getId();
		$this->cleanup(function () use ($dashboardId): void {
			$this->deleteRows('launchpad_dashboard_shares', 'dashboard_id', $dashboardId);
			$this->deleteRows('launchpad_widget_placements', 'dashboard_id', $dashboardId);
			$this->deleteRows('launchpad_dashboards', 'id', $dashboardId);
		});

		Server::get(DashboardShareService::class)->addShare(
			dashboardId: $dashboardId,
			shareType: 'user',
			shareWith: $recipient,
			permissionLevel: 'view_only',
			callerId: $owner
		);

		$rows = $this->storedRows('launchpad_dashboard_shares', 'dashboard_id', $dashboardId);
		$this->assertCount(1, $rows, 'the share was not stored');
		$this->assertColumnsFilled(
			row: $rows[0],
			columns: ['dashboard_id', 'share_type', 'share_with', 'created_at']
		);
		$this->assertSame($recipient, $rows[0]['share_with']);
	}//end testAddShareStoresEveryRequiredColumn()
}//end class
