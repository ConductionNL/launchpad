<?php

/**
 * Unit tests for editing one dashboard together (building-edit-a-dashboard-together,
 * tasks 1.2, 1.3 and 1.5): two editors on different widgets, two editors on the
 * same widget, and where a new widget lands.
 *
 * PlacementService and FreeSpotFinder are the real classes. The placement
 * mapper is an in-memory double whose claimVersion() compares and raises a
 * widget's version the way the one SQL statement does.
 *
 * @category Test
 * @package  Unit\Service
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Exception\StalePlacementException;
use OCA\LaunchPad\Service\FreeSpotFinder;
use OCA\LaunchPad\Service\PlacementService;
use OCA\LaunchPad\Service\PlacementUpdater;
use OCA\LaunchPad\Service\PlacementUpdaters;
use OCA\LaunchPad\Service\TileUpdater;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\PlacementService
 * @covers \OCA\LaunchPad\Service\FreeSpotFinder
 * @covers \OCA\LaunchPad\Exception\StalePlacementException
 * @uses   \OCA\LaunchPad\Db\WidgetPlacement
 * @uses   \OCA\LaunchPad\Db\Dashboard
 * @uses   \OCA\LaunchPad\Service\PlacementUpdater
 * @uses   \OCA\LaunchPad\Service\PlacementUpdaters
 * @uses   \OCA\LaunchPad\Service\TileUpdater
 */
class DashboardCoEditingTest extends TestCase {

	/**
	 * The saved placements by id.
	 *
	 * @var array<int,WidgetPlacement>
	 */
	private array $saved = [];

	/**
	 * Seed dashboard 7 with a calendar (id 1) and a news widget (id 2), both at version 3.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->saved = [
			1 => $this->placement(id: 1, widgetId: 'calendar', x: 0, y: 0, w: 4, h: 4, version: 3),
			2 => $this->placement(id: 2, widgetId: 'news', x: 4, y: 0, w: 4, h: 4, version: 3),
		];
	}

	/**
	 * Marieke resizes the calendar and Sanne moves the news widget: both changes are saved.
	 *
	 * @return void
	 */
	public function testChangesToDifferentWidgetsBothSurvive(): void {
		$service = $this->service();

		$calendar = $service->updatePlacement(placementId: 1, data: ['version' => 3, 'gridWidth' => 6]);
		$news     = $service->updatePlacement(placementId: 2, data: ['version' => 3, 'gridY' => 4]);

		$this->assertSame([6, 4], [$this->saved[1]->getGridWidth(), $this->saved[1]->getVersion()]);
		$this->assertSame([4, 4], [$this->saved[2]->getGridY(), $this->saved[2]->getVersion()]);
		$this->assertSame(4, $calendar->getVersion());
		$this->assertSame(4, $news->jsonSerialize()['version']);
	}

	/**
	 * Marieke saves the calendar first; Sanne's later save on the same version is refused with Marieke's version.
	 *
	 * @return void
	 */
	public function testTheLaterChangeToOneWidgetIsRefusedNotLost(): void {
		$service = $this->service();
		$service->updatePlacement(placementId: 1, data: ['version' => 3, 'customTitle' => 'Agenda']);

		try {
			$service->updatePlacement(placementId: 1, data: ['version' => 3, 'customTitle' => 'Kalender']);
			$this->fail('A save on an older version must be refused.');
		} catch (StalePlacementException $e) {
			$this->assertSame('Agenda', $e->getCurrent()->getCustomTitle());
			$this->assertSame(4, $e->getCurrent()->getVersion());
			$this->assertSame('A colleague changed this widget first.', $e->getMessage());
		}

		$this->assertSame('Agenda', $this->saved[1]->getCustomTitle());

		// On top of Marieke's version, Sanne's change goes through.
		$service->updatePlacement(placementId: 1, data: ['version' => 4, 'customTitle' => 'Kalender']);
		$this->assertSame(['Kalender', 5], [$this->saved[1]->getCustomTitle(), $this->saved[1]->getVersion()]);
	}

	/**
	 * A save without a version (an older client) still raises the version, so later versioned saves see it.
	 *
	 * @return void
	 */
	public function testASaveWithoutAVersionRaisesIt(): void {
		$this->service()->updatePlacement(placementId: 2, data: ['gridX' => 8]);

		$this->assertSame([8, 4], [$this->saved[2]->getGridX(), $this->saved[2]->getVersion()]);
		$this->expectException(StalePlacementException::class);
		$this->service()->updatePlacement(placementId: 2, data: ['version' => '3', 'gridX' => 0]);
	}

	/**
	 * A widget added over the occupied spot takes the first free spot instead.
	 *
	 * @return void
	 */
	public function testANewWidgetTakesTheFirstFreeSpot(): void {
		$added = $this->service(columns: 12)->addWidget(dashboardId: 7, widgetId: 'notes');

		$this->assertSame([8, 0, 4, 4], [$added->getGridX(), $added->getGridY(), $added->getGridWidth(), $added->getGridHeight()]);
	}

