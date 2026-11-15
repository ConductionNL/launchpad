<?php

/**
 * MetadataFieldMapperTest
 *
 * Unit tests for MetadataFieldMapper: the ordered listing, the lookups by id,
 * key and id list, the per-field value count and the cascading delete.
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

use OCA\LaunchPad\Db\MetadataFieldMapper;
use PHPUnit\Framework\TestCase;

/**
 * Pins the queries MetadataFieldMapper composes.
 *
 * @covers \OCA\LaunchPad\Db\MetadataFieldMapper
 * @uses \OCA\LaunchPad\Db\ColumnMatchQueries
 * @uses \OCA\LaunchPad\Db\MetadataField
 */
class MetadataFieldMapperTest extends TestCase {

	/**
	 * The fields are listed by sort order, then id.
	 *
	 * @return void
	 */
	public function testFindAllIsOrderedBySortOrderThenId(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 2, 'field_key' => 'b'], ['id' => 1, 'field_key' => 'a']];

		$fields = (new MetadataFieldMapper($recorder->connection()))->findAll();

		$this->assertCount(2, $fields);
		$this->assertContains('qb1.from("launchpad_meta_fields", null)', $recorder->calls);
		$this->assertContains('qb1.orderBy("sort_order", "ASC")', $recorder->calls);
		$this->assertContains('qb1.addOrderBy("id", "ASC")', $recorder->calls);
	}//end testFindAllIsOrderedBySortOrderThenId()

	/**
	 * One field is found by integer id or by key.
	 *
	 * @return void
	 */
	public function testFindByIdAndByKey(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 3, 'field_key' => 'department']];
		$mapper = new MetadataFieldMapper($recorder->connection());

		$this->assertSame('department', $mapper->findById(3)->getFieldKey());
		$this->assertContains('qb1.createNamedParameter(3, 1, null)', $recorder->calls);
		$this->assertContains('qb1.where("eq(\"id\", \":p1\", null)")', $recorder->calls);

		$this->assertSame(3, $mapper->findByKey('department')->getId());
		$this->assertContains('qb2.createNamedParameter("department", 2, null)', $recorder->calls);
		$this->assertContains('qb2.where("eq(\"field_key\", \":p2\", null)")', $recorder->calls);
	}//end testFindByIdAndByKey()

	/**
	 * An id list is bound as an integer array and the result keyed by id; an empty list queries nothing.
	 *
	 * @return void
	 */
	public function testFindByIdsKeysTheFieldsByIdAndSkipsAnEmptyList(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 5, 'field_key' => 'e'], ['id' => 2, 'field_key' => 'b']];

		$byId = (new MetadataFieldMapper($recorder->connection()))->findByIds([2, 5]);

		$this->assertSame([5, 2], array_keys($byId));
		$this->assertSame('e', $byId[5]->getFieldKey());
		$this->assertContains('qb1.createNamedParameter([2,5], 101, null)', $recorder->calls);
		$this->assertContains('qb1.expr.in("id", ":p1", null)', $recorder->calls);

		$none = new QueryRecorder(test: $this);
		$this->assertSame([], (new MetadataFieldMapper($none->connection()))->findByIds([]));
		$this->assertSame([], $none->calls);
	}//end testFindByIdsKeysTheFieldsByIdAndSkipsAnEmptyList()

	/**
	 * The value count reads the values table for that field; no row counts as zero.
	 *
	 * @return void
	 */
	public function testCountValuesForFieldCountsThatFieldsValues(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['cnt' => '7']];

		$this->assertSame(7, (new MetadataFieldMapper($recorder->connection()))->countValuesForField(4));
		$this->assertContains('qb1.from("launchpad_meta_values", null)', $recorder->calls);
		$this->assertContains('qb1.createNamedParameter(4, 1, null)', $recorder->calls);
		$this->assertContains('qb1.expr.eq("field_id", ":p1", null)', $recorder->calls);

		$empty = new QueryRecorder(test: $this);
		$this->assertSame(0, (new MetadataFieldMapper($empty->connection()))->countValuesForField(4));
	}//end testCountValuesForFieldCountsThatFieldsValues()

	/**
	 * The cascade deletes the field's values before the field itself.
	 *
	 * @return void
	 */
	public function testDeleteWithCascadeDeletesValuesFirst(): void {
		$recorder = new QueryRecorder(test: $this);

		$this->assertTrue((new MetadataFieldMapper($recorder->connection()))->deleteWithCascade(4));

		$deletes = array_values(preg_grep('/\.delete\(/', $recorder->calls));
		$this->assertSame(['qb1.delete("launchpad_meta_values", null)', 'qb2.delete("launchpad_meta_fields", null)'], $deletes);
		$this->assertContains('qb1.expr.eq("field_id", ":p1", null)', $recorder->calls);
		$this->assertContains('qb2.expr.eq("id", ":p2", null)', $recorder->calls);
		$this->assertLessThan(
			array_search('qb2.executeStatement(null)', $recorder->calls, true),
			array_search('qb1.executeStatement(null)', $recorder->calls, true)
		);
	}//end testDeleteWithCascadeDeletesValuesFirst()
}//end class
