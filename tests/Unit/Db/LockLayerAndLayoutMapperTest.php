<?php

/**
 * Unit tests for DashboardLockMapper, PersonalLayerMapper and RoleLayoutDefaultMapper.
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

use OCA\LaunchPad\Db\DashboardLock;
use OCA\LaunchPad\Db\DashboardLockMapper;
use OCA\LaunchPad\Db\PersonalLayer;
use OCA\LaunchPad\Db\PersonalLayerMapper;
use OCA\LaunchPad\Db\RoleLayoutDefault;
use OCA\LaunchPad\Db\RoleLayoutDefaultMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\TestCase;

/**
 * The queries these mappers build, the entities they hydrate, and the
 * not-found cases that answer null instead of throwing.
 *
 * @covers \OCA\LaunchPad\Db\DashboardLockMapper
 * @covers \OCA\LaunchPad\Db\PersonalLayerMapper
 * @covers \OCA\LaunchPad\Db\RoleLayoutDefaultMapper
 * @uses   \OCA\LaunchPad\Db\ColumnMatchQueries
 * @uses   \OCA\LaunchPad\Db\DashboardLock
 * @uses   \OCA\LaunchPad\Db\PersonalLayer
 * @uses   \OCA\LaunchPad\Db\RoleLayoutDefault
 */
class LockLayerAndLayoutMapperTest extends TestCase {

	/**
	 * The lock expiry threshold the recorder saw, as a timestamp.
	 *
	 * @param QueryRecorder $recorder The recorder.
	 *
	 * @return int
	 */
	private function recordedThreshold(QueryRecorder $recorder): int {
		foreach ($recorder->calls as $call) {
			if (preg_match('/createNamedParameter\("(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})"/', $call, $m) === 1) {
				return strtotime($m[1]);
			}
		}

		$this->fail('No threshold parameter recorded');
	}//end recordedThreshold()

	/**
	 * A person's layer is looked up by user and dashboard, at most one row.
	 *
	 * @return void
	 */
	public function testFindsAPersonalLayer(): void {
		$recorder = new QueryRecorder($this);
		$recorder->rows = [['id' => 3, 'user_id' => 'ann', 'dashboard_id' => 7, 'hidden' => '["w1"]']];

		$layer = (new PersonalLayerMapper($recorder->connection()))->findForUser(userId: 'ann', dashboardId: 7);

		$this->assertInstanceOf(PersonalLayer::class, $layer);
		$this->assertSame(['ann', 7, '["w1"]'], [$layer->getUserId(), $layer->getDashboardId(), $layer->getHidden()]);
		$this->assertContains('qb1.from("launchpad_personal_layers", null)', $recorder->calls);
		$this->assertContains('qb1.createNamedParameter("ann", 2, null)', $recorder->calls);
		$this->assertContains('qb1.createNamedParameter(7, 1, null)', $recorder->calls);
		$this->assertContains('qb1.setMaxResults(1)', $recorder->calls);
	}//end testFindsAPersonalLayer()

	/**
	 * No layer row means no layer, not an error.
	 *
	 * @return void
	 */
	public function testNoPersonalLayerIsNull(): void {
		$recorder = new QueryRecorder($this);

		$this->assertNull((new PersonalLayerMapper($recorder->connection()))->findForUser(userId: 'bob', dashboardId: 1));
	}//end testNoPersonalLayerIsNull()

	/**
	 * Deleting a layer targets that user and dashboard and returns the affected rows.
	 *
	 * @return void
	 */
	public function testDeletesAPersonalLayer(): void {
		$recorder = new QueryRecorder($this);

		$this->assertSame(1, (new PersonalLayerMapper($recorder->connection()))->deleteForUser(userId: 'ann', dashboardId: 7));
		$this->assertSame(
			[
				'qb1 new',
				'qb1.delete("launchpad_personal_layers", null)',
				'qb1.expr()',
				'qb1.createNamedParameter("ann", 2, null)',
				'qb1.expr.eq("user_id", ":p1", null)',
				'qb1.where("eq(\"user_id\", \":p1\", null)")',
				'qb1.expr()',
				'qb1.createNamedParameter(7, 1, null)',
				'qb1.expr.eq("dashboard_id", ":p2", null)',
				'qb1.andWhere("eq(\"dashboard_id\", \":p2\", null)")',
				'qb1.executeStatement(null)',
			],
			$recorder->calls
		);
	}//end testDeletesAPersonalLayer()

