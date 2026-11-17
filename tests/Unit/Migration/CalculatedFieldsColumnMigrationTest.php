<?php

/**
 * CalculatedFieldsColumnMigrationTest
 *
 * The migration adds the nullable `calculated_fields` column to the widget
 * placements once, and leaves a schema without the table alone
 * (widget-calculated-fields, task 22).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-22
 */

declare(strict_types=1);

namespace Unit\Migration;

use OCA\LaunchPad\Migration\Version002016Date20261117000000;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Schema\IColumn;
use OCP\DB\Schema\ITable;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Version002016Date20261117000000.
 *
 * @covers \OCA\LaunchPad\Migration\Version002016Date20261117000000
 */
class CalculatedFieldsColumnMigrationTest extends TestCase {

	/**
	 * Run the step against a schema.
	 *
	 * @param ISchemaWrapper $schema The schema.
	 *
	 * @return ISchemaWrapper|null What the step answered.
	 */
	private function migrate(ISchemaWrapper $schema): ?ISchemaWrapper {
		return (new Version002016Date20261117000000())->changeSchema(
			$this->createMock(IOutput::class),
			static fn (): ISchemaWrapper => $schema,
			[]
		);
	}//end migrate()

	/**
	 * A table without the column gets it, nullable text, and the schema is answered.
	 *
	 * @return void
	 */
	public function testTheColumnIsAddedNullable(): void {
		$table = $this->createMock(ITable::class);
		$table->expects($this->once())->method('hasColumn')->with('calculated_fields')->willReturn(false);
		$table->expects($this->once())->method('addColumn')->with(
			'calculated_fields',
			Types::TEXT,
			$this->callback(static fn (array $options): bool => $options['notnull'] === false && $options['default'] === null)
		)->willReturn($this->createMock(IColumn::class));

		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->with('launchpad_widget_placements')->willReturn(true);
		$schema->method('getTable')->with('launchpad_widget_placements')->willReturn($table);

		$this->assertSame($schema, $this->migrate(schema: $schema));
	}//end testTheColumnIsAddedNullable()

	/**
	 * A second run adds nothing and changes nothing.
	 *
	 * @return void
	 */
	public function testAnExistingColumnIsLeftAlone(): void {
		$table = $this->createMock(ITable::class);
		$table->method('hasColumn')->willReturn(true);
		$table->expects($this->never())->method('addColumn');

		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->willReturn(true);
		$schema->method('getTable')->willReturn($table);

		$this->assertNull($this->migrate(schema: $schema));
	}//end testAnExistingColumnIsLeftAlone()

	/**
	 * Without the placements table there is nothing to change.
	 *
	 * @return void
	 */
	public function testAMissingTableChangesNothing(): void {
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->willReturn(false);
		$schema->expects($this->never())->method('getTable');

		$this->assertNull($this->migrate(schema: $schema));
	}//end testAMissingTableChangesNothing()
}//end class
