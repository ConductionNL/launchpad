<?php

/**
 * AcknowledgedWidgetsTest
 *
 * "Widgets that ask for confirmation" (REQ-ACK-008): one row per key, the
 * template's placement and dashboard first, the title as named, the counts and
 * deadline of the audience report, and a key without a report left out.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/dashboard-acknowledgements/spec.md#requirement-an-editor-can-see-who-has-confirmed-req-ack-008
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Kernel\Contracts\AcknowledgementServiceInterface;
use OCA\LaunchPad\Service\AcknowledgedWidgets;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AcknowledgedWidgets.
 *
 * @covers \OCA\LaunchPad\Service\AcknowledgedWidgets
 */
class AcknowledgedWidgetsTest extends TestCase {

	/**
	 * A placement.
	 *
	 * @param int         $dashboardId The dashboard.
	 * @param string      $key         The announcement key.
	 * @param string|null $title       The custom title.
	 * @param string      $widgetId    The widget type.
	 *
	 * @return WidgetPlacement
	 */
	private function placement(int $dashboardId, string $key, ?string $title, string $widgetId = 'text'): WidgetPlacement {
		$placement = new WidgetPlacement();
		$placement->setDashboardId($dashboardId);
		$placement->setAnnouncementKey($key);
		$placement->setCustomTitle($title);
		$placement->setWidgetId($widgetId);

		return $placement;
	}//end placement()

	/**
	 * A dashboard.
	 *
	 * @param string $name The name.
	 * @param string $type The type.
	 *
	 * @return Dashboard
	 */
	private function dashboard(string $name, string $type): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setName($name);
		$dashboard->setType($type);

		return $dashboard;
	}//end dashboard()

	/**
	 * One row per key, from the template's placement, with the report's figures.
	 *
	 * @return void
	 */
	public function testOneRowPerKeyFromTheTemplate(): void {
		$placements = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$placements->method('findAcknowledgedWithKey')->willReturn(
			[
				$this->placement(dashboardId: 1, key: 'ak-1', title: 'Copy title'),
				$this->placement(dashboardId: 2, key: 'ak-1', title: 'New expense rules'),
				$this->placement(dashboardId: 3, key: 'ak-2', title: ' ', widgetId: 'note'),
				$this->placement(dashboardId: 9, key: 'ak-gone', title: 'Gone'),
			]
		);
		$dashboards = $this->createMock(originalClassName: DashboardMapper::class);
		$dashboards->method('find')->willReturnCallback(
			fn (int $id): Dashboard => match ($id) {
				1 => $this->dashboard(name: 'Mijn dashboard', type: 'user'),
				2 => $this->dashboard(name: 'Intranet Zuiddrecht', type: Dashboard::TYPE_ADMIN_TEMPLATE),
				default => throw new DoesNotExistException('gone'),
			}
		);
		$acknowledgements = $this->createMock(originalClassName: AcknowledgementServiceInterface::class);
		$acknowledgements->method('report')->willReturnCallback(
			static function (string $key): array {
				if ($key === 'ak-gone') {
					throw new DoesNotExistException('gone');
				}

				return ['deadline' => '2026-12-01', 'overdue' => false, 'acknowledgedCount' => 3, 'pendingCount' => 2];
			}
		);

		$rows = (new AcknowledgedWidgets(placements: $placements, dashboards: $dashboards, acknowledgements: $acknowledgements))->list();

		$this->assertSame(
			expected: [
				[
					'announcementKey'   => 'ak-1',
					'title'             => 'New expense rules',
					'dashboard'         => 'Intranet Zuiddrecht',
					'deadline'          => '2026-12-01',
					'overdue'           => false,
					'acknowledgedCount' => 3,
					'pendingCount'      => 2,
				],
				[
					'announcementKey'   => 'ak-2',
					'title'             => 'note',
					'dashboard'         => '',
					'deadline'          => '2026-12-01',
					'overdue'           => false,
					'acknowledgedCount' => 3,
					'pendingCount'      => 2,
				],
			],
			actual: $rows
		);
	}//end testOneRowPerKeyFromTheTemplate()
}//end class
