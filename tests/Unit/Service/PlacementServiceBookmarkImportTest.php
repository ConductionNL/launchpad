<?php

/**
 * PlacementServiceBookmarkImportTest
 *
 * A bookmark import checks the quota once for the whole import, creates every
 * placement in one transaction below the dashboard, and leaves nothing behind
 * when a write fails.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Exception\QuotaExceededException;
use OCA\LaunchPad\Service\BookmarkImportPlan;
use OCA\LaunchPad\Service\PlacementService;
use OCA\LaunchPad\Service\PlacementUpdater;
use OCA\LaunchPad\Service\PlacementUpdaters;
use OCA\LaunchPad\Service\QuotaService;
use OCA\LaunchPad\Service\TileUpdater;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \OCA\LaunchPad\Service\PlacementService
 * @covers \OCA\LaunchPad\Service\QuotaService
 * @uses   \OCA\LaunchPad\Service\BookmarkImportPlan
 * @uses   \OCA\LaunchPad\Service\PlacementUpdaters
 * @uses   \OCA\LaunchPad\Service\TileUpdater
 * @uses   \OCA\LaunchPad\Exception\QuotaExceededException
 */
class PlacementServiceBookmarkImportTest extends TestCase {
	/** @var WidgetPlacementMapper&MockObject */
	private $placementMapper;

	/** @var AdminSettingMapper&MockObject */
	private $settingMapper;

	/** @var IDBConnection&MockObject */
	private $db;

	private PlacementService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->placementMapper = $this->createMock(WidgetPlacementMapper::class);
		$this->settingMapper = $this->createMock(AdminSettingMapper::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->service = new PlacementService(
			placementMapper: $this->placementMapper,
			updaters: new PlacementUpdaters(tileUpdater: new TileUpdater(), placementUpdater: $this->createMock(PlacementUpdater::class)),
			publicShareContext: null,
			quotaService: new QuotaService(
				settingMapper: $this->settingMapper,
				dashboardMapper: $this->createMock(DashboardMapper::class),
				placementMapper: $this->placementMapper,
			),
			db: $this->db,
		);
	}//end setUp()

	/**
	 * Wire the widget quota.
	 *
	 * @param int $limit The per-dashboard widget limit.
	 *
	 * @return void
	 */
	private function withWidgetLimit(int $limit): void {
		$this->settingMapper->method('getValue')->willReturnCallback(
			static fn (string $key, $default = null) => $key === AdminSetting::KEY_MAX_WIDGETS_PER_DASHBOARD ? $limit : $default
		);
	}//end withWidgetLimit()

	/**
	 * An existing placement on the dashboard.
	 *
	 * @param int $y      Its row.
	 * @param int $height Its height.
	 *
	 * @return WidgetPlacement
	 */
	private static function existing(int $y, int $height): WidgetPlacement {
		$placement = new WidgetPlacement();
		$placement->setGridY($y);
		$placement->setGridHeight($height);
		return $placement;
	}//end existing()

	/**
	 * The plan of one folder of three and two loose bookmarks.
	 *
	 * @return BookmarkImportPlan
	 */
	private static function plan(): BookmarkImportPlan {
		return BookmarkImportPlan::fromRequest(
			folders: [['name' => 'Werk', 'bookmarks' => [
				['title' => 'Wiki', 'url' => 'https://wiki.example'],
				['title' => 'Mail', 'url' => 'https://mail.example'],
				['title' => 'Plan', 'url' => 'http://plan.example'],
			]]],
			bookmarks: [['title' => 'News', 'url' => 'https://news.example'], ['title' => 'Docs', 'url' => 'https://docs.example']]
		);
	}//end plan()

	/**
	 * The import creates a container and two tiles below the lowest edge, in
	 * one committed transaction (REQ-BMI-001).
	 *
	 * @return void
	 */
	public function testTheImportIsCreatedBelowTheDashboardInOneTransaction(): void {
		$this->withWidgetLimit(20);
		$this->placementMapper->method('countByDashboardId')->willReturn(18 - 3);
		$this->placementMapper->method('findByDashboardId')->with(7)->willReturn([self::existing(0, 4), self::existing(4, 6)]);
		$inserted = [];
		$this->placementMapper->expects(self::exactly(3))->method('insert')->willReturnCallback(
			static function (WidgetPlacement $placement) use (&$inserted): WidgetPlacement {
				$inserted[] = $placement;
				return $placement;
			}
		);
		$this->db->expects(self::once())->method('beginTransaction');
		$this->db->expects(self::once())->method('commit');
		$this->db->expects(self::never())->method('rollBack');

		$created = $this->service->importBookmarks(dashboardId: 7, plan: self::plan());

		self::assertSame($inserted, $created);
		self::assertSame('container', $created[0]->getWidgetId());
		self::assertSame(10, $created[0]->getGridY());
		self::assertSame(7, $created[0]->getDashboardId());
		self::assertSame('Werk', $created[0]->getContentArray()['title']);
		self::assertCount(3, $created[0]->getContentArray()['placements']);
		self::assertStringStartsWith('tile-', $created[1]->getWidgetId());
		self::assertSame('News', $created[1]->getTileTitle());
		self::assertSame('url', $created[1]->getTileLinkType());
		self::assertSame('https://news.example', $created[1]->getTileLinkValue());
		self::assertSame([0, 14], [$created[1]->getGridX(), $created[1]->getGridY()]);
		self::assertSame([2, 14], [$created[2]->getGridX(), $created[2]->getGridY()]);
	}//end testTheImportIsCreatedBelowTheDashboardInOneTransaction()

	/**
	 * With room for 2 and an import of 3 placements, nothing is created and
	 * no transaction starts (REQ-BMI-003).
	 *
	 * @return void
	 */
	public function testAnImportLargerThanTheRoomLeftCreatesNothing(): void {
		$this->withWidgetLimit(20);
		$this->placementMapper->method('countByDashboardId')->willReturn(18);
		$this->placementMapper->expects(self::never())->method('insert');
		$this->db->expects(self::never())->method('beginTransaction');

		try {
			$this->service->importBookmarks(dashboardId: 7, plan: self::plan());
			self::fail('The import was not refused.');
		} catch (QuotaExceededException $e) {
			self::assertSame(['quota' => 'widgets', 'limit' => 20, 'current' => 18], array_intersect_key($e->toResponseBody(), ['quota' => 1, 'limit' => 1, 'current' => 1]));
		}
	}//end testAnImportLargerThanTheRoomLeftCreatesNothing()

	/**
	 * A write failing halfway rolls the transaction back and rethrows.
	 *
	 * @return void
	 */
	public function testAFailingWriteRollsTheImportBack(): void {
		$this->withWidgetLimit(0);
		$this->placementMapper->method('findByDashboardId')->willReturn([]);
		$calls = 0;
		$this->placementMapper->method('insert')->willReturnCallback(
			static function (WidgetPlacement $placement) use (&$calls): WidgetPlacement {
				$calls++;
				if ($calls === 2) {
					throw new RuntimeException('disk full');
				}

				return $placement;
			}
		);
		$this->db->expects(self::once())->method('beginTransaction');
		$this->db->expects(self::never())->method('commit');
		$this->db->expects(self::once())->method('rollBack');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('disk full');
		$this->service->importBookmarks(dashboardId: 7, plan: self::plan());
	}//end testAFailingWriteRollsTheImportBack()
}//end class
