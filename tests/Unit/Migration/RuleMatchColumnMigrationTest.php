<?php

/**
 * RuleMatchColumnMigrationTest
 *
 * The migration adds the `rule_match` column, default `any`, to the widget
 * placements once, and leaves a schema without the table alone (REQ-VIS-014).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
 */

declare(strict_types=1);

namespace Unit\Migration;

use OCA\LaunchPad\Migration\Version002018Date20261124000000;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Schema\IColumn;
use OCP\DB\Schema\ITable;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Version002018Date20261124000000.
 *
 * @covers \OCA\LaunchPad\Migration\Version002018Date20261124000000
 */
class RuleMatchColumnMigrationTest extends TestCase {

	/**
	 * Run the step against a schema.
	 *
	 * @param ISchemaWrapper $schema The schema.
	 *
	 * @return ISchemaWrapper|null What the step answered.
	 */
	private function migrate(ISchemaWrapper $schema): ?ISchemaWrapper {
		return (new Version002018Date20261124000000())->changeSchema(
			$this->createMock(originalClassName: IOutput::class),
			static fn (): ISchemaWrapper => $schema,
			[]
		);
	}//end migrate()

	/**
	 * A table without the column gets it, a string defaulting to `any`, and the schema is answered.
	 *
	 * @return void
	 */
	public function testTheColumnIsAddedNullable(): void {
		$table = $this->createMock(originalClassName: ITable::class);
		$table->expects($this->once())->method('hasColumn')->with('rule_match')->willReturn(false);
		$table->expects($this->once())->method('addColumn')->with(
			'rule_match',
			Types::STRING,
			$this->callback(callback: static fn (array $options): bool => $options['default'] === 'any' && $options['length'] === 8)
		)->willReturn($this->createMock(originalClassName: IColumn::class));

		$schema = $this->createMock(originalClassName: ISchemaWrapper::class);
		$schema->method('hasTable')->with('launchpad_widget_placements')->willReturn(true);
		$schema->method('getTable')->with('launchpad_widget_placements')->willReturn($table);

		$this->assertSame(expected: $schema, actual: $this->migrate(schema: $schema));
	}//end testTheColumnIsAddedNullable()

	/**
	 * A second run adds nothing and changes nothing.
	 *
	 * @return void
	 */
	public function testAnExistingColumnIsLeftAlone(): void {
		$table = $this->createMock(originalClassName: ITable::class);
		$table->method('hasColumn')->willReturn(true);
		$table->expects($this->never())->method('addColumn');

		$schema = $this->createMock(originalClassName: ISchemaWrapper::class);
		$schema->method('hasTable')->willReturn(true);
		$schema->method('getTable')->willReturn($table);

		$this->assertNull(actual: $this->migrate(schema: $schema));
	}//end testAnExistingColumnIsLeftAlone()

	/**
	 * Without the placements table there is nothing to change.
	 *
	 * @return void
	 */
	public function testAMissingTableChangesNothing(): void {
		$schema = $this->createMock(originalClassName: ISchemaWrapper::class);
		$schema->method('hasTable')->willReturn(false);
		$schema->expects($this->never())->method('getTable');

		$this->assertNull(actual: $this->migrate(schema: $schema));
	}//end testAMissingTableChangesNothing()
}//end class
