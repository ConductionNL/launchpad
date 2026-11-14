<?php

/**
 * RoleFeaturePermissionMapperTest
 *
 * Unit tests for RoleFeaturePermissionMapper: lookups by id and group, the
 * ordered listing, and the multi-group read that never queries for no groups.
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

use OCA\LaunchPad\Db\RoleFeaturePermission;
use OCA\LaunchPad\Db\RoleFeaturePermissionMapper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Db\RoleFeaturePermissionMapper
 * @uses \OCA\LaunchPad\Db\ColumnMatchQueries
 * @uses \OCA\LaunchPad\Db\GroupIdFilter
 * @uses \OCA\LaunchPad\Db\RoleFeaturePermission
 */
class RoleFeaturePermissionMapperTest extends TestCase {

	/**
	 * A row is found by id, bound as an integer.
	 *
	 * @return void
	 */
	public function testFindBindsTheIdAsAnInteger(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 6, 'group_id' => 'editors']];

		$row = (new RoleFeaturePermissionMapper($recorder->connection()))->find(6);

		$this->assertSame(6, $row->getId());
		$this->assertContains('qb1.from("launchpad_role_feat_perms", null)', $recorder->calls);
		$this->assertContains('qb1.createNamedParameter(6, 1, null)', $recorder->calls);
		$this->assertContains('qb1.expr.eq("id", ":p1", null)', $recorder->calls);
	}//end testFindBindsTheIdAsAnInteger()

	/**
	 * A row is found by its group.
	 *
	 * @return void
	 */
	public function testFindByGroupIdMatchesTheGroupColumn(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 6, 'group_id' => 'editors']];

		$row = (new RoleFeaturePermissionMapper($recorder->connection()))->findByGroupId('editors');

		$this->assertSame('editors', $row->getGroupId());
		$this->assertContains('qb1.createNamedParameter("editors", 2, null)', $recorder->calls);
		$this->assertContains('qb1.expr.eq("group_id", ":p1", null)', $recorder->calls);
	}//end testFindByGroupIdMatchesTheGroupColumn()

	/**
	 * The listing is ordered by group.
	 *
	 * @return void
	 */
	public function testFindAllIsOrderedByGroup(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 1, 'group_id' => 'a'], ['id' => 2, 'group_id' => 'b']];

		$rows = (new RoleFeaturePermissionMapper($recorder->connection()))->findAll();

		$this->assertSame(['a', 'b'], array_map(static fn (RoleFeaturePermission $row): ?string => $row->getGroupId(), $rows));
		$this->assertContains('qb1.orderBy("group_id", "ASC")', $recorder->calls);
	}//end testFindAllIsOrderedByGroup()

	/**
	 * Several groups are read with one IN over a string array.
	 *
	 * @return void
	 */
	public function testFindByGroupIdsReadsAllGroupsAtOnce(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 1, 'group_id' => 'a']];

		$rows = (new RoleFeaturePermissionMapper($recorder->connection()))->findByGroupIds(['a', 'b']);

		$this->assertCount(1, $rows);
		$this->assertContains('qb1.createNamedParameter(["a","b"], 102, null)', $recorder->calls);
		$this->assertContains('qb1.expr.in("group_id", ":p1", null)', $recorder->calls);
	}//end testFindByGroupIdsReadsAllGroupsAtOnce()

	/**
	 * No groups means no rows and no query.
	 *
	 * @return void
	 */
	public function testNoGroupsReadsNothing(): void {
		$recorder = new QueryRecorder(test: $this);

		$this->assertSame([], (new RoleFeaturePermissionMapper($recorder->connection()))->findByGroupIds([]));
		$this->assertSame([], $recorder->calls);
	}//end testNoGroupsReadsNothing()
}//end class
