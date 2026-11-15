<?php

/**
 * TileMapperTest
 *
 * Unit tests for TileMapper: a user's tiles oldest first, one tile scoped to
 * its owner, and the per-user delete.
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

use OCA\LaunchPad\Db\TileMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\TestCase;

/**
 * Pins the queries TileMapper composes.
 *
 * @covers \OCA\LaunchPad\Db\TileMapper
 * @uses \OCA\LaunchPad\Db\ColumnMatchQueries
 * @uses \OCA\LaunchPad\Db\Tile
 */
class TileMapperTest extends TestCase {

	/**
	 * A user's tiles are read from the tiles table, oldest first.
	 *
	 * @return void
	 */
	public function testFindByUserIdIsOrderedByCreation(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 1, 'user_id' => 'alice'], ['id' => 2, 'user_id' => 'alice']];

		$tiles = (new TileMapper($recorder->connection()))->findByUserId('alice');

		$this->assertSame([1, 2], array_map(static fn ($tile): int => $tile->getId(), $tiles));
		$this->assertContains('qb1.from("launchpad_tiles", null)', $recorder->calls);
		$this->assertContains('qb1.createNamedParameter("alice", 2, null)', $recorder->calls);
		$this->assertContains('qb1.where("eq(\"user_id\", \":p1\", null)")', $recorder->calls);
		$this->assertContains('qb1.orderBy("created_at", "ASC")', $recorder->calls);
	}//end testFindByUserIdIsOrderedByCreation()

	/**
	 * One tile is found by its integer id and its owner, both required.
	 *
	 * @return void
	 */
	public function testFindByIdAndUserMatchesIdAndOwner(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 7, 'user_id' => 'alice']];

		$tile = (new TileMapper($recorder->connection()))->findByIdAndUser(7, 'alice');

		$this->assertSame(7, $tile->getId());
		$this->assertContains('qb1.createNamedParameter(7, 1, null)', $recorder->calls);
		$this->assertContains('qb1.where("eq(\"id\", \":p1\", null)")', $recorder->calls);
		$this->assertContains('qb1.andWhere("eq(\"user_id\", \":p2\", null)")', $recorder->calls);
	}//end testFindByIdAndUserMatchesIdAndOwner()

	/**
	 * Another user's tile, or a missing one, is not found.
	 *
	 * @return void
	 */
	public function testAnUnmatchedTileIsNotFound(): void {
		$recorder = new QueryRecorder(test: $this);

		$this->expectException(DoesNotExistException::class);
		(new TileMapper($recorder->connection()))->findByIdAndUser(7, 'bob');
	}//end testAnUnmatchedTileIsNotFound()

	/**
	 * Deleting a user's tiles issues one DELETE filtered on the user.
	 *
	 * @return void
	 */
	public function testDeleteByUserIdDeletesThatUsersTiles(): void {
		$recorder = new QueryRecorder(test: $this);

		(new TileMapper($recorder->connection()))->deleteByUserId('alice');

		$this->assertContains('qb1.delete("launchpad_tiles", null)', $recorder->calls);
		$this->assertContains('qb1.where("eq(\"user_id\", \":p1\", null)")', $recorder->calls);
		$this->assertContains('qb1.executeStatement(null)', $recorder->calls);
		$this->assertSame([], preg_grep('/andWhere/', $recorder->calls));
	}//end testDeleteByUserIdDeletesThatUsersTiles()
}//end class
