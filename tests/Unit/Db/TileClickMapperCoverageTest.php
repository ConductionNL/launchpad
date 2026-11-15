<?php

/**
 * Tile click mapper coverage test
 *
 * Pins the queries TileClickMapper builds for its lookups, aggregate
 * reports, range export and deletes, and the rows or entities each one
 * returns, over a recording connection.
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

use OCA\LaunchPad\Db\TileClick;
use OCA\LaunchPad\Db\TileClickMapper;
use PHPUnit\Framework\TestCase;

/**
 * The queries and results of the tile click mapper.
 *
 * @covers \OCA\LaunchPad\Db\TileClickMapper
 * @uses   \OCA\LaunchPad\Db\TileClick
 * @uses   \OCA\LaunchPad\Db\ColumnMatchQueries
 */
class TileClickMapperCoverageTest extends TestCase {

	/**
	 * A stored aggregate row as the database returns it.
	 *
	 * @param int    $id        The row id.
	 * @param string $placement The placement UUID.
	 * @param string $bucket    The click bucket.
	 *
	 * @return array<string, mixed>
	 */
	private static function storedRow(int $id, string $placement, string $bucket): array {
		return [
			'id'                 => $id,
			'placement_uuid'     => $placement,
			'dashboard_uuid'     => 'd-1',
			'click_bucket'       => $bucket,
			'click_count'        => '4',
			'unique_actor_count' => '2',
		];
	}//end storedRow()

	/**
	 * The two-condition range filter shared by the range queries.
	 *
	 * @param string $first The placeholder of the lower bound.
	 * @param string $second The placeholder of the upper bound.
	 * @param string $firstClause The clause that adds the lower bound.
	 *
	 * @return array<int, string>
	 */
	private static function rangeCalls(string $first, string $second, string $firstClause): array {
		return [
			'qb1.expr()',
			'qb1.createNamedParameter("2026-11-01", 2, null)',
			'qb1.expr.gte("click_bucket", "' . $first . '", null)',
			'qb1.' . $firstClause . '("gte(\"click_bucket\", \"' . $first . '\", null)")',
			'qb1.expr()',
			'qb1.createNamedParameter("2026-11-07", 2, null)',
			'qb1.expr.lte("click_bucket", "' . $second . '", null)',
			'qb1.andWhere("lte(\"click_bucket\", \"' . $second . '\", null)")',
		];
	}//end rangeCalls()

	/**
	 * The mapper targets the tile click table.
	 *
	 * @return void
	 */
	public function testMapperUsesTheTileClickTable(): void {
		$mapper = new TileClickMapper((new QueryRecorder($this))->connection());

		$this->assertSame('launchpad_tile_clicks', $mapper->getTableName());
	}//end testMapperUsesTheTileClickTable()

	/**
	 * The lookup filters on placement and bucket, limits to one row and
	 * maps the row it finds.
	 *
	 * @return void
	 */
	public function testFindByPlacementAndBucketReturnsTheRow(): void {
		$rec       = new QueryRecorder($this);
		$rec->rows = [self::storedRow(3, 'p-1', '2026-11-01')];
		$mapper    = new TileClickMapper($rec->connection());

		$row = $mapper->findByPlacementAndBucket('p-1', '2026-11-01');

		$this->assertInstanceOf(TileClick::class, $row);
		$this->assertSame(
			[
				'id'               => 3,
				'placementUuid'    => 'p-1',
				'dashboardUuid'    => 'd-1',
				'clickBucket'      => '2026-11-01',
				'clickCount'       => 4,
				'uniqueActorCount' => 2,
			],
			$row->jsonSerialize()
		);
		$this->assertSame(
			[
				'qb1 new',
				'qb1.select("*")',
				'qb1.from("launchpad_tile_clicks", null)',
				'qb1.expr()',
				'qb1.createNamedParameter("p-1", 2, null)',
				'qb1.expr.eq("placement_uuid", ":p1", null)',
				'qb1.where("eq(\"placement_uuid\", \":p1\", null)")',
				'qb1.expr()',
				'qb1.createNamedParameter("2026-11-01", 2, null)',
				'qb1.expr.eq("click_bucket", ":p2", null)',
				'qb1.andWhere("eq(\"click_bucket\", \":p2\", null)")',
				'qb1.setMaxResults(1)',
				'qb1.executeQuery(null)',
			],
			$rec->calls
		);
	}//end testFindByPlacementAndBucketReturnsTheRow()

