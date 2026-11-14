<?php

/**
 * Unit tests for the default_link_target migration.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Migration
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Migration;

use OCP\DB\Schema\IColumn;
use OCP\DB\Schema\ITable;
use OCA\LaunchPad\Migration\Version002013Date20261114090000;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Migration\Version002013Date20261114090000
 *
 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
 */
class Version002013Date20261114090000Test extends TestCase {

	/**
	 * Run the migration over a schema whose dashboards table has or lacks the column.
	 *
	 * @param bool  $hasTable  Whether the dashboards table exists.
	 * @param bool  $hasColumn Whether the column exists already.
	 * @param array $added     Receives the addColumn arguments.
	 *
	 * @return ISchemaWrapper|null
	 */
	private function migrate(bool $hasTable, bool $hasColumn, array &$added): ?ISchemaWrapper {
		$table = $this->createMock(ITable::class);
		$table->method('hasColumn')->with('default_link_target')->willReturn($hasColumn);
		$table->method('addColumn')->willReturnCallback(
			function (string $name, $type, array $options) use (&$added) {
				$added[] = [$name, $type, $options];
				return $this->createMock(IColumn::class);
			}
		);

		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->with('launchpad_dashboards')->willReturn($hasTable);
		$schema->method('getTable')->willReturn($table);

		return (new Version002013Date20261114090000())->changeSchema(
			$this->createMock(IOutput::class),
			static fn () => $schema,
			[]
		);
	}

	/**
	 * The column is added nullable, without a default value, and only once.
	 *
	 * @return void
	 */
	public function testTheNullableColumnIsAddedOnce(): void {
		$added = [];
		$this->assertNotNull($this->migrate(hasTable: true, hasColumn: false, added: $added));
		$this->assertSame(
			[['default_link_target', Types::STRING, ['notnull' => false, 'length' => 16, 'default' => null, 'comment' => 'same-tab, new-tab or side-panel; NULL follows the link type.']]],
			$added
		);

		$again = [];
		$this->assertNull($this->migrate(hasTable: true, hasColumn: true, added: $again));
		$this->assertNull($this->migrate(hasTable: false, hasColumn: false, added: $again));
		$this->assertSame([], $again);
	}
}
