<?php

/**
 * WidgetPlacementWriteDatabaseTest
 *
 * Adding a widget, through PlacementService, against a real database: the
 * stored placement must carry every column the table requires.
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
use OCA\LaunchPad\Service\PlacementService;
use OCP\Server;

/**
 * @SuppressWarnings(PHPMD.StaticAccess) Server::get() is the only way in from a test.
 */
class WidgetPlacementWriteDatabaseTest extends RealDatabaseTestCase {
	/**
	 * An added widget is stored with every required column filled.
	 *
	 * @return void
	 */
	public function testAddWidgetStoresEveryRequiredColumn(): void {
		$dashboard = Server::get(DashboardService::class)->createDashboard(
			userId: $this->uniqueId(prefix: 'db-owner'),
			name: 'Placement write test'
		);
		$dashboardId = (int)$dashboard->getId();
		$this->cleanup(function () use ($dashboardId): void {
			$this->deleteRows('launchpad_widget_placements', 'dashboard_id', $dashboardId);
			$this->deleteRows('launchpad_dashboards', 'id', $dashboardId);
		});

		$placement = Server::get(PlacementService::class)->addWidget(
			dashboardId: $dashboardId,
			widgetId: 'activity'
		);

		$rows = $this->storedRows('launchpad_widget_placements', 'id', (int)$placement->getId());
		$this->assertCount(1, $rows, 'the placement was not stored');
		$this->assertColumnsFilled(
			row: $rows[0],
			columns: ['dashboard_id', 'widget_id', 'created_at', 'updated_at']
		);
		$this->assertSame('activity', $rows[0]['widget_id']);
	}//end testAddWidgetStoresEveryRequiredColumn()
}//end class
