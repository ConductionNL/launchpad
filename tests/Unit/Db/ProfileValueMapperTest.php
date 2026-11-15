<?php

/**
 * ProfileValueMapperTest
 *
 * Unit tests for ProfileValueMapper: the multi-user read, the escaped and
 * capped substring search, and the delete-then-insert replacement of one field.
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

use OCA\LaunchPad\Db\ProfileFieldValues;
use OCA\LaunchPad\Db\ProfileValue;
use OCA\LaunchPad\Db\ProfileValueMapper;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Db\ProfileValueMapper
 * @uses \OCA\LaunchPad\Db\ColumnMatchQueries
 * @uses \OCA\LaunchPad\Db\ProfileFieldValues
 * @uses \OCA\LaunchPad\Db\ProfileValue
 */
class ProfileValueMapperTest extends TestCase {

	/**
	 * Several users are read with one IN, re-indexed, in id order.
	 *
	 * @return void
	 */
	public function testFindByUsersReadsAllUsersAtOnce(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 1, 'user_id' => 'alice', 'field_key' => 'phone', 'value' => '123']];

		$rows = (new ProfileValueMapper($recorder->connection()))->findByUsers([3 => 'alice', 7 => 'bob']);

		$this->assertCount(1, $rows);
		$this->assertSame('alice', $rows[0]->getUserId());
		$this->assertContains('qb1.from("launchpad_profile_values", null)', $recorder->calls);
		$this->assertContains('qb1.createNamedParameter(["alice","bob"], 102, null)', $recorder->calls);
		$this->assertContains('qb1.expr.in("user_id", ":p1", null)', $recorder->calls);
		$this->assertContains('qb1.orderBy("id", null)', $recorder->calls);
	}//end testFindByUsersReadsAllUsersAtOnce()

	/**
	 * No users means no rows and no query.
	 *
	 * @return void
	 */
	public function testNoUsersReadsNothing(): void {
		$recorder = new QueryRecorder(test: $this);

		$this->assertSame([], (new ProfileValueMapper($recorder->connection()))->findByUsers([]));
		$this->assertSame([], $recorder->calls);
	}//end testNoUsersReadsNothing()

	/**
	 * The needle is LIKE-escaped, wrapped in wildcards and the read is capped.
	 *
	 * @return void
	 */
	public function testSearchEscapesTheNeedleAndCapsTheRows(): void {
		$recorder = new QueryRecorder(test: $this);
		/** @var IDBConnection&MockObject $db */
		$db = $recorder->connection();
		$db->expects($this->once())
			->method('escapeLikeParameter')
			->with('50%')
			->willReturn('50\\%');

		(new ProfileValueMapper($db))->search('50%');

		$this->assertContains('qb1.createNamedParameter("%50\\\\%%", 2, null)', $recorder->calls);
		$this->assertContains('qb1.expr.like("value_search", ":p1", null)', $recorder->calls);
		$this->assertContains('qb1.setMaxResults(' . ProfileValueMapper::SEARCH_ROW_CAP . ')', $recorder->calls);
	}//end testSearchEscapesTheNeedleAndCapsTheRows()

	/**
	 * Replacing a field deletes its old values, then inserts one lower-cased-searchable row per value.
	 *
	 * @return void
	 */
	public function testReplaceValuesDeletesThenInsertsEachValue(): void {
		$recorder = new QueryRecorder(test: $this);

		(new ProfileValueMapper($recorder->connection()))->replaceValues(
			'alice',
			new ProfileFieldValues(fieldKey: 'skills', values: ['PHP', 'Vue'], source: 'ldap', scope: 'internal')
		);

		$deleteAt = array_search('qb1.delete("launchpad_profile_values", null)', $recorder->calls, true);
		$this->assertIsInt($deleteAt);
		$this->assertContains('qb1.expr.eq("user_id", ":p1", null)', $recorder->calls);
		$this->assertContains('qb1.expr.eq("field_key", ":p2", null)', $recorder->calls);
		$this->assertCount(2, preg_grep('/\.insert\("launchpad_profile_values"\)/', $recorder->calls));
		$this->assertContains('qb2.createNamedParameter("php", 2, null)', $recorder->calls);
		$this->assertContains('qb3.createNamedParameter("vue", 2, null)', $recorder->calls);
		$this->assertContains('qb3.createNamedParameter("internal", 2, null)', $recorder->calls);
		$this->assertLessThan(array_search('qb2 new', $recorder->calls, true), $deleteAt);
	}//end testReplaceValuesDeletesThenInsertsEachValue()

	/**
	 * Replacing a field with no values only deletes.
	 *
	 * @return void
	 */
	public function testReplacingWithNoValuesOnlyDeletes(): void {
		$recorder = new QueryRecorder(test: $this);

		(new ProfileValueMapper($recorder->connection()))->replaceValues(
			'alice',
			new ProfileFieldValues(fieldKey: 'skills', values: [], source: 'ldap')
		);

		$this->assertContains('qb1.executeStatement(null)', $recorder->calls);
		$this->assertSame([], preg_grep('/\.insert\(/', $recorder->calls));
	}//end testReplacingWithNoValuesOnlyDeletes()

	/**
	 * Deleting one user's rows returns the affected count.
	 *
	 * @return void
	 */
	public function testDeleteByUserReturnsTheCount(): void {
		$recorder = new QueryRecorder(test: $this);

		$this->assertSame(1, (new ProfileValueMapper($recorder->connection()))->deleteByUser('alice'));
		$this->assertContains('qb1.delete("launchpad_profile_values", null)', $recorder->calls);
		$this->assertContains('qb1.createNamedParameter("alice", 2, null)', $recorder->calls);
	}//end testDeleteByUserReturnsTheCount()
}//end class
