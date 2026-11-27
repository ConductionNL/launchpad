<?php

/**
 * Unit tests for Version002024Date20261127160000: the receipts table and the
 * reminder column are added once; an existing table or column is left alone;
 * without the old acknowledgements table nothing is carried over.
 *
 * @category Test
 * @package  Unit\Migration
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/announcements-managed-in-settings/tasks.md#task-6
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Migration;

use OCA\LaunchPad\Migration\Version002024Date20261127160000;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Schema\ITable;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

/**
 * The receipts migration.
 */
class Version002024Date20261127160000Test extends TestCase {

	/**
	 * Run changeSchema over a schema.
	 *
	 * @param bool  $hasReceipts Whether the receipts table exists.
	 * @param bool  $hasColumn   Whether reminded_at exists.
	 * @param array $calls       Receives the schema calls.
	 *
	 * @return void
	 */
	private function migrate(bool $hasReceipts, bool $hasColumn, array &$calls): void {
		$receipts = $this->createMock(originalClassName: ITable::class);
		$receipts->method('addColumn')->willReturnCallback(
			function (string $name) use (&$calls) {
				$calls[] = 'column '.$name;
				return $this->createMock(originalClassName: \OCP\DB\Schema\IColumn::class);
			}
		);
		$receipts->method('addUniqueIndex')->willReturnCallback(
			function (array $columns, string $name) use (&$calls, $receipts) {
				$calls[] = 'unique '.implode(',', $columns);
				return $receipts;
			}
		);
		$receipts->method('addIndex')->willReturnSelf();
		$receipts->method('setPrimaryKey')->willReturnSelf();

		$announcements = $this->createMock(originalClassName: ITable::class);
		$announcements->method('hasColumn')->with('reminded_at')->willReturn($hasColumn);
		$announcements->method('addColumn')->willReturnCallback(
			function (string $name) use (&$calls) {
				$calls[] = 'announcements '.$name;
				return $this->createMock(originalClassName: \OCP\DB\Schema\IColumn::class);
			}
		);

		$schema = $this->createMock(originalClassName: ISchemaWrapper::class);
		$schema->method('hasTable')->willReturnCallback(
			static fn (string $name): bool => $name === 'launchpad_announcements' || $hasReceipts
		);
		$schema->method('createTable')->with('launchpad_ann_receipts')->willReturn($receipts);
		$schema->method('getTable')->with('launchpad_announcements')->willReturn($announcements);

		$migration = new Version002024Date20261127160000(db: $this->createMock(originalClassName: IDBConnection::class));
		$this->assertSame(
			expected: $schema,
			actual: $migration->changeSchema($this->createMock(originalClassName: IOutput::class), static fn () => $schema, [])
		);
	}//end migrate()

	/**
	 * A fresh install gets the table, its unique index and the column.
	 *
	 * @return void
	 */
	public function testAddsTheTableAndTheColumn(): void {
		$calls = [];
		$this->migrate(hasReceipts: false, hasColumn: false, calls: $calls);

		$this->assertContains(needle: 'column confirmed_via', haystack: $calls);
		$this->assertContains(needle: 'unique announcement_uuid,user_id', haystack: $calls);
		$this->assertContains(needle: 'announcements reminded_at', haystack: $calls);
	}//end testAddsTheTableAndTheColumn()

	/**
	 * Run again, it adds nothing.
	 *
	 * @return void
	 */
	public function testRunAgainAddsNothing(): void {
		$calls = [];
		$this->migrate(hasReceipts: true, hasColumn: true, calls: $calls);

		$this->assertSame(expected: [], actual: $calls);
	}//end testRunAgainAddsNothing()

	/**
	 * Without the acknowledgements table nothing is read or carried over.
	 *
	 * @return void
	 */
	public function testNoCarryOverWithoutTheOldTable(): void {
		$db = $this->createMock(originalClassName: IDBConnection::class);
		$db->method('tableExists')->willReturn(false);
		$db->expects($this->never())->method('getQueryBuilder');

		(new Version002024Date20261127160000(db: $db))->postSchemaChange(
			$this->createMock(originalClassName: IOutput::class),
			static fn () => null,
			[]
		);
	}//end testNoCarryOverWithoutTheOldTable()
}//end class
