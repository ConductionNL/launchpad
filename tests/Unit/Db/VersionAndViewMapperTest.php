<?php

/**
 * Unit tests for DashboardVersionMapper and DashboardViewMapper.
 *
 * @category Test
 * @package  OCA\LaunchPad\Tests\Unit\Db
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Db;

use OCA\LaunchPad\Db\DashboardVersion;
use OCA\LaunchPad\Db\DashboardVersionMapper;
use OCA\LaunchPad\Db\DashboardView;
use OCA\LaunchPad\Db\DashboardViewMapper;
use PHPUnit\Framework\TestCase;

/**
 * Version history queries, scalar reads, pruning to the retention, and the
 * view-bucket analytics with their upsert.
 *
 * @covers \OCA\LaunchPad\Db\DashboardVersionMapper
 * @covers \OCA\LaunchPad\Db\DashboardViewMapper
 * @uses   \OCA\LaunchPad\Db\ColumnMatchQueries
 * @uses   \OCA\LaunchPad\Db\DashboardUuidCondition
 * @uses   \OCA\LaunchPad\Db\DashboardVersion
 * @uses   \OCA\LaunchPad\Db\DashboardView
 */
class VersionAndViewMapperTest extends TestCase {

	/**
	 * Versions are listed newest first, optionally limited, and found by number.
	 *
	 * @return void
	 */
	public function testVersionLookups(): void {
		$row = ['id' => 1, 'dashboard_uuid' => 'd1', 'version_number' => 3, 'note' => 'n'];

		$recorder = new QueryRecorder($this);
		$recorder->rows = [$row];
		$all = (new DashboardVersionMapper($recorder->connection()))->findByDashboardUuid(dashboardUuid: 'd1');
		$this->assertInstanceOf(DashboardVersion::class, $all[0]);
		$this->assertSame(3, $all[0]->getVersionNumber());
		$this->assertContains('qb1.orderBy("version_number", "DESC")', $recorder->calls);

		$recorder = new QueryRecorder($this);
		$recorder->rows = [$row];
		(new DashboardVersionMapper($recorder->connection()))->findLatestByDashboard(dashboardUuid: 'd1', limit: 5);
		$this->assertContains('qb1.setMaxResults(5)', $recorder->calls);

		$recorder = new QueryRecorder($this);
		$recorder->rows = [$row];
		$one = (new DashboardVersionMapper($recorder->connection()))->findByDashboardAndVersion(dashboardUuid: 'd1', versionNumber: 3);
		$this->assertSame('n', $one->getNote());
		$this->assertContains('qb1.createNamedParameter(3, 1, null)', $recorder->calls);
	}//end testVersionLookups()

	/**
	 * The max version and count read one scalar, zero without rows.
	 *
	 * @return void
	 */
	public function testScalarReads(): void {
		$recorder = new QueryRecorder($this);
		$recorder->rows = [['m' => '12']];
		$this->assertSame(12, (new DashboardVersionMapper($recorder->connection()))->findMaxVersionNumber(dashboardUuid: 'd1'));
		$this->assertContains('qb1.func.max("version_number")', $recorder->calls);
		$this->assertContains('qb1.expr.eq("dashboard_uuid", ":p1", null)', $recorder->calls);

		$recorder = new QueryRecorder($this);
		$this->assertSame(0, (new DashboardVersionMapper($recorder->connection()))->countByDashboard(dashboardUuid: 'd1'));
		$this->assertContains('qb1.func.count("*", "")', $recorder->calls);
	}//end testScalarReads()

	/**
	 * Pruning deletes versions older than the oldest one kept.
	 *
	 * @return void
	 */
	public function testPruneDeletesBelowTheCutoff(): void {
		$recorder = new QueryRecorder($this);
		$recorder->rows = [['version_number' => 9], ['version_number' => 8], ['version_number' => 7]];

		$deleted = (new DashboardVersionMapper($recorder->connection()))->pruneOldVersions(dashboardUuid: 'd1', keepCount: 3);

		$this->assertSame(1, $deleted);
		$this->assertContains('qb2.setMaxResults(3)', $recorder->calls);
		$this->assertContains('qb3.delete("launchpad_dash_versions", null)', $recorder->calls);
		$this->assertContains('qb3.createNamedParameter(7, 1, null)', $recorder->calls);
		$this->assertContains('qb3.expr.lt("version_number", ":p4", null)', $recorder->calls);
	}//end testPruneDeletesBelowTheCutoff()

	/**
	 * Nothing is pruned within the retention, or when fewer rows than kept come back.
	 *
	 * @return void
	 */
	public function testPruneKeepsWhatIsWithinRetention(): void {
		$recorder = new QueryRecorder($this);
		$recorder->rows = [['version_number' => 2]];
		$this->assertSame(0, (new DashboardVersionMapper($recorder->connection()))->pruneOldVersions(dashboardUuid: 'd1', keepCount: 2));
		$this->assertNotContains('qb2 new', $recorder->calls);

		$recorder = new QueryRecorder($this);
		$recorder->rows = [['version_number' => 9], ['version_number' => 8]];
		$this->assertSame(0, (new DashboardVersionMapper($recorder->connection()))->pruneOldVersions(dashboardUuid: 'd1', keepCount: 3));
		$this->assertNotContains('qb3 new', $recorder->calls);

		$recorder = new QueryRecorder($this);
		$this->assertSame(1, (new DashboardVersionMapper($recorder->connection()))->deleteByDashboardUuid(dashboardUuid: 'd1'));
	}//end testPruneKeepsWhatIsWithinRetention()

