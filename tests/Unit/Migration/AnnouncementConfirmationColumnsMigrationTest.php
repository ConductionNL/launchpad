<?php

/**
 * AnnouncementConfirmationColumnsMigrationTest
 *
 * The migration adds `requires_confirmation` and `confirm_before` to the
 * announcements once, and leaves a schema without the table alone
 * (REQ-ANN-007).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 */

declare(strict_types=1);

namespace Unit\Migration;

use OCA\LaunchPad\Migration\Version002021Date20261124210000;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Schema\IColumn;
use OCP\DB\Schema\ITable;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Version002021Date20261124210000.
 *
 * @covers \OCA\LaunchPad\Migration\Version002021Date20261124210000
 */
class AnnouncementConfirmationColumnsMigrationTest extends TestCase {

	/**
	 * Run the step against a schema.
	 *
	 * @param ISchemaWrapper $schema The schema.
	 *
	 * @return ISchemaWrapper|null What the step answered.
	 */
	private function migrate(ISchemaWrapper $schema): ?ISchemaWrapper {
		return (new Version002021Date20261124210000())->changeSchema(
			$this->createMock(originalClassName: IOutput::class),
			static fn (): ISchemaWrapper => $schema,
			[]
		);
	}//end migrate()

	/**
	 * A schema whose announcements table answers hasColumn as given.
	 *
	 * @param ITable $table The table.
	 *
	 * @return ISchemaWrapper The schema.
	 */
	private function schemaWith(ITable $table): ISchemaWrapper {
		$schema = $this->createMock(originalClassName: ISchemaWrapper::class);
		$schema->method('hasTable')->with('launchpad_announcements')->willReturn(true);
		$schema->method('getTable')->with('launchpad_announcements')->willReturn($table);

		return $schema;
	}//end schemaWith()

	/**
	 * A table without the columns gets both: a small integer defaulting to 0 and a nullable date.
	 *
	 * @return void
	 */
	public function testBothColumnsAreAdded(): void {
		$added = [];
		$table = $this->createMock(originalClassName: ITable::class);
		$table->method('hasColumn')->willReturn(false);
		$table->expects($this->exactly(count: 2))->method('addColumn')->willReturnCallback(
			function (string $name, string $type, array $options) use (&$added): IColumn {
				$added[$name] = [$type, $options];
				return $this->createMock(originalClassName: IColumn::class);
			}
		);

		$schema = $this->schemaWith(table: $table);

		$this->assertSame(expected: $schema, actual: $this->migrate(schema: $schema));
		$this->assertSame(expected: Types::SMALLINT, actual: $added['requires_confirmation'][0]);
		$this->assertSame(expected: 0, actual: $added['requires_confirmation'][1]['default']);
		$this->assertSame(expected: Types::DATETIME, actual: $added['confirm_before'][0]);
		$this->assertFalse(condition: $added['confirm_before'][1]['notnull']);
	}//end testBothColumnsAreAdded()

	/**
	 * A second run adds nothing and changes nothing.
	 *
	 * @return void
	 */
	public function testExistingColumnsAreLeftAlone(): void {
		$table = $this->createMock(originalClassName: ITable::class);
		$table->method('hasColumn')->willReturn(true);
		$table->expects($this->never())->method('addColumn');

		$this->assertNull(actual: $this->migrate(schema: $this->schemaWith(table: $table)));
	}//end testExistingColumnsAreLeftAlone()

	/**
	 * Without the announcements table there is nothing to change.
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
