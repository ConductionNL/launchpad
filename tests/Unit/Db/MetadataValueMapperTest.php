<?php

/**
 * MetadataValueMapperTest
 *
 * Unit tests for MetadataValueMapper: reads by dashboard, field and the
 * dashboard/field pair, the update-or-insert upsert and the per-dashboard delete.
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

use OCA\LaunchPad\Db\MetadataValue;
use OCA\LaunchPad\Db\MetadataValueMapper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Db\MetadataValueMapper
 * @uses \OCA\LaunchPad\Db\ColumnMatchQueries
 * @uses \OCA\LaunchPad\Db\MetadataValue
 */
class MetadataValueMapperTest extends TestCase {

	/**
	 * A dashboard's values are read by its uuid.
	 *
	 * @return void
	 */
	public function testFindByDashboardMatchesTheUuid(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 1, 'dashboard_uuid' => 'd-1', 'field_id' => 4, 'value' => 'x']];

		$rows = (new MetadataValueMapper($recorder->connection()))->findByDashboard('d-1');

		$this->assertCount(1, $rows);
		$this->assertSame('x', $rows[0]->getValue());
		$this->assertContains('qb1.from("launchpad_meta_values", null)', $recorder->calls);
		$this->assertContains('qb1.expr.eq("dashboard_uuid", ":p1", null)', $recorder->calls);
	}//end testFindByDashboardMatchesTheUuid()

	/**
	 * A field's values are read with the field id bound as an integer.
	 *
	 * @return void
	 */
	public function testFindByFieldBindsAnInteger(): void {
		$recorder = new QueryRecorder(test: $this);

		$this->assertSame([], (new MetadataValueMapper($recorder->connection()))->findByField(4));
		$this->assertContains('qb1.createNamedParameter(4, 1, null)', $recorder->calls);
		$this->assertContains('qb1.expr.eq("field_id", ":p1", null)', $recorder->calls);
	}//end testFindByFieldBindsAnInteger()

	/**
	 * One value is matched on both the dashboard and the field.
	 *
	 * @return void
	 */
	public function testFindOneMatchesDashboardAndField(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 5, 'dashboard_uuid' => 'd-1', 'field_id' => 4, 'value' => 'x']];

		$row = (new MetadataValueMapper($recorder->connection()))->findOne('d-1', 4);

		$this->assertSame(5, $row?->getId());
		$this->assertContains('qb1.expr.eq("dashboard_uuid", ":p1", null)', $recorder->calls);
		$this->assertContains('qb1.createNamedParameter(4, 1, null)', $recorder->calls);
		$this->assertContains('qb1.expr.eq("field_id", ":p2", null)', $recorder->calls);
		$this->assertCount(1, preg_grep('/^qb1\.expr\.andX\(/', $recorder->calls));
	}//end testFindOneMatchesDashboardAndField()

	/**
	 * A missing pair is null, not an exception.
	 *
	 * @return void
	 */
	public function testFindOneOfAMissingPairIsNull(): void {
		$recorder = new QueryRecorder(test: $this);

		$this->assertNull((new MetadataValueMapper($recorder->connection()))->findOne('d-1', 4));
	}//end testFindOneOfAMissingPairIsNull()

	/**
	 * An existing pair is updated with the new value.
	 *
	 * @return void
	 */
	public function testUpsertUpdatesAnExistingValue(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 5, 'dashboard_uuid' => 'd-1', 'field_id' => 4, 'value' => 'old']];

		$row = (new MetadataValueMapper($recorder->connection()))->upsert('d-1', 4, 'new');

		$this->assertSame(5, $row->getId());
		$this->assertSame('new', $row->getValue());
		$this->assertContains('qb2.update("launchpad_meta_values", null)', $recorder->calls);
		$this->assertContains('qb2.set("value", ":p3")', $recorder->calls);
		$this->assertSame([], preg_grep('/\.insert\(/', $recorder->calls));
	}//end testUpsertUpdatesAnExistingValue()

	/**
	 * A missing pair is inserted carrying the dashboard, field and value.
	 *
	 * @return void
	 */
	public function testUpsertInsertsAMissingValue(): void {
		$recorder = new QueryRecorder(test: $this);

		$row = (new MetadataValueMapper($recorder->connection()))->upsert('d-1', 4, 'new');

		$this->assertInstanceOf(MetadataValue::class, $row);
		$this->assertSame('d-1', $row->getDashboardUuid());
		$this->assertSame(4, $row->getFieldId());
		$this->assertSame('new', $row->getValue());
		$this->assertContains('qb2.insert("launchpad_meta_values")', $recorder->calls);
	}//end testUpsertInsertsAMissingValue()

	/**
	 * Deleting a dashboard's values returns the affected row count.
	 *
	 * @return void
	 */
	public function testDeleteByDashboardReturnsTheCount(): void {
		$recorder = new QueryRecorder(test: $this);

		$this->assertSame(1, (new MetadataValueMapper($recorder->connection()))->deleteByDashboard('d-1'));
		$this->assertContains('qb1.delete("launchpad_meta_values", null)', $recorder->calls);
		$this->assertContains('qb1.expr.eq("dashboard_uuid", ":p1", null)', $recorder->calls);
	}//end testDeleteByDashboardReturnsTheCount()
}//end class
