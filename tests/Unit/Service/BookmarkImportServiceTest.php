<?php

/**
 * BookmarkImportServiceTest
 *
 * Bookmark import (launcher-bookmark-import, REQ-BMI-001..003) through the
 * real PlacementService and QuotaService: a chosen folder becomes a container
 * of link tiles at the bottom, loose bookmarks become tiles, non-web
 * addresses are skipped with a reason, the quota is checked once for the
 * whole import, and a failure part-way rolls everything back.
 *
 * @category  Test
 * @package   Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Exception\QuotaExceededException;
use OCA\LaunchPad\Service\BookmarkImportService;
use OCA\LaunchPad\Service\PlacementService;
use OCA\LaunchPad\Service\PlacementUpdater;
use OCA\LaunchPad\Service\QuotaService;
use OCA\LaunchPad\Service\TileUpdater;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BookmarkImportServiceTest extends TestCase {
	/**
	 * Inserted placements.
	 *
	 * @var WidgetPlacement[]
	 */
	private array $inserted = [];

	private int $limit = 0;

	private int $existing = 0;

	private $db;

	private ?int $failOnInsert = null;

	private function service(): BookmarkImportService {
		$mapper = $this->createMock(WidgetPlacementMapper::class);
		$mapper->method('insert')->willReturnCallback(
			function (WidgetPlacement $placement): WidgetPlacement {
				if ($this->failOnInsert !== null && count($this->inserted) === $this->failOnInsert) {
					throw new RuntimeException('database went away');
				}

				$placement->setId(100 + count($this->inserted));
				$this->inserted[] = $placement;
				return $placement;
			}
		);
		$mapper->method('countByDashboardId')->willReturnCallback(fn (): int => $this->existing);
		$bottom = new WidgetPlacement();
		$bottom->setGridY(6);
		$bottom->setGridHeight(4);
		$mapper->method('findByDashboardId')->willReturn([$bottom]);

		$settings = $this->createMock(AdminSettingMapper::class);
		$settings->method('getValue')->willReturnCallback(
			fn (string $key, mixed $default = null): mixed => $key === 'max_widgets_per_dashboard' ? $this->limit : $default
		);

		$quota = new QuotaService(
			settingMapper: $settings,
			dashboardMapper: $this->createMock(DashboardMapper::class),
			placementMapper: $mapper,
		);
		$placements = new PlacementService(
			placementMapper: $mapper,
			tileUpdater: new TileUpdater(),
			placementUpdater: $this->createMock(PlacementUpdater::class),
			quotaService: $quota,
		);

		$this->db = $this->createMock(IDBConnection::class);

		return new BookmarkImportService(placements: $placements, placementMapper: $mapper, quota: $quota, db: $this->db);
	}//end service()

	private static function bookmarks(string $prefix, int $count): array {
		$list = [];
		for ($i = 1; $i <= $count; $i++) {
			$list[] = ['title' => $prefix . ' ' . $i, 'url' => 'https://example.nl/' . strtolower($prefix) . '/' . $i];
		}

		return $list;
	}//end bookmarks()

	/**
	 * REQ-BMI-001 scenario "Import one folder".
	 *
	 * @return void
	 */
	public function testAFolderBecomesAContainerOfLinkTilesAtTheBottom(): void {
		$service = $this->service();
		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->db->expects($this->never())->method('rollBack');

		$result = $service->import(dashboardId: 7, folders: [['name' => 'Werk', 'bookmarks' => self::bookmarks('Werk', 12)]], bookmarks: []);

		$this->assertCount(1, $this->inserted);
		$container = $this->inserted[0];
		$this->assertSame('container', $container->getWidgetId());
		$this->assertSame(10, $container->getGridY(), 'appended below the lowest widget (6 + 4)');
		$content = $container->getContentArray();
		$this->assertSame('Werk', $content['title']);
		$this->assertCount(12, $content['placements']);
		$first = $content['placements'][0];
		$this->assertSame('link', $first['type']);
		$this->assertSame(['label' => 'Werk 1', 'url' => 'https://example.nl/werk/1', 'actionType' => 'external'], array_intersect_key($first['content'], ['label' => 1, 'url' => 1, 'actionType' => 1]));
		$this->assertSame([0, 0, 2, 2], [$first['gridX'], $first['gridY'], $first['gridWidth'], $first['gridHeight']]);
		$this->assertSame([2, 0], [$content['placements'][1]['gridX'], $content['placements'][1]['gridY']]);
		$this->assertSame([0, 2], [$content['placements'][2]['gridX'], $content['placements'][2]['gridY']]);
		$this->assertSame(['containers' => 1, 'tiles' => 12, 'skipped' => []], array_intersect_key($result, ['containers' => 1, 'tiles' => 1, 'skipped' => 1]));
	}//end testAFolderBecomesAContainerOfLinkTilesAtTheBottom()

	public function testLooseBookmarksBecomeTilesInARow(): void {
		$result = $this->service()->import(dashboardId: 7, folders: [], bookmarks: self::bookmarks('Los', 2));

		$this->assertCount(2, $this->inserted);
		$this->assertStringStartsWith('tile-', $this->inserted[0]->getWidgetId());
		$this->assertSame('url', $this->inserted[0]->getTileLinkType());
		$this->assertSame('https://example.nl/los/1', $this->inserted[0]->getTileLinkValue());
		$this->assertSame('Los 1', $this->inserted[0]->getTileTitle());
		$this->assertSame([0, 10], [$this->inserted[0]->getGridX(), $this->inserted[0]->getGridY()]);
		$this->assertSame([2, 10], [$this->inserted[1]->getGridX(), $this->inserted[1]->getGridY()]);
		$this->assertCount(2, $result['placements']);
	}//end testLooseBookmarksBecomeTilesInARow()

	/**
	 * REQ-BMI-002 scenario "A script bookmark is skipped".
	 *
	 * @return void
	 */
	public function testNonWebAddressesAreSkippedWithAReason(): void {
		$bookmarks = self::bookmarks('Werk', 5);
		$bookmarks[] = ['title' => 'Alert', 'url' => 'javascript:alert(1)'];
		$bookmarks[] = ['title' => 'Local', 'url' => 'file:///etc/passwd'];
		$bookmarks[] = ['title' => 'Intranet', 'url' => 'http://intranet.gemeente.local/start'];

		$result = $this->service()->import(dashboardId: 7, folders: [['name' => 'Werk', 'bookmarks' => $bookmarks]], bookmarks: []);

		$this->assertSame(6, $result['tiles'], 'http and https pass, an intranet address included');
		$this->assertSame(
			[
				['title' => 'Alert', 'url' => 'javascript:alert(1)', 'reason' => 'not-a-web-address'],
				['title' => 'Local', 'url' => 'file:///etc/passwd', 'reason' => 'not-a-web-address'],
			],
			$result['skipped']
		);
	}//end testNonWebAddressesAreSkippedWithAReason()

	/**
	 * REQ-BMI-003 scenario "Import too large for the quota".
	 *
	 * @return void
	 */
	public function testTheQuotaIsCheckedOnceForTheWholeImportAndNothingIsCreated(): void {
		$this->limit = 20;
		$this->existing = 18;
		$service = $this->service();
		$this->db->expects($this->never())->method('beginTransaction');

		try {
			$service->import(
				dashboardId: 7,
				folders: [
					['name' => 'A', 'bookmarks' => self::bookmarks('A', 1)],
					['name' => 'B', 'bookmarks' => self::bookmarks('B', 1)],
					['name' => 'C', 'bookmarks' => self::bookmarks('C', 1)],
				],
				bookmarks: []
			);
			$this->fail('the import passed the quota');
		} catch (QuotaExceededException $e) {
			$this->assertSame(['limit' => 20, 'current' => 18], array_intersect_key($e->toResponseBody(), ['limit' => 1, 'current' => 1]));
		}

		$this->assertSame([], $this->inserted);
	}//end testTheQuotaIsCheckedOnceForTheWholeImportAndNothingIsCreated()

	public function testAFailurePartWayRollsBack(): void {
		$service = $this->service();
		$this->failOnInsert = 1;
		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('rollBack');
		$this->db->expects($this->never())->method('commit');

		$this->expectException(RuntimeException::class);
		$service->import(dashboardId: 7, folders: [['name' => 'A', 'bookmarks' => self::bookmarks('A', 1)]], bookmarks: self::bookmarks('Los', 1));
	}//end testAFailurePartWayRollsBack()

	public function testMoreThanTwoThousandBookmarksIsRefused(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->service()->import(dashboardId: 7, folders: [['name' => 'Big', 'bookmarks' => self::bookmarks('B', 2001)]], bookmarks: []);
	}//end testMoreThanTwoThousandBookmarksIsRefused()

	public function testNestedFolderNamesAreNotTrustedAndEmptyFoldersAreDropped(): void {
		$result = $this->service()->import(
			dashboardId: 7,
			folders: [
				['name' => '', 'bookmarks' => self::bookmarks('X', 1)],
				['name' => 'Leeg', 'bookmarks' => [['title' => 'Bad', 'url' => 'ftp://x']]],
			],
			bookmarks: []
		);

		$this->assertSame(1, $result['containers']);
		$this->assertSame('Bookmarks', $this->inserted[0]->getContentArray()['title']);
	}//end testNestedFolderNamesAreNotTrustedAndEmptyFoldersAreDropped()
}//end class