	/**
	 * A widget added while a colleague just filled the first free spot goes to the next one; a free asked spot is kept.
	 *
	 * @return void
	 */
	public function testANewWidgetAfterAColleaguesMoveTakesTheNextFreeSpot(): void {
		$this->saved[3] = $this->placement(id: 3, widgetId: 'links', x: 8, y: 0, w: 4, h: 4, version: 0);

		$added = $this->service(columns: 12)->addWidget(dashboardId: 7, widgetId: 'notes');
		$this->assertSame([0, 4], [$added->getGridX(), $added->getGridY()]);

		$tile = $this->service(columns: 12)->addTileFromArray(dashboardId: 7, tileData: ['gridX' => 10, 'gridY' => 6, 'gridWidth' => 2, 'gridHeight' => 2, 'title' => 'T']);
		$this->assertSame([10, 6], [$tile->getGridX(), $tile->getGridY()]);
	}

	/**
	 * The finder keeps a free asked spot, clamps a too-wide widget and goes below when every row is full.
	 *
	 * @return void
	 */
	public function testTheFinderEdgeCases(): void {
		$finder = new FreeSpotFinder();
		$full   = [['gridX' => 0, 'gridY' => 0, 'gridWidth' => 12, 'gridHeight' => 2]];

		$this->assertSame(['gridX' => 0, 'gridY' => 2, 'gridWidth' => 4, 'gridHeight' => 4], $finder->place(occupied: $full, columns: 12, wanted: ['gridX' => 0, 'gridY' => 0, 'gridWidth' => 4, 'gridHeight' => 4]));
		$this->assertSame(['gridX' => 0, 'gridY' => 0, 'gridWidth' => 6, 'gridHeight' => 1], $finder->place(occupied: [], columns: 6, wanted: ['gridX' => 3, 'gridY' => 0, 'gridWidth' => 20, 'gridHeight' => 0]));
		$this->assertSame(['gridX' => 2, 'gridY' => 5, 'gridWidth' => 2, 'gridHeight' => 2], $finder->place(occupied: $full, columns: 12, wanted: ['gridX' => 2, 'gridY' => 5, 'gridWidth' => 2, 'gridHeight' => 2]));
		$this->assertSame(['gridX' => 4, 'gridY' => 0, 'gridWidth' => 2, 'gridHeight' => 2], $finder->place(occupied: [['gridX' => 0, 'gridY' => 0, 'gridWidth' => 4, 'gridHeight' => 1]], columns: 12, wanted: ['gridX' => 1, 'gridY' => 0, 'gridWidth' => 2, 'gridHeight' => 2]));
	}

	/**
	 * The service over the in-memory placements.
	 *
	 * @param int|null $columns The dashboard's column count, or null for no dashboard mapper.
	 *
	 * @return PlacementService
	 */
	private function service(?int $columns=null): PlacementService {
		$mapper = $this->createMock(WidgetPlacementMapper::class);
		$mapper->method('find')->willReturnCallback(fn (int $id): WidgetPlacement => clone $this->saved[$id]);
		$mapper->method('findByDashboardId')->willReturnCallback(fn (): array => array_values($this->saved));
		$mapper->method('update')->willReturnCallback(
			function (WidgetPlacement $placement): WidgetPlacement {
				$this->saved[$placement->getId()] = clone $placement;
				return $placement;
			}
		);
		$mapper->method('insert')->willReturnArgument(0);
		$mapper->method('claimVersion')->willReturnCallback(
			function (int $id, int $expected): bool {
				if ($this->saved[$id]->getVersion() !== $expected) {
					return false;
				}

				$this->saved[$id]->setVersion($expected + 1);
				return true;
			}
		);

		$dashboards = null;
		if ($columns !== null) {
			$dashboard = new Dashboard();
			$dashboard->setGridColumns($columns);
			$dashboards = $this->createMock(DashboardMapper::class);
			$dashboards->method('find')->willReturn($dashboard);
		}

		return new PlacementService(
			placementMapper: $mapper,
			updaters: new PlacementUpdaters(tileUpdater: new TileUpdater(), placementUpdater: new PlacementUpdater()),
			dashboardMapper: $dashboards
		);
	}

	/**
	 * One saved placement.
	 *
	 * @param int    $id       The id.
	 * @param string $widgetId The widget type.
	 * @param int    $x        Grid x.
	 * @param int    $y        Grid y.
	 * @param int    $w        Grid width.
	 * @param int    $h        Grid height.
	 * @param int    $version  The version.
	 *
	 * @return WidgetPlacement
	 */
	private function placement(int $id, string $widgetId, int $x, int $y, int $w, int $h, int $version): WidgetPlacement {
		$placement = new WidgetPlacement();
		$placement->setId($id);
		$placement->setDashboardId(7);
		$placement->setWidgetId($widgetId);
		$placement->setGridX($x);
		$placement->setGridY($y);
		$placement->setGridWidth($w);
		$placement->setGridHeight($h);
		$placement->setVersion($version);
		return $placement;
	}
}
