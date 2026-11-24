<?php

/**
 * ConversationColumnMigrationTest
 *
 * The migration adds the nullable `conversation_enabled` column to the dashboards
 * once, and leaves a schema without the table alone (REQ-LEAF-006).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/sharing-tab-embed-and-conversation/specs/dashboard-talk-leaf/spec.md#requirement-the-conversation-is-switched-on-from-the-sharing-tab-req-leaf-006
 */

declare(strict_types=1);

namespace Unit\Migration;

use OCA\LaunchPad\Migration\Version002019Date20261124180000;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Schema\IColumn;
use OCP\DB\Schema\ITable;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Version002019Date20261124180000.
 *
 * @covers \OCA\LaunchPad\Migration\Version002019Date20261124180000
 */
class ConversationColumnMigrationTest extends TestCase {

	/**
	 * Run the step against a schema.
	 *
	 * @param ISchemaWrapper $schema The schema.
	 *
	 * @return ISchemaWrapper|null What the step answered.
	 */
	private function migrate(ISchemaWrapper $schema): ?ISchemaWrapper {
		return (new Version002019Date20261124180000())->changeSchema(
			$this->createMock(originalClassName: IOutput::class),
			static fn (): ISchemaWrapper => $schema,
			[]
		);
	}//end migrate()

	/**
	 * A table without the column gets it, a nullable small integer, and the schema is answered.
	 *
	 * @return void
	 */
	public function testTheColumnIsAddedNullable(): void {
		$table = $this->createMock(originalClassName: ITable::class);
		$table->expects($this->once())->method('hasColumn')->with('conversation_enabled')->willReturn(false);
		$table->expects($this->once())->method('addColumn')->with(
			'conversation_enabled',
			Types::SMALLINT,
			$this->callback(callback: static fn (array $options): bool => $options['notnull'] === false && $options['default'] === null)
		)->willReturn($this->createMock(originalClassName: IColumn::class));

		$schema = $this->createMock(originalClassName: ISchemaWrapper::class);
		$schema->method('hasTable')->with('launchpad_dashboards')->willReturn(true);
		$schema->method('getTable')->with('launchpad_dashboards')->willReturn($table);

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
	 * Without the dashboards table there is nothing to change.
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