	/**
	 * Role layout defaults are listed by group then sort order, and looked up by id, group or group and widget.
	 *
	 * @return void
	 */
	public function testRoleLayoutDefaultQueries(): void {
		$row = ['id' => 4, 'name' => 'Start', 'group_id' => 'staff', 'widget_id' => 'news', 'grid_width' => 6, 'sort_order' => 2];

		$recorder = new QueryRecorder($this);
		$recorder->rows = [$row];
		$all = (new RoleLayoutDefaultMapper($recorder->connection()))->findAll();
		$this->assertCount(1, $all);
		$this->assertInstanceOf(RoleLayoutDefault::class, $all[0]);
		$this->assertSame(['staff', 'news', 6], [$all[0]->getGroupId(), $all[0]->getWidgetId(), $all[0]->getGridWidth()]);
		$this->assertContains('qb1.orderBy("group_id", "ASC")', $recorder->calls);
		$this->assertContains('qb1.addOrderBy("sort_order", "ASC")', $recorder->calls);

		$recorder = new QueryRecorder($this);
		$recorder->rows = [$row];
		$byGroup = (new RoleLayoutDefaultMapper($recorder->connection()))->findByGroupId(groupId: 'staff');
		$this->assertSame(4, $byGroup[0]->getId());
		$this->assertContains('qb1.createNamedParameter("staff", 2, null)', $recorder->calls);
		$this->assertContains('qb1.orderBy("sort_order", "ASC")', $recorder->calls);

		$recorder = new QueryRecorder($this);
		$recorder->rows = [$row];
		$one = (new RoleLayoutDefaultMapper($recorder->connection()))->findByGroupAndWidget(groupId: 'staff', widgetId: 'news');
		$this->assertSame('Start', $one->getName());
		$this->assertContains('qb1.expr.eq("widget_id", ":p2", null)', $recorder->calls);

		$recorder = new QueryRecorder($this);
		$this->expectException(DoesNotExistException::class);
		(new RoleLayoutDefaultMapper($recorder->connection()))->find(id: 99);
	}//end testRoleLayoutDefaultQueries()

	/**
	 * An active lock is one updated within the lock timeout; none answers null.
	 *
	 * @return void
	 */
	public function testFindActiveLockUsesTheTimeout(): void {
		$recorder = new QueryRecorder($this);
		$recorder->rows = [['id' => 1, 'dashboard_uuid' => 'd1', 'user_id' => 'ann', 'display_name' => 'Ann']];
		$before = time();

		$lock = (new DashboardLockMapper($recorder->connection()))->findActive(dashboardUuid: 'd1');

		$this->assertInstanceOf(DashboardLock::class, $lock);
		$this->assertSame(['d1', 'ann'], [$lock->getDashboardUuid(), $lock->getUserId()]);
		$threshold = $this->recordedThreshold(recorder: $recorder);
		$this->assertEqualsWithDelta($before - DashboardLock::LOCK_TIMEOUT_SECONDS, $threshold, 5);
		$this->assertContains('qb1.expr.gte("updated_at", ":p2", null)', $recorder->calls);

		$this->assertNull((new DashboardLockMapper((new QueryRecorder($this))->connection()))->findActive(dashboardUuid: 'd2'));
	}//end testFindActiveLockUsesTheTimeout()

	/**
	 * Lock lookups by dashboard and by user, newest first for a user.
	 *
	 * @return void
	 */
	public function testLockLookups(): void {
		$row = ['id' => 1, 'dashboard_uuid' => 'd1', 'user_id' => 'ann'];

		$recorder = new QueryRecorder($this);
		$recorder->rows = [$row];
		$this->assertSame('d1', (new DashboardLockMapper($recorder->connection()))->findByDashboardUuid(dashboardUuid: 'd1')->getDashboardUuid());
		$this->assertContains('qb1.expr.eq("dashboard_uuid", ":p1", null)', $recorder->calls);

		$recorder = new QueryRecorder($this);
		$recorder->rows = [$row, $row];
		$this->assertCount(2, (new DashboardLockMapper($recorder->connection()))->findByUserId(userId: 'ann'));
		$this->assertContains('qb1.orderBy("updated_at", "DESC")', $recorder->calls);
	}//end testLockLookups()

	/**
	 * Expired locks are deleted or counted with an older-than-timeout condition.
	 *
	 * @return void
	 */
	public function testExpiredLockCleanup(): void {
		$recorder = new QueryRecorder($this);
		$before = time();
		$this->assertSame(1, (new DashboardLockMapper($recorder->connection()))->deleteExpiredForDashboard(dashboardUuid: 'd1'));
		$this->assertContains('qb1.delete("launchpad_dashboard_locks", null)', $recorder->calls);
		$this->assertContains('qb1.expr.lt("updated_at", ":p2", null)', $recorder->calls);
		$this->assertEqualsWithDelta($before - DashboardLock::LOCK_TIMEOUT_SECONDS, $this->recordedThreshold(recorder: $recorder), 5);

		$recorder = new QueryRecorder($this);
		$this->assertSame(1, (new DashboardLockMapper($recorder->connection()))->deleteAllExpired());
		$this->assertContains('qb1.where("lt(\"updated_at\", \":p1\", null)")', $recorder->calls);

		$recorder = new QueryRecorder($this);
		$this->assertSame(1, (new DashboardLockMapper($recorder->connection()))->deleteByDashboardUuid(dashboardUuid: 'd1'));
		$this->assertNotContains('qb1.expr.lt("updated_at", ":p2", null)', $recorder->calls);

		$recorder = new QueryRecorder($this);
		$recorder->rows = [['cnt' => '4']];
		$this->assertSame(4, (new DashboardLockMapper($recorder->connection()))->countAllExpired());

		$this->assertSame(0, (new DashboardLockMapper((new QueryRecorder($this))->connection()))->countAllExpired());
	}//end testExpiredLockCleanup()
}//end class
