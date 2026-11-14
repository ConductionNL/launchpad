<?php

/**
 * WidgetPlacementMapperCoverageTest
 *
 * Pins the queries the {@see WidgetPlacementMapper} finders, deletes,
 * position updates and orphan scans build, and the entities or counts they
 * return, over a recording connection (no database).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Db
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Db;

use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\TestCase;

/**
 * Query and outcome tests for the placement mapper.
 *
 * @covers \OCA\LaunchPad\Db\WidgetPlacementMapper
 * @uses   \OCA\LaunchPad\Db\WidgetPlacement
 * @uses   \OCA\LaunchPad\Db\ColumnMatchQueries
 * @uses   \OCA\LaunchPad\Db\OrphanRowQueries
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class WidgetPlacementMapperCoverageTest extends TestCase {

	private const TABLE = '"launchpad_widget_placements"';

	/**
	 * The recording connection.
	 *
	 * @var QueryRecorder
	 */
	private QueryRecorder $recorder;

	/**
	 * Build a fresh recorder.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->recorder = new QueryRecorder($this);
	}//end setUp()

	/**
	 * A mapper over the recording connection, whose queries return the rows.
	 *
	 * @param array<int, array<string, mixed>> $rows The rows every query returns.
	 *
	 * @return WidgetPlacementMapper The mapper.
	 */
	private function mapper(array $rows = []): WidgetPlacementMapper {
		$this->recorder->rows = $rows;

		return new WidgetPlacementMapper($this->recorder->connection());
	}//end mapper()

	/**
	 * The select-where prefix for one equality condition.
	 *
	 * @param string $column The column.
	 * @param string $value  The JSON-printed value.
	 * @param int    $type   The parameter type.
	 *
	 * @return array<int, string> The recorded calls.
	 */
	private function selectWhere(string $column, string $value, int $type): array {
		return [
			'qb1 new',
			'qb1.select("*")',
			'qb1.from(' . self::TABLE . ', null)',
			'qb1.expr()',
			'qb1.createNamedParameter(' . $value . ', ' . $type . ', null)',
			'qb1.expr.eq("' . $column . '", ":p1", null)',
			'qb1.where("eq(\"' . $column . '\", \":p1\", null)")',
		];
	}//end selectWhere()

	/**
	 * find() selects by integer id and hydrates the row, honouring the
	 * shortened acknowledgement column alias.
	 *
	 * @return void
	 */
	public function testFindSelectsByIdAndHydratesTheRow(): void {
		$found = $this->mapper(rows: [['id' => '7', 'widget_id' => 'launchpad_news', 'ack_content_version' => '3']])->find(7);

		$this->assertSame(
			array_merge($this->selectWhere('id', '7', 1), ['qb1.executeQuery(null)']),
			$this->recorder->calls
		);
		$this->assertInstanceOf(WidgetPlacement::class, $found);
		$this->assertSame(7, $found->getId());
		$this->assertSame('launchpad_news', $found->getWidgetId());
		$this->assertSame(3, $found->getAcknowledgementContentVersion());
	}//end testFindSelectsByIdAndHydratesTheRow()

	/**
	 * find() throws when no row matches.
	 *
	 * @return void
	 */
	public function testFindThrowsWhenNoRowMatches(): void {
		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Did expect one result but found none');

		$this->mapper()->find(404);
	}//end testFindThrowsWhenNoRowMatches()

	/**
	 * findByDashboardId() filters by dashboard and orders by sort, row, column.
	 *
	 * @return void
	 */
	public function testFindByDashboardIdOrdersBySortOrderThenGrid(): void {
		$rows = $this->mapper(rows: [['id' => '1', 'dashboard_id' => '3'], ['id' => '2', 'dashboard_id' => '3']])->findByDashboardId(3);

		$this->assertSame(
			array_merge(
				$this->selectWhere('dashboard_id', '3', 1),
				[
					'qb1.orderBy("sort_order", "ASC")',
					'qb1.addOrderBy("grid_y", "ASC")',
					'qb1.addOrderBy("grid_x", "ASC")',
					'qb1.executeQuery(null)',
				]
			),
			$this->recorder->calls
		);
		$this->assertSame([1, 2], array_map(fn (WidgetPlacement $p): int => $p->getId(), $rows));
		$this->assertSame(3, $rows[1]->getDashboardId());
	}//end testFindByDashboardIdOrdersBySortOrderThenGrid()

	/**
	 * findByWidgetId() filters by the string widget id.
	 *
	 * @return void
	 */
	public function testFindByWidgetIdFiltersByStringParameter(): void {
		$rows = $this->mapper(rows: [['id' => '4', 'widget_id' => 'launchpad_news']])->findByWidgetId('launchpad_news');

		$this->assertSame(
			array_merge($this->selectWhere('widget_id', '"launchpad_news"', 2), ['qb1.executeQuery(null)']),
			$this->recorder->calls
		);
		$this->assertCount(1, $rows);
		$this->assertSame('launchpad_news', $rows[0]->getWidgetId());
	}//end testFindByWidgetIdFiltersByStringParameter()

	/**
	 * findByWidgetId() returns an empty list when nothing matches.
	 *
	 * @return void
	 */
	public function testFindByWidgetIdReturnsEmptyListWithoutRows(): void {
		$this->assertSame([], $this->mapper()->findByWidgetId('none'));
	}//end testFindByWidgetIdReturnsEmptyListWithoutRows()

	/**
	 * findAll() selects the whole table ordered by id, with no filter.
	 *
	 * @return void
	 */
	public function testFindAllSelectsEveryRowOrderedById(): void {
		$rows = $this->mapper(rows: [['id' => '1'], ['id' => '2'], ['id' => '3']])->findAll();

		$this->assertSame(
			[
				'qb1 new',
				'qb1.select("*")',
				'qb1.from(' . self::TABLE . ', null)',
				'qb1.orderBy("id", "ASC")',
				'qb1.executeQuery(null)',
			],
			$this->recorder->calls
		);
		$this->assertSame([1, 2, 3], array_map(fn (WidgetPlacement $p): int => $p->getId(), $rows));
	}//end testFindAllSelectsEveryRowOrderedById()

	/**
	 * findByAnnouncementKey() filters by the announcement key column.
	 *
	 * @return void
	 */
	public function testFindByAnnouncementKeyFiltersByKey(): void {
		$rows = $this->mapper(rows: [['id' => '8', 'announcement_key' => 'ann-1'], ['id' => '9', 'announcement_key' => 'ann-1']])
			->findByAnnouncementKey('ann-1');

		$this->assertSame(
			array_merge($this->selectWhere('announcement_key', '"ann-1"', 2), ['qb1.executeQuery(null)']),
			$this->recorder->calls
		);
		$this->assertSame(['ann-1', 'ann-1'], array_map(fn (WidgetPlacement $p): ?string => $p->getAnnouncementKey(), $rows));
	}//end testFindByAnnouncementKeyFiltersByKey()

	/**
	 * deleteByDashboardId() deletes by integer dashboard id.
	 *
	 * @return void
	 */
	public function testDeleteByDashboardIdDeletesByDashboard(): void {
		$this->mapper()->deleteByDashboardId(12);

		$this->assertSame(
			[
				'qb1 new',
				'qb1.delete(' . self::TABLE . ', null)',
				'qb1.expr()',
				'qb1.createNamedParameter(12, 1, null)',
				'qb1.expr.eq("dashboard_id", ":p1", null)',
				'qb1.where("eq(\"dashboard_id\", \":p1\", null)")',
				'qb1.executeStatement(null)',
			],
			$this->recorder->calls
		);
	}//end testDeleteByDashboardIdDeletesByDashboard()

	/**
	 * deleteByDashboardUuid() resolves the UUID with a bound sub-select and
	 * returns the affected-row count.
	 *
	 * @return void
	 */
	public function testDeleteByDashboardUuidUsesBoundSubSelect(): void {
		$deleted = $this->mapper()->deleteByDashboardUuid('uuid-1');

		$sub = '(SELECT id FROM `*PREFIX*launchpad_dashboards` WHERE `uuid` = :p1)';
		$this->assertSame(
			[
				'qb1 new',
				'qb1.delete(' . self::TABLE . ', null)',
				'qb1.expr()',
				'qb1.createNamedParameter("uuid-1", 2, null)',
				'qb1.createFunction("' . $sub . '")',
				'qb1.expr.in("dashboard_id", ' . $sub . ', null)',
				'qb1.where("in(\"dashboard_id\", ' . $sub . ', null)")',
				'qb1.executeStatement(null)',
			],
			$this->recorder->calls
		);
		$this->assertSame(1, $deleted);
	}//end testDeleteByDashboardUuidUsesBoundSubSelect()

	/**
	 * updatePositions() issues one update per entry, binding the given grid
	 * values as integers, defaulting missing ones and stamping updated_at.
	 *
	 * @return void
	 */
	public function testUpdatePositionsWritesEachEntryWithDefaults(): void {
		$this->mapper()->updatePositions(
			[
				['id' => 9, 'gridX' => 1, 'gridY' => 2, 'gridWidth' => 3, 'gridHeight' => 5],
				['id' => 10],
			]
		);

		$expected = [];
		$param = 0;
		foreach ([[1, 9, [1, 2, 3, 5]], [2, 10, [0, 0, 4, 4]]] as [$n, $id, $values]) {
			$tag = 'qb' . $n;
			$expected[] = $tag . ' new';
			$expected[] = $tag . '.update(' . self::TABLE . ', null)';
			foreach (['grid_x', 'grid_y', 'grid_width', 'grid_height'] as $i => $column) {
				$expected[] = $tag . '.createNamedParameter(' . $values[$i] . ', 1, null)';
				$expected[] = $tag . '.set("' . $column . '", ":p' . (++$param) . '")';
			}

			// Every position write raises the widget's version (building-edit-a-dashboard-together, task 1.2).
			$expected[] = $tag . '.getColumnName("version", "")';
			$expected[] = $tag . '.createFunction("version + 1")';
			$expected[] = $tag . '.set("version", version + 1)';
			$expected[] = $tag . '.createNamedParameter(DateTime, "datetime", null)';
			$expected[] = $tag . '.set("updated_at", ":p' . (++$param) . '")';
			$expected[] = $tag . '.expr()';
			$expected[] = $tag . '.createNamedParameter(' . $id . ', 1, null)';
			$expected[] = $tag . '.expr.eq("id", ":p' . (++$param) . '", null)';
			$expected[] = $tag . '.where("eq(\"id\", \":p' . $param . '\", null)")';
			$expected[] = $tag . '.executeStatement(null)';
		}

		$this->assertSame($expected, $this->recorder->calls);
	}//end testUpdatePositionsWritesEachEntryWithDefaults()

	/**
	 * updatePositions() with an empty list touches the database not at all.
	 *
	 * @return void
	 */
	public function testUpdatePositionsWithEmptyListBuildsNoQuery(): void {
		$this->mapper()->updatePositions([]);

		$this->assertSame([], $this->recorder->calls);
	}//end testUpdatePositionsWithEmptyListBuildsNoQuery()

	/**
	 * The orphan scan prefix shared by count and delete.
	 *
	 * @param string $select The recorded select call.
	 *
	 * @return array<int, string> The recorded calls.
	 */
	private function orphanScan(string $select): array {
		return [
			$select,
			'qb1.from(' . self::TABLE . ', "p")',
			'qb1.leftJoin("p", "launchpad_dashboards", "d", "d.id = p.dashboard_id")',
			'qb1.expr()',
			'qb1.expr.isNull("d.id")',
			'qb1.where("isNull(\"d.id\")")',
			'qb1.executeQuery(null)',
		];
	}//end orphanScan()

	/**
	 * countOrphaned() left-joins the dashboards and counts rows without a parent.
	 *
	 * @return void
	 */
	public function testCountOrphanedCountsRowsWithoutDashboard(): void {
		$count = $this->mapper(rows: [['cnt' => '4']])->countOrphaned();

		$this->assertSame(
			array_merge(
				['qb1 new', 'qb1.func()', 'qb1.func.count("p.id", "")'],
				$this->orphanScan('qb1.select(count("p.id", ""))')
			),
			$this->recorder->calls
		);
		$this->assertSame(4, $count);
	}//end testCountOrphanedCountsRowsWithoutDashboard()

	/**
	 * deleteOrphaned() resolves orphan ids, then deletes them by primary key.
	 *
	 * @return void
	 */
	public function testDeleteOrphanedDeletesResolvedIds(): void {
		$deleted = $this->mapper(rows: [['id' => '5'], ['id' => '6']])->deleteOrphaned();

		$this->assertSame(
			array_merge(
				['qb1 new'],
				$this->orphanScan('qb1.select("p.id")'),
				[
					'qb2 new',
					'qb2.delete(' . self::TABLE . ', null)',
					'qb2.expr()',
					'qb2.createNamedParameter([5,6], 101, null)',
					'qb2.expr.in("id", ":p1", null)',
					'qb2.where("in(\"id\", \":p1\", null)")',
					'qb2.executeStatement(null)',
				]
			),
			$this->recorder->calls
		);
		$this->assertSame(1, $deleted);
	}//end testDeleteOrphanedDeletesResolvedIds()

	/**
	 * deleteOrphaned() returns 0 and issues no delete when nothing is orphaned.
	 *
	 * @return void
	 */
	public function testDeleteOrphanedWithoutOrphansSkipsDelete(): void {
		$deleted = $this->mapper()->deleteOrphaned();

		$this->assertSame(array_merge(['qb1 new'], $this->orphanScan('qb1.select("p.id")')), $this->recorder->calls);
		$this->assertSame(0, $deleted);
	}//end testDeleteOrphanedWithoutOrphansSkipsDelete()
}//end class
