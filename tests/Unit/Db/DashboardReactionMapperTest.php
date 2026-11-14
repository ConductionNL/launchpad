<?php

/**
 * DashboardReactionMapperTest
 *
 * Unit tests for DashboardReactionMapper: the reaction lookups by dashboard,
 * emoji (paged) and user, the per-emoji counts, adding and removing a
 * reaction, the per-dashboard delete and the reactor count.
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

use DateTimeInterface;
use OCA\LaunchPad\Db\DashboardReactionMapper;
use PHPUnit\Framework\TestCase;

/**
 * Pins the queries DashboardReactionMapper composes.
 *
 * @covers \OCA\LaunchPad\Db\DashboardReactionMapper
 * @uses \OCA\LaunchPad\Db\ColumnMatchQueries
 * @uses \OCA\LaunchPad\Db\DashboardReaction
 * @uses \OCA\LaunchPad\Db\DashboardUuidCondition
 */
class DashboardReactionMapperTest extends TestCase {

	/**
	 * A dashboard's reactions are read newest first.
	 *
	 * @return void
	 */
	public function testFindByDashboardIsNewestFirst(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 2, 'dashboard_uuid' => 'd-1', 'emoji' => '👍']];

		$reactions = (new DashboardReactionMapper($recorder->connection()))->findByDashboard('d-1');

		$this->assertCount(1, $reactions);
		$this->assertSame('👍', $reactions[0]->getEmoji());
		$this->assertContains('qb1.from("launchpad_dash_reactions", null)', $recorder->calls);
		$this->assertContains('qb1.where("eq(\"dashboard_uuid\", \":p1\", null)")', $recorder->calls);
		$this->assertContains('qb1.orderBy("reacted_at", "DESC")', $recorder->calls);
	}//end testFindByDashboardIsNewestFirst()

	/**
	 * Reactors of one emoji are read oldest first, with a stable id tie-break, paged only when asked.
	 *
	 * @return void
	 */
	public function testFindByEmojiIsOldestFirstAndPagedOnRequest(): void {
		$recorder = new QueryRecorder(test: $this);
		$mapper = new DashboardReactionMapper($recorder->connection());

		$this->assertSame([], $mapper->findByEmoji('d-1', '🎉'));
		$this->assertContains('qb1.andWhere("eq(\"emoji\", \":p2\", null)")', $recorder->calls);
		$this->assertContains('qb1.orderBy("reacted_at", "ASC")', $recorder->calls);
		$this->assertContains('qb1.addOrderBy("id", "ASC")', $recorder->calls);
		$this->assertSame([], preg_grep('/setMaxResults|setFirstResult/', $recorder->calls));

		$mapper->findByEmoji('d-1', '🎉', 10, 20);
		$this->assertContains('qb2.setMaxResults(10)', $recorder->calls);
		$this->assertContains('qb2.setFirstResult(20)', $recorder->calls);
	}//end testFindByEmojiIsOldestFirstAndPagedOnRequest()

	/**
	 * A user's reactions on one dashboard match both the dashboard and the user.
	 *
	 * @return void
	 */
	public function testFindByUserMatchesDashboardAndUser(): void {
		$recorder = new QueryRecorder(test: $this);

		(new DashboardReactionMapper($recorder->connection()))->findByUser('alice', 'd-1');

		$this->assertContains('qb1.createNamedParameter("d-1", 2, null)', $recorder->calls);
		$this->assertContains('qb1.where("eq(\"dashboard_uuid\", \":p1\", null)")', $recorder->calls);
		$this->assertContains('qb1.andWhere("eq(\"user_id\", \":p2\", null)")', $recorder->calls);
	}//end testFindByUserMatchesDashboardAndUser()

	/**
	 * The counts are grouped by emoji and answered as emoji => integer count.
	 *
	 * @return void
	 */
	public function testCountByEmojiGroupsAndCastsTheCounts(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['emoji' => '👍', 'cnt' => '3'], ['emoji' => '🎉', 'cnt' => 1]];

		$counts = (new DashboardReactionMapper($recorder->connection()))->countByEmoji('d-1');

		$this->assertSame(['👍' => 3, '🎉' => 1], $counts);
		$this->assertContains('qb1.select("emoji")', $recorder->calls);
		$this->assertContains('qb1.func.count("*", "")', $recorder->calls);
		$this->assertContains('qb1.where("eq(\"dashboard_uuid\", \":p1\", null)")', $recorder->calls);
		$this->assertContains('qb1.groupBy(groupBys: "emoji")', $recorder->calls);
	}//end testCountByEmojiGroupsAndCastsTheCounts()

	/**
	 * Adding a reaction inserts it stamped with the reaction time.
	 *
	 * @return void
	 */
	public function testAddReactionInsertsAStampedReaction(): void {
		$recorder = new QueryRecorder(test: $this);

		$reaction = (new DashboardReactionMapper($recorder->connection()))->addReaction('d-1', 'alice', '👍');

		$this->assertSame('d-1', $reaction->getDashboardUuid());
		$this->assertSame('alice', $reaction->getUserId());
		$this->assertSame('👍', $reaction->getEmoji());
		$this->assertInstanceOf(DateTimeInterface::class, $reaction->getReactedAt());
		$this->assertSame(1, $reaction->getId());
		$this->assertContains('qb1.insert("launchpad_dash_reactions")', $recorder->calls);
	}//end testAddReactionInsertsAStampedReaction()

	/**
	 * Removing a reaction answers whether a row was deleted.
	 *
	 * @return void
	 */
	public function testRemoveReactionDeletesTheExactReaction(): void {
		$recorder = new QueryRecorder(test: $this);

		$this->assertTrue((new DashboardReactionMapper($recorder->connection()))->removeReaction('d-1', 'alice', '👍'));
		$this->assertContains('qb1.delete("launchpad_dash_reactions", null)', $recorder->calls);
		$this->assertContains('qb1.andWhere("eq(\"user_id\", \":p2\", null)")', $recorder->calls);
		$this->assertContains('qb1.andWhere("eq(\"emoji\", \":p3\", null)")', $recorder->calls);
	}//end testRemoveReactionDeletesTheExactReaction()

	/**
	 * The dashboard delete and the reactor count filter on the dashboard (and emoji).
	 *
	 * @return void
	 */
	public function testDeleteByDashboardAndCountReactors(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['cnt' => '5']];
		$mapper = new DashboardReactionMapper($recorder->connection());

		$this->assertSame(1, $mapper->deleteByDashboardUuid('d-1'));
		$this->assertContains('qb1.delete("launchpad_dash_reactions", null)', $recorder->calls);
		$this->assertSame([], preg_grep('/^qb1\.andWhere/', $recorder->calls));

		$this->assertSame(5, $mapper->countReactorsByEmoji('d-1', '👍'));
		$this->assertContains('qb2.func.count("*", "cnt")', $recorder->calls);
		$this->assertContains('qb2.andWhere("eq(\"emoji\", \":p3\", null)")', $recorder->calls);
	}//end testDeleteByDashboardAndCountReactors()
}//end class
