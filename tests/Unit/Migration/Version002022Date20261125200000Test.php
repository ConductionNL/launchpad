<?php

/**
 * Unit tests for Version002022Date20261125200000: the acknowledgement key is
 * widened to 64 characters, once.
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
 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Migration;

use OCA\LaunchPad\Migration\Version002022Date20261125200000;
use OCA\LaunchPad\Service\AnnouncementReadConfirmations;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Schema\IColumn;
use OCP\DB\Schema\ITable;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

/**
 * The acknowledgement key migration.
 */
class Version002022Date20261125200000Test extends TestCase {

	/**
	 * Run the migration over a schema with the given table and column length.
	 *
	 * @param bool     $hasTable Whether the acknowledgements table exists.
	 * @param int|null $length   The current column length; null for no column.
	 * @param array    $modified Receives the modifyColumn arguments.
	 *
	 * @return ISchemaWrapper|null
	 */
	private function migrate(bool $hasTable, ?int $length, array &$modified): ?ISchemaWrapper {
		$column = $this->createMock(IColumn::class);
		$column->method('getLength')->willReturn($length);

		$table = $this->createMock(ITable::class);
		$table->method('hasColumn')->with('announcement_key')->willReturn($length !== null);
		$table->method('getColumn')->with('announcement_key')->willReturn($column);
		$table->method('modifyColumn')->willReturnCallback(
			function (string $name, array $options) use (&$modified, $table) {
				$modified[] = [$name, $options];
				return $table;
			}
		);

		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->with('launchpad_acknowledgements')->willReturn($hasTable);
		$schema->method('getTable')->willReturn($table);

		return (new Version002022Date20261125200000())->changeSchema(
			$this->createMock(IOutput::class),
			static fn () => $schema,
			[]
		);
	}

	/**
	 * A 36-character key column is widened to 64.
	 *
	 * @return void
	 */
	public function testANarrowKeyIsWidened(): void {
		$modified = [];
		$this->assertNotNull($this->migrate(hasTable: true, length: 36, modified: $modified));
		$this->assertSame([['announcement_key', ['length' => 64]]], $modified);
	}

	/**
	 * A wide column, a missing column or a missing table changes nothing.
	 *
	 * @return void
	 */
	public function testNothingElseIsTouched(): void {
		$modified = [];
		$this->assertNull($this->migrate(hasTable: true, length: 64, modified: $modified));
		$this->assertNull($this->migrate(hasTable: true, length: null, modified: $modified));
		$this->assertNull($this->migrate(hasTable: false, length: 36, modified: $modified));
		$this->assertSame([], $modified);
	}

	/**
	 * The read confirmation's key fits the widened column.
	 *
	 * @return void
	 */
	public function testAReadConfirmationKeyFits(): void {
		$key = AnnouncementReadConfirmations::key(uuid: '3ba37a05-4f3a-43f3-bf2e-0dab0f3509b9');
		$this->assertGreaterThan(36, strlen($key));
		$this->assertLessThanOrEqual(Version002022Date20261125200000::KEY_LENGTH, strlen($key));
	}
}