	/**
	 * A bucket with no row yields null instead of an exception.
	 *
	 * @return void
	 */
	public function testFindByPlacementAndBucketReturnsNullWhenAbsent(): void {
		$rec    = new QueryRecorder($this);
		$mapper = new TileClickMapper($rec->connection());

		$this->assertNull($mapper->findByPlacementAndBucket('p-9', '2026-11-02'));
		$this->assertContains('qb1.setMaxResults(1)', $rec->calls);
		$this->assertContains('qb1.executeQuery(null)', $rec->calls);
	}//end testFindByPlacementAndBucketReturnsNullWhenAbsent()

	/**
	 * The top-tiles report sums per placement within the range, orders by
	 * the click sum, limits, and casts every aggregate row.
	 *
	 * @return void
	 */
	public function testFindTopTilesInRangeBuildsTheAggregateAndMapsRows(): void {
		$rec       = new QueryRecorder($this);
		$rec->rows = [
			['placement_uuid' => 'p-1', 'dashboard_uuid' => 'd-1', 'click_count_sum' => '9', 'unique_actor_sum' => '3'],
			['placement_uuid' => 'p-2', 'dashboard_uuid' => 'd-2', 'click_count_sum' => 4, 'unique_actor_sum' => null],
		];
		$mapper    = new TileClickMapper($rec->connection());

		$rows = $mapper->findTopTilesInRange('2026-11-01', '2026-11-07', 5);

		$this->assertSame(
			[
				['placementUuid' => 'p-1', 'dashboardUuid' => 'd-1', 'clickCount' => 9, 'uniqueActorCount' => 3],
				['placementUuid' => 'p-2', 'dashboardUuid' => 'd-2', 'clickCount' => 4, 'uniqueActorCount' => 0],
			],
			$rows
		);
		$this->assertSame(
			array_merge(
				[
					'qb1 new',
					'qb1.select("placement_uuid")',
					'qb1.func()',
					'qb1.func.max("dashboard_uuid")',
					'qb1.selectAlias(max("dashboard_uuid"), "dashboard_uuid")',
					'qb1.func()',
					'qb1.func.sum("click_count")',
					'qb1.selectAlias(sum("click_count"), "click_count_sum")',
					'qb1.func()',
					'qb1.func.sum("unique_actor_count")',
					'qb1.selectAlias(sum("unique_actor_count"), "unique_actor_sum")',
					'qb1.from("launchpad_tile_clicks", null)',
				],
				self::rangeCalls(':p1', ':p2', 'where'),
				[
					'qb1.groupBy("placement_uuid")',
					'qb1.orderBy("click_count_sum", "DESC")',
					'qb1.setMaxResults(5)',
					'qb1.executeQuery(null)',
				]
			),
			$rec->calls
		);
	}//end testFindTopTilesInRangeBuildsTheAggregateAndMapsRows()

	/**
	 * An empty range yields no aggregate rows.
	 *
	 * @return void
	 */
	public function testFindTopTilesInRangeReturnsEmptyWithoutRows(): void {
		$mapper = new TileClickMapper((new QueryRecorder($this))->connection());

		$this->assertSame([], $mapper->findTopTilesInRange('2026-11-01', '2026-11-07', 10));
	}//end testFindTopTilesInRangeReturnsEmptyWithoutRows()

