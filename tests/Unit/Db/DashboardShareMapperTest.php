<?php

/**
 * DashboardShareMapperTest
 *
 * Unit tests for DashboardShareMapper: the lookups by id, dashboard, level and
 * share key, the recipient query (user, or user or group), the deletes by
 * dashboard, recipient, owner and dashboard uuid, the keep-list prune and the
 * orphan count and purge.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Db
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Db;

use OCA\LaunchPad\Db\DashboardShare;
use OCA\LaunchPad\Db\DashboardShareMapper;
use PHPUnit\Framework\TestCase;

/**
 * Pins the queries DashboardShareMapper composes.
 *
 * @covers \OCA\LaunchPad\Db\DashboardShareMapper
 * @uses \OCA\LaunchPad\Db\ColumnMatchQueries
 * @uses \OCA\LaunchPad\Db\OrphanRowQueries
 * @uses \OCA\LaunchPad\Db\DashboardShare
 */
class DashboardShareMapperTest extends TestCase {

	/**
	 * A share is found by id, bound as an integer.
	 *
	 * @return void
	 */
	public function testFindBindsTheIdAsAnInteger(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 4, 'dashboard_id' => 9, 'share_type' => 'user', 'share_with' => 'bob']];

		$share = (new DashboardShareMapper($recorder->connection()))->find(4);

		$this->assertSame(4, $share->getId());
		$this->assertSame('bob', $share->getShareWith());
		$this->assertContains('qb1.from("launchpad_dashboard_shares", null)', $recorder->calls);
		$this->assertContains('qb1.createNamedParameter(4, 1, null)', $recorder->calls);
		$this->assertContains('qb1.expr.eq("id", ":p1", null)', $recorder->calls);
	}//end testFindBindsTheIdAsAnInteger()

	/**
	 * A dashboard's shares, and those at one level, are read oldest first.
	 *
	 * @return void
	 */
	public function testDashboardSharesAreOrderedByCreation(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 1, 'dashboard_id' => 9], ['id' => 2, 'dashboard_id' => 9]];
		$mapper = new DashboardShareMapper($recorder->connection());

		$this->assertCount(2, $mapper->findByDashboardId(9));
		$this->assertContains('qb1.expr.eq("dashboard_id", ":p1", null)', $recorder->calls);
		$this->assertContains('qb1.orderBy("created_at", "ASC")', $recorder->calls);

		$this->assertCount(2, $mapper->findByDashboardAndLevel(9, 'view_only'));
		$this->assertContains('qb2.expr.eq("dashboard_id", ":p2", null)', $recorder->calls);
		$this->assertContains('qb2.createNamedParameter("view_only", 2, null)', $recorder->calls);
		$this->assertContains('qb2.andWhere("eq(\"permission_level\", \":p3\", null)")', $recorder->calls);
		$this->assertContains('qb2.orderBy("created_at", "ASC")', $recorder->calls);
	}//end testDashboardSharesAreOrderedByCreation()

	/**
	 * One share is found by dashboard, type and recipient; an absent one is null.
	 *
	 * @return void
	 */
	public function testFindShareMatchesTheCompositeKeyOrAnswersNull(): void {
		$found = new QueryRecorder(test: $this);
		$found->rows = [['id' => 5, 'dashboard_id' => 9, 'share_type' => 'group', 'share_with' => 'staff']];

		$share = (new DashboardShareMapper($found->connection()))->findShare(9, 'group', 'staff');

		$this->assertInstanceOf(DashboardShare::class, $share);
		$this->assertSame(5, $share->getId());
		$this->assertContains('qb1.andWhere("eq(\"share_type\", \":p2\", null)")', $found->calls);
		$this->assertContains('qb1.andWhere("eq(\"share_with\", \":p3\", null)")', $found->calls);
		$this->assertContains('qb1.createNamedParameter("staff", 2, null)', $found->calls);

		$absent = new QueryRecorder(test: $this);
		$this->assertNull((new DashboardShareMapper($absent->connection()))->findShare(9, 'group', 'staff'));
	}//end testFindShareMatchesTheCompositeKeyOrAnswersNull()

	/**
	 * Without groups, only the user's own shares are asked for.
	 *
	 * @return void
	 */
	public function testARecipientWithoutGroupsMatchesUserSharesOnly(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 1, 'share_type' => 'user', 'share_with' => 'alice']];

		$shares = (new DashboardShareMapper($recorder->connection()))->findForRecipient('alice', []);

		$this->assertCount(1, $shares);
		$this->assertContains('qb1.createNamedParameter("user", 2, null)', $recorder->calls);
		$this->assertContains('qb1.createNamedParameter("alice", 2, null)', $recorder->calls);
		$this->assertSame([], preg_grep('/orX|PARAM|"group"/', $recorder->calls));
		$this->assertCount(1, preg_grep('/^qb1\.where\(/', $recorder->calls));
	}//end testARecipientWithoutGroupsMatchesUserSharesOnly()

	/**
	 * With groups, the user's shares or any of the groups' shares are asked for.
	 *
	 * @return void
	 */
	public function testARecipientWithGroupsMatchesUserOrGroupShares(): void {
		$recorder = new QueryRecorder(test: $this);

		$this->assertSame([], (new DashboardShareMapper($recorder->connection()))->findForRecipient('alice', ['staff', 'admin']));

		$this->assertContains('qb1.createNamedParameter("group", 2, null)', $recorder->calls);
		$this->assertContains('qb1.createNamedParameter(["staff","admin"], 102, null)', $recorder->calls);
		$this->assertContains('qb1.expr.in("share_with", ":p4", null)', $recorder->calls);
		$this->assertContains(
			'qb1.where(orX(andX("eq(\\"share_type\\", \\":p1\\", null)", "eq(\\"share_with\\", \\":p2\\", null)"), '
			. 'andX("eq(\\"share_type\\", \\":p3\\", null)", "in(\\"share_with\\", \\":p4\\", null)")))',
			$recorder->calls
		);
	}//end testARecipientWithGroupsMatchesUserOrGroupShares()

	/**
	 * The deletes by dashboard and by user recipient filter on those columns and answer the affected rows.
	 *
	 * @return void
	 */
	public function testDeletesByDashboardAndByRecipientUser(): void {
		$recorder = new QueryRecorder(test: $this);
		$mapper = new DashboardShareMapper($recorder->connection());

		$this->assertSame(1, $mapper->deleteByDashboardId(9));
		$this->assertContains('qb1.delete("launchpad_dashboard_shares", null)', $recorder->calls);
		$this->assertContains('qb1.expr.eq("dashboard_id", ":p1", null)', $recorder->calls);
		$this->assertContains('qb1.executeStatement(null)', $recorder->calls);

		$this->assertSame(1, $mapper->deleteByRecipientUser('bob'));
		$this->assertContains('qb2.delete("launchpad_dashboard_shares", null)', $recorder->calls);
		$this->assertContains('qb2.createNamedParameter("user", 2, null)', $recorder->calls);
		$this->assertContains('qb2.createNamedParameter("bob", 2, null)', $recorder->calls);
		$this->assertContains('qb2.andWhere("eq(\"share_with\", \":p3\", null)")', $recorder->calls);
	}//end testDeletesByDashboardAndByRecipientUser()

	/**
	 * Only the shares whose type:recipient key is not kept are deleted, one by one.
	 *
	 * @return void
	 */
	public function testDeleteNotInDeletesOnlyTheSharesNotKept(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [
			['id' => 1, 'dashboard_id' => 9, 'share_type' => 'user', 'share_with' => 'alice'],
			['id' => 2, 'dashboard_id' => 9, 'share_type' => 'group', 'share_with' => 'staff'],
			['id' => 3, 'dashboard_id' => 9, 'share_type' => 'user', 'share_with' => 'staff'],
		];

		$deleted = (new DashboardShareMapper($recorder->connection()))->deleteNotIn(9, ['group:staff']);

		$this->assertSame(2, $deleted);
		$this->assertCount(2, preg_grep('/\.delete\("launchpad_dashboard_shares"/', $recorder->calls));
		$this->assertContains('qb2.createNamedParameter(1, 1, null)', $recorder->calls);
		$this->assertContains('qb3.createNamedParameter(3, 1, null)', $recorder->calls);
		$this->assertSame([], preg_grep('/createNamedParameter\(2, 1,/', $recorder->calls));
	}//end testDeleteNotInDeletesOnlyTheSharesNotKept()

	/**
	 * Orphans are shares whose dashboard row is missing.
	 *
	 * @return void
	 */
	public function testOrphansJoinTheDashboards(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['cnt' => '3']];

		$this->assertSame(3, (new DashboardShareMapper($recorder->connection()))->countOrphaned());
		$this->assertContains('qb1.from("launchpad_dashboard_shares", "s")', $recorder->calls);
		$this->assertContains('qb1.leftJoin("s", "launchpad_dashboards", "d", "d.id = s.dashboard_id")', $recorder->calls);
		$this->assertContains('qb1.expr.isNull("d.id")', $recorder->calls);

		$purge = new QueryRecorder(test: $this);
		$purge->rows = [['id' => '6'], ['id' => '8']];
		$this->assertSame(1, (new DashboardShareMapper($purge->connection()))->deleteOrphaned());
		$this->assertContains('qb2.createNamedParameter([6,8], 101, null)', $purge->calls);
	}//end testOrphansJoinTheDashboards()

	/**
	 * Deleting by owner and recipient limits the delete to the owner's dashboards through a subquery.
	 *
	 * @return void
	 */
	public function testDeleteByOwnerAndRecipientScopesToTheOwnersDashboards(): void {
		$recorder = new QueryRecorder(test: $this);

		$this->assertSame(1, (new DashboardShareMapper($recorder->connection()))->deleteByOwnerAndRecipient('group', 'staff', 'owner'));

		$this->assertContains('qb1.select("id")', $recorder->calls);
		$this->assertContains('qb1.from("launchpad_dashboards", null)', $recorder->calls);
		$this->assertContains('qb1.createNamedParameter("owner", 2, null)', $recorder->calls);
		$this->assertContains('qb1.expr.eq("user_id", ":p1", null)', $recorder->calls);
		$this->assertContains('qb2.delete("launchpad_dashboard_shares", null)', $recorder->calls);
		$this->assertContains('qb2.createNamedParameter("group", 2, null)', $recorder->calls);
		$this->assertContains('qb2.createNamedParameter("staff", 2, null)', $recorder->calls);
		$this->assertNotSame([], preg_grep('/^qb2\.createFunction\("\(sql\)"\)$/', $recorder->calls));
		$this->assertNotSame([], preg_grep('/^qb2\.expr\.in\("dashboard_id", /', $recorder->calls));
		$this->assertContains('qb2.executeStatement(null)', $recorder->calls);
	}//end testDeleteByOwnerAndRecipientScopesToTheOwnersDashboards()

	/**
	 * Deleting by dashboard uuid selects the dashboard id by its bound uuid.
	 *
	 * @return void
	 */
	public function testDeleteByDashboardUuidBindsTheUuid(): void {
		$recorder = new QueryRecorder(test: $this);

		$this->assertSame(1, (new DashboardShareMapper($recorder->connection()))->deleteByDashboardUuid('u-1'));

		$this->assertContains('qb1.delete("launchpad_dashboard_shares", null)', $recorder->calls);
		$this->assertContains('qb1.createNamedParameter("u-1", 2, null)', $recorder->calls);
		$this->assertContains(
			'qb1.createFunction("(SELECT `id` FROM `*PREFIX*launchpad_dashboards` WHERE `uuid` = :p1)")',
			$recorder->calls
		);
	}//end testDeleteByDashboardUuidBindsTheUuid()
}//end class
