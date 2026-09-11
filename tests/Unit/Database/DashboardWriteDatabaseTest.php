<?php

/**
 * DashboardWriteDatabaseTest
 *
 * Dashboard create and update, through DashboardService, against a real
 * database: the stored row must carry every column the table requires.
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
use OCP\Server;

/**
 * @SuppressWarnings(PHPMD.StaticAccess) Server::get() is the only way in from a test.
 */
class DashboardWriteDatabaseTest extends RealDatabaseTestCase {
	/**
	 * Columns of `oc_launchpad_dashboards` that are NOT NULL with no default.
	 */
	private const REQUIRED = ['uuid', 'name', 'created_at', 'updated_at'];

	/**
	 * Create a dashboard as a fresh owner; remove it again in tearDown.
	 *
	 * @param string $owner The owner.
	 *
	 * @return int The stored dashboard id.
	 */
	private function createFor(string $owner): int {
		$dashboard = Server::get(DashboardService::class)->createDashboard(
			userId: $owner,
			name: 'Database write test'
		);
		$id = (int)$dashboard->getId();
		$this->cleanup(function () use ($id): void {
			$this->deleteRows('launchpad_widget_placements', 'dashboard_id', $id);
			$this->deleteRows('launchpad_dashboards', 'id', $id);
		});

		return $id;
	}//end createFor()

	/**
	 * A created dashboard is stored with every required column filled.
	 *
	 * @return void
	 */
	public function testCreateStoresEveryRequiredColumn(): void {
		$id = $this->createFor(owner: $this->uniqueId(prefix: 'db-owner'));

		$rows = $this->storedRows('launchpad_dashboards', 'id', $id);
		$this->assertCount(1, $rows, 'the dashboard was not stored');
		$this->assertColumnsFilled(row: $rows[0], columns: self::REQUIRED);
		$this->assertSame('Database write test', $rows[0]['name']);
	}//end testCreateStoresEveryRequiredColumn()

	/**
	 * An update is stored, and leaves every required column filled.
	 *
	 * @return void
	 */
	public function testUpdateIsStoredAndKeepsEveryRequiredColumn(): void {
		$owner = $this->uniqueId(prefix: 'db-owner');
		$id = $this->createFor(owner: $owner);

		Server::get(DashboardService::class)->updateDashboard(
			dashboardId: $id,
			userId: $owner,
			data: ['name' => 'Renamed in the database']
		);

		$rows = $this->storedRows('launchpad_dashboards', 'id', $id);
		$this->assertSame('Renamed in the database', $rows[0]['name'], 'the update was not stored');
		$this->assertColumnsFilled(row: $rows[0], columns: self::REQUIRED);
	}//end testUpdateIsStoredAndKeepsEveryRequiredColumn()
}//end class