	/**
	 * The per-dashboard breakdown filters on the dashboard and the range,
	 * groups per placement and maps every row without a dashboard key.
	 *
	 * @return void
	 */
	public function testFindByDashboardInRangeBuildsTheBreakdownAndMapsRows(): void {
		$rec       = new QueryRecorder($this);
		$rec->rows = [
			['placement_uuid' => 'p-1', 'click_count_sum' => '7', 'unique_actor_sum' => '5'],
			['placement_uuid' => 'p-3', 'click_count_sum' => '1', 'unique_actor_sum' => '1'],
		];
		$mapper    = new TileClickMapper($rec->connection());

		$rows = $mapper->findByDashboardInRange('d-1', '2026-11-01', '2026-11-07');

		$this->assertSame(
			[
				['placementUuid' => 'p-1', 'clickCount' => 7, 'uniqueActorCount' => 5],
				['placementUuid' => 'p-3', 'clickCount' => 1, 'uniqueActorCount' => 1],
			],
			$rows
		);
		$this->assertSame(
			array_merge(
				[
					'qb1 new',
					'qb1.select("placement_uuid")',
					'qb1.func()',
					'qb1.func.sum("click_count")',
					'qb1.selectAlias(sum("click_count"), "click_count_sum")',
					'qb1.func()',
					'qb1.func.sum("unique_actor_count")',
					'qb1.selectAlias(sum("unique_actor_count"), "unique_actor_sum")',
					'qb1.from("launchpad_tile_clicks", null)',
					'qb1.expr()',
					'qb1.createNamedParameter("d-1", 2, null)',
					'qb1.expr.eq("dashboard_uuid", ":p1", null)',
					'qb1.where("eq(\"dashboard_uuid\", \":p1\", null)")',
				],
				self::rangeCalls(':p2', ':p3', 'andWhere'),
				[
					'qb1.groupBy("placement_uuid")',
					'qb1.orderBy("click_count_sum", "DESC")',
					'qb1.executeQuery(null)',
				]
			),
			$rec->calls
		);
	}//end testFindByDashboardInRangeBuildsTheBreakdownAndMapsRows()

	/**
	 * The export scan selects every column in the range, ordered by
	 * placement then bucket, and maps each row to an entity.
	 *
	 * @return void
	 */
	public function testFindAllInRangeReturnsOrderedEntities(): void {
		$rec       = new QueryRecorder($this);
		$rec->rows = [
			self::storedRow(1, 'p-1', '2026-11-01'),
			self::storedRow(2, 'p-1', '2026-11-02'),
		];
		$mapper    = new TileClickMapper($rec->connection());

		$rows = $mapper->findAllInRange('2026-11-01', '2026-11-07');

		$this->assertCount(2, $rows);
		$this->assertContainsOnlyInstancesOf(TileClick::class, $rows);
		$this->assertSame([1, 2], [$rows[0]->getId(), $rows[1]->getId()]);
		$this->assertSame('2026-11-02', $rows[1]->getClickBucket());
		$this->assertSame(4, $rows[1]->getClickCount());
		$this->assertSame(
			array_merge(
				[
					'qb1 new',
					'qb1.select("*")',
					'qb1.from("launchpad_tile_clicks", null)',
				],
				self::rangeCalls(':p1', ':p2', 'where'),
				[
					'qb1.orderBy("placement_uuid", "ASC")',
					'qb1.addOrderBy("click_bucket", "ASC")',
					'qb1.executeQuery(null)',
				]
			),
			$rec->calls
		);
	}//end testFindAllInRangeReturnsOrderedEntities()

	/**
	 * The retention purge deletes rows strictly older than the cutoff and
	 * returns the affected-row count.
	 *
	 * @return void
	 */
	public function testDeleteOlderThanDeletesBeforeTheCutoff(): void {
		$rec    = new QueryRecorder($this);
		$mapper = new TileClickMapper($rec->connection());

		$this->assertSame(1, $mapper->deleteOlderThan('2026-10-01'));
		$this->assertSame(
			[
				'qb1 new',
				'qb1.delete("launchpad_tile_clicks", null)',
				'qb1.expr()',
				'qb1.createNamedParameter("2026-10-01", 2, null)',
				'qb1.expr.lt("click_bucket", ":p1", null)',
				'qb1.where("lt(\"click_bucket\", \":p1\", null)")',
				'qb1.executeStatement(null)',
			],
			$rec->calls
		);
	}//end testDeleteOlderThanDeletesBeforeTheCutoff()

	/**
	 * Deleting a placement removes its rows only and returns the count.
	 *
	 * @return void
	 */
	public function testDeleteByPlacementDeletesThatPlacement(): void {
		$rec    = new QueryRecorder($this);
		$mapper = new TileClickMapper($rec->connection());

		$this->assertSame(1, $mapper->deleteByPlacement('p-7'));
		$this->assertSame(
			[
				'qb1 new',
				'qb1.delete("launchpad_tile_clicks", null)',
				'qb1.expr()',
				'qb1.createNamedParameter("p-7", 2, null)',
				'qb1.expr.eq("placement_uuid", ":p1", null)',
				'qb1.where("eq(\"placement_uuid\", \":p1\", null)")',
				'qb1.executeStatement(null)',
			],
			$rec->calls
		);
	}//end testDeleteByPlacementDeletesThatPlacement()
}//end class
