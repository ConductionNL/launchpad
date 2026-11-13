<?php

/**
 * ConditionalRuleMapperTest
 *
 * Unit tests for ConditionalRuleMapper: lookups by id and placement, the
 * ordered listing, the per-placement delete, and the orphan count and purge
 * over rules whose placement no longer exists.
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

use OCA\LaunchPad\Db\ConditionalRuleMapper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Db\ConditionalRuleMapper
 * @uses \OCA\LaunchPad\Db\ColumnMatchQueries
 * @uses \OCA\LaunchPad\Db\OrphanRowQueries
 * @uses \OCA\LaunchPad\Db\ConditionalRule
 */
class ConditionalRuleMapperTest extends TestCase {

	/**
	 * A rule is found by id, bound as an integer.
	 *
	 * @return void
	 */
	public function testFindBindsTheIdAsAnInteger(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 2, 'widget_placement_id' => 9]];

		$rule = (new ConditionalRuleMapper($recorder->connection()))->find(2);

		$this->assertSame(2, $rule->getId());
		$this->assertContains('qb1.from("launchpad_conditional_rules", null)', $recorder->calls);
		$this->assertContains('qb1.createNamedParameter(2, 1, null)', $recorder->calls);
	}//end testFindBindsTheIdAsAnInteger()

	/**
	 * A placement's rules are read oldest first.
	 *
	 * @return void
	 */
	public function testFindByPlacementIdIsOrderedByCreation(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 1, 'widget_placement_id' => 9], ['id' => 2, 'widget_placement_id' => 9]];

		$rules = (new ConditionalRuleMapper($recorder->connection()))->findByPlacementId(9);

		$this->assertCount(2, $rules);
		$this->assertContains('qb1.expr.eq("widget_placement_id", ":p1", null)', $recorder->calls);
		$this->assertContains('qb1.orderBy("created_at", "ASC")', $recorder->calls);
	}//end testFindByPlacementIdIsOrderedByCreation()

	/**
	 * The listing is ordered by placement, then creation.
	 *
	 * @return void
	 */
	public function testFindAllIsOrderedByPlacementThenCreation(): void {
		$recorder = new QueryRecorder(test: $this);

		$this->assertSame([], (new ConditionalRuleMapper($recorder->connection()))->findAll());
		$this->assertContains('qb1.orderBy("widget_placement_id", "ASC")', $recorder->calls);
		$this->assertContains('qb1.addOrderBy("created_at", "ASC")', $recorder->calls);
	}//end testFindAllIsOrderedByPlacementThenCreation()

	/**
	 * Deleting by placement issues one DELETE filtered on the placement.
	 *
	 * @return void
	 */
	public function testDeleteByPlacementIdDeletesThatPlacementsRules(): void {
		$recorder = new QueryRecorder(test: $this);

		(new ConditionalRuleMapper($recorder->connection()))->deleteByPlacementId(9);

		$this->assertContains('qb1.delete("launchpad_conditional_rules", null)', $recorder->calls);
		$this->assertContains('qb1.expr.eq("widget_placement_id", ":p1", null)', $recorder->calls);
		$this->assertContains('qb1.executeStatement(null)', $recorder->calls);
	}//end testDeleteByPlacementIdDeletesThatPlacementsRules()

	/**
	 * Orphans are rules whose placement row is missing.
	 *
	 * @return void
	 */
	public function testCountOrphanedJoinsThePlacements(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['cnt' => '4']];

		$this->assertSame(4, (new ConditionalRuleMapper($recorder->connection()))->countOrphaned());
		$this->assertContains('qb1.from("launchpad_conditional_rules", "r")', $recorder->calls);
		$this->assertContains('qb1.leftJoin("r", "launchpad_widget_placements", "p", "p.id = r.widget_placement_id")', $recorder->calls);
		$this->assertContains('qb1.expr.isNull("p.id")', $recorder->calls);
	}//end testCountOrphanedJoinsThePlacements()

	/**
	 * The purge deletes exactly the orphaned ids, and nothing when there are none.
	 *
	 * @return void
	 */
	public function testDeleteOrphanedDeletesTheOrphanIds(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => '3'], ['id' => '7']];

		$this->assertSame(1, (new ConditionalRuleMapper($recorder->connection()))->deleteOrphaned());
		$this->assertContains('qb2.delete("launchpad_conditional_rules", null)', $recorder->calls);
		$this->assertContains('qb2.createNamedParameter([3,7], 101, null)', $recorder->calls);

		$none = new QueryRecorder(test: $this);
		$this->assertSame(0, (new ConditionalRuleMapper($none->connection()))->deleteOrphaned());
		$this->assertSame([], preg_grep('/\.delete\(/', $none->calls));
	}//end testDeleteOrphanedDeletesTheOrphanIds()
}//end class
