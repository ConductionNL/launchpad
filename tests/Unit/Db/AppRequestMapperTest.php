<?php

/**
 * AppRequestMapperTest
 *
 * Unit tests for AppRequestMapper: the open requests in the order they came in, the count of one user's open requests, and the lookup by uuid.
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

use OCA\LaunchPad\Db\AppRequestMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Db\AppRequestMapper
 * @uses   \OCA\LaunchPad\Db\AppRequest
 */
class AppRequestMapperTest extends TestCase {

	/**
	 * Only open requests, oldest first, with the id as tie-breaker.
	 *
	 * @return void
	 */
	public function testFindOpenListsOpenRequestsOldestFirst(): void {
		$recorder       = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 1, 'uuid' => 'a', 'status' => 'open'], ['id' => 2, 'uuid' => 'b', 'status' => 'open']];

		$requests = (new AppRequestMapper(db: $recorder->connection()))->findOpen();

		$this->assertSame(expected: ['a', 'b'], actual: array_map(static fn ($request): string => $request->getUuid(), $requests));
		$this->assertContains(needle: 'qb1.from("launchpad_app_requests", null)', haystack: $recorder->calls);
		$this->assertContains(needle: 'qb1.createNamedParameter("open", 2, null)', haystack: $recorder->calls);
		$this->assertContains(needle: 'qb1.where("eq(\"status\", \":p1\", null)")', haystack: $recorder->calls);
		$this->assertContains(needle: 'qb1.orderBy("created_at", null)', haystack: $recorder->calls);
		$this->assertContains(needle: 'qb1.addOrderBy("id", null)', haystack: $recorder->calls);
	}//end testFindOpenListsOpenRequestsOldestFirst()

	/**
	 * The count is of this user's requests that are still open, as an integer.
	 *
	 * @return void
	 */
	public function testCountOpenByCountsOneUsersOpenRequests(): void {
		$recorder       = new QueryRecorder(test: $this);
		$recorder->rows = [['open_count' => '3']];

		$this->assertSame(expected: 3, actual: (new AppRequestMapper(db: $recorder->connection()))->countOpenBy(userId: 'alice'));
		$this->assertContains(needle: 'qb1.createNamedParameter("alice", 2, null)', haystack: $recorder->calls);
		$this->assertContains(
			needle: 'qb1.where("eq(\"requested_by\", \":p1\", null)", "eq(\"status\", \":p2\", null)")',
			haystack: $recorder->calls
		);
		$this->assertContains(needle: 'qb1.createNamedParameter("open", 2, null)', haystack: $recorder->calls);
	}//end testCountOpenByCountsOneUsersOpenRequests()

	/**
	 * A user without requests counts zero.
	 *
	 * @return void
	 */
	public function testCountOpenByIsZeroWithoutRows(): void {
		$recorder = new QueryRecorder(test: $this);

		$this->assertSame(expected: 0, actual: (new AppRequestMapper(db: $recorder->connection()))->countOpenBy(userId: 'bob'));
	}//end testCountOpenByIsZeroWithoutRows()

	/**
	 * The lookup by uuid binds the uuid and throws when nothing matches.
	 *
	 * @return void
	 */
	public function testFindByUuid(): void {
		$recorder       = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 4, 'uuid' => 'req-4']];

		$this->assertSame(expected: 4, actual: (new AppRequestMapper(db: $recorder->connection()))->findByUuid(uuid: 'req-4')->getId());
		$this->assertContains(needle: 'qb1.where("eq(\"uuid\", \":p1\", null)")', haystack: $recorder->calls);
		$this->assertContains(needle: 'qb1.createNamedParameter("req-4", 2, null)', haystack: $recorder->calls);

		$this->expectException(exception: DoesNotExistException::class);
		(new AppRequestMapper(db: (new QueryRecorder(test: $this))->connection()))->findByUuid(uuid: 'missing');
	}//end testFindByUuid()
}//end class