	/**
	 * A bucket is found by dashboard and bucket, null when absent; ranges filter on the bucket.
	 *
	 * @return void
	 */
	public function testViewLookups(): void {
		$recorder = new QueryRecorder($this);
		$recorder->rows = [['id' => 5, 'dashboard_uuid' => 'd1', 'view_bucket' => '2026-11-01', 'view_count' => 4, 'unique_viewer_count' => 2]];
		$view = (new DashboardViewMapper($recorder->connection()))->findByDashboardAndBucket(dashboardUuid: 'd1', viewBucket: '2026-11-01');
		$this->assertInstanceOf(DashboardView::class, $view);
		$this->assertSame([4, 2], [$view->getViewCount(), $view->getUniqueViewerCount()]);

		$empty = new DashboardViewMapper((new QueryRecorder($this))->connection());
		$this->assertNull($empty->findByDashboardAndBucket(dashboardUuid: 'd1', viewBucket: 'x'));

		$recorder = new QueryRecorder($this);
		(new DashboardViewMapper($recorder->connection()))->findByDashboardInRange(dashboardUuid: 'd1', startDate: '2026-11-01', endDate: '2026-11-30');
		$this->assertContains('qb1.expr.gte("view_bucket", ":p2", null)', $recorder->calls);
		$this->assertContains('qb1.expr.lte("view_bucket", ":p3", null)', $recorder->calls);
		$this->assertContains('qb1.orderBy("view_bucket", "ASC")', $recorder->calls);

		$recorder = new QueryRecorder($this);
		(new DashboardViewMapper($recorder->connection()))->findAllInRange(startDate: '2026-11-01', endDate: '2026-11-30');
		$this->assertContains('qb1.addOrderBy("view_bucket", "ASC")', $recorder->calls);
	}//end testViewLookups()

	/**
	 * Top dashboards and the instance summary are cast to integers.
	 *
	 * @return void
	 */
	public function testAggregates(): void {
		$recorder = new QueryRecorder($this);
		$recorder->rows = [['dashboard_uuid' => 'd1', 'view_count_sum' => '9', 'unique_viewer_sum' => '3']];
		$top = (new DashboardViewMapper($recorder->connection()))->findTopDashboardsInRange(startDate: 'a', endDate: 'b', limit: 10);
		$this->assertSame([['dashboardUuid' => 'd1', 'viewCount' => 9, 'uniqueViewerCount' => 3]], $top);
		$this->assertContains('qb1.groupBy("dashboard_uuid")', $recorder->calls);
		$this->assertContains('qb1.orderBy("view_count_sum", "DESC")', $recorder->calls);

		$recorder = new QueryRecorder($this);
		$recorder->rows = [['total_view_count' => '20', 'total_unique' => null, 'dashboard_count' => '2']];
		$this->assertSame(
			['totalViewCount' => 20, 'totalUniqueViewers' => 0, 'dashboardCount' => 2],
			(new DashboardViewMapper($recorder->connection()))->findInstanceSummaryInRange(startDate: 'a', endDate: 'b')
		);

		$this->assertSame(
			['totalViewCount' => 0, 'totalUniqueViewers' => 0, 'dashboardCount' => 0],
			(new DashboardViewMapper((new QueryRecorder($this))->connection()))->findInstanceSummaryInRange(startDate: 'a', endDate: 'b')
		);
	}//end testAggregates()

	/**
	 * Upsert adds to an existing bucket, or inserts a new one with no negative counts.
	 *
	 * @return void
	 */
	public function testUpsertView(): void {
		$recorder = new QueryRecorder($this);
		$recorder->rows = [['id' => 5, 'dashboard_uuid' => 'd1', 'view_bucket' => 'b', 'view_count' => 4, 'unique_viewer_count' => 2]];
		$mapper = new DashboardViewMapper($recorder->connection());
		$updated = $mapper->upsertView(dashboardUuid: 'd1', viewBucket: 'b', viewCountDelta: 3, uniqueCountDelta: 1);
		$this->assertSame([7, 3], [$updated->getViewCount(), $updated->getUniqueViewerCount()]);
		$this->assertContains('qb2.update("launchpad_dashboard_views", null)', $recorder->calls);

		$recorder = new QueryRecorder($this);
		$mapper = new DashboardViewMapper($recorder->connection());
		$inserted = $mapper->upsertView(dashboardUuid: 'd2', viewBucket: 'c', viewCountDelta: -2, uniqueCountDelta: 1);
		$this->assertSame(
			['d2', 'c', 0, 1],
			[$inserted->getDashboardUuid(), $inserted->getViewBucket(), $inserted->getViewCount(), $inserted->getUniqueViewerCount()]
		);
		$this->assertContains('qb2.insert("launchpad_dashboard_views")', $recorder->calls);
	}//end testUpsertView()

	/**
	 * Old buckets are deleted before a date, or all of one dashboard's.
	 *
	 * @return void
	 */
	public function testViewDeletes(): void {
		$recorder = new QueryRecorder($this);
		$this->assertSame(1, (new DashboardViewMapper($recorder->connection()))->deleteOlderThan(beforeDate: '2026-01-01'));
		$this->assertContains('qb1.createNamedParameter("2026-01-01", 2, null)', $recorder->calls);
		$this->assertContains('qb1.expr.lt("view_bucket", ":p1", null)', $recorder->calls);

		$recorder = new QueryRecorder($this);
		$this->assertSame(1, (new DashboardViewMapper($recorder->connection()))->deleteByDashboard(dashboardUuid: 'd1'));
		$this->assertContains('qb1.delete("launchpad_dashboard_views", null)', $recorder->calls);
	}//end testViewDeletes()
}//end class
