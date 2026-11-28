<?php

/**
 * RoleAssignmentMapperTest
 *
 * Unit tests for RoleAssignmentMapper: lookups by id, user, group and role,
 * the multi-group read, and the three deletes.
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

use OCA\LaunchPad\Db\RoleAssignmentMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Db\RoleAssignmentMapper
 * @uses \OCA\LaunchPad\Db\ColumnMatchQueries
 * @uses \OCA\LaunchPad\Db\GroupIdFilter
 * @uses \OCA\LaunchPad\Db\RoleAssignment
 */
class RoleAssignmentMapperTest extends TestCase {

	/**
	 * An assignment is found by id; a missing one throws.
	 *
	 * @return void
	 */
	public function testFindByIdBindsAnIntegerAndThrowsWhenMissing(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 3, 'user_id' => 'alice', 'role' => 'admin']];

		$this->assertSame(3, (new RoleAssignmentMapper($recorder->connection()))->findById(3)->getId());
		$this->assertContains('qb1.from("launchpad_role_assignments", null)', $recorder->calls);
		$this->assertContains('qb1.createNamedParameter(3, 1, null)', $recorder->calls);

		$this->expectException(DoesNotExistException::class);
		(new RoleAssignmentMapper((new QueryRecorder(test: $this))->connection()))->findById(4);
	}//end testFindByIdBindsAnIntegerAndThrowsWhenMissing()

	/**
	 * Every listing is in id order, the user and group ones filtered.
	 *
	 * @return void
	 */
	public function testListingsAreFilteredAndInIdOrder(): void {
		$all = new QueryRecorder(test: $this);
		$all->rows = [['id' => 1, 'user_id' => 'alice'], ['id' => 2, 'group_id' => 'staff']];
		$this->assertCount(2, (new RoleAssignmentMapper($all->connection()))->findAll());
		$this->assertContains('qb1.orderBy("id", "ASC")', $all->calls);

		$byUser = new QueryRecorder(test: $this);
		(new RoleAssignmentMapper($byUser->connection()))->findByUser('alice');
		$this->assertContains('qb1.expr.eq("user_id", ":p1", null)', $byUser->calls);
		$this->assertContains('qb1.orderBy("id", "ASC")', $byUser->calls);

		$byGroup = new QueryRecorder(test: $this);
		(new RoleAssignmentMapper($byGroup->connection()))->findByGroup('staff');
		$this->assertContains('qb1.expr.eq("group_id", ":p1", null)', $byGroup->calls);
		$this->assertContains('qb1.orderBy("id", "ASC")', $byGroup->calls);
	}//end testListingsAreFilteredAndInIdOrder()

	/**
	 * Several groups are read with one IN, ordered by group; none reads nothing.
	 *
	 * @return void
	 */
	public function testFindByGroupIds(): void {
		$recorder = new QueryRecorder(test: $this);
		(new RoleAssignmentMapper($recorder->connection()))->findByGroupIds(['staff', 'admins']);
		$this->assertContains('qb1.createNamedParameter(["staff","admins"], 102, null)', $recorder->calls);
		$this->assertContains('qb1.expr.in("group_id", ":p1", null)', $recorder->calls);
		$this->assertContains('qb1.orderBy("group_id", "ASC")', $recorder->calls);

		$none = new QueryRecorder(test: $this);
		$this->assertSame([], (new RoleAssignmentMapper($none->connection()))->findByGroupIds([]));
		$this->assertSame([], $none->calls);
	}//end testFindByGroupIds()

	/**
	 * A user's role is matched on both columns; a missing one is null.
	 *
	 * @return void
	 */
	public function testFindUserRole(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 3, 'user_id' => 'alice', 'role' => 'editor']];

		$row = (new RoleAssignmentMapper($recorder->connection()))->findUserRole('alice', 'editor');

		$this->assertSame('editor', $row?->getRole());
		$this->assertContains('qb1.where("eq(\"user_id\", \":p1\", null)")', $recorder->calls);
		$this->assertContains('qb1.andWhere("eq(\"role\", \":p2\", null)")', $recorder->calls);
		$this->assertNull((new RoleAssignmentMapper((new QueryRecorder(test: $this))->connection()))->findUserRole('bob', 'editor'));
	}//end testFindUserRole()

	/**
	 * A group's role is matched on both columns; a missing one is null.
	 *
	 * @return void
	 */
	public function testFindGroupRole(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 5, 'group_id' => 'staff', 'role' => 'viewer']];

		$row = (new RoleAssignmentMapper($recorder->connection()))->findGroupRole('staff', 'viewer');

		$this->assertSame('staff', $row?->getGroupId());
		$this->assertContains('qb1.where("eq(\"group_id\", \":p1\", null)")', $recorder->calls);
		$this->assertContains('qb1.andWhere("eq(\"role\", \":p2\", null)")', $recorder->calls);
		$this->assertNull((new RoleAssignmentMapper((new QueryRecorder(test: $this))->connection()))->findGroupRole('staff', 'admin'));
	}//end testFindGroupRole()

	/**
	 * Each delete targets its own column and returns the affected count.
	 *
	 * @return void
	 */
	public function testDeletesTargetTheirColumn(): void {
		foreach ([
			['deleteByUserId', 'alice', 'qb1.expr.eq("user_id", ":p1", null)'],
			['deleteByGroupId', 'staff', 'qb1.expr.eq("group_id", ":p1", null)'],
			['deleteById', 9, 'qb1.createNamedParameter(9, 1, null)'],
		] as [$method, $argument, $expected]) {
			$recorder = new QueryRecorder(test: $this);

			$this->assertSame(1, (new RoleAssignmentMapper($recorder->connection()))->$method($argument), $method);
			$this->assertContains('qb1.delete("launchpad_role_assignments", null)', $recorder->calls, $method);
			$this->assertContains($expected, $recorder->calls, $method);
		}
	}//end testDeletesTargetTheirColumn()
}//end class
