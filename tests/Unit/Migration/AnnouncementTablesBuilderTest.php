<?php

/**
 * AnnouncementTablesBuilderTest
 *
 * The migration creates both announcement tables with the columns the design
 * names, once (engagement-announcements task 1).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/engagement-announcements/tasks.md#task-1
 */

declare(strict_types=1);

namespace Unit\Migration;

use Closure;
use OCA\LaunchPad\Migration\AnnouncementTablesBuilder;
use OCA\LaunchPad\Migration\Version002013Date20261114000000;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Schema\IColumn;
use OCP\DB\Schema\ITable;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AnnouncementTablesBuilder and its migration step.
 *
 * @covers \OCA\LaunchPad\Migration\AnnouncementTablesBuilder
 * @covers \OCA\LaunchPad\Migration\Version002013Date20261114000000
 */
class AnnouncementTablesBuilderTest extends TestCase {

	/**
	 * A schema double recording the tables, columns and indexes created.
	 *
	 * @param list<string>                     $existing Tables that already exist.
	 * @param array<string, array<string, mixed>> $made     Filled per table.
	 *
	 * @return ISchemaWrapper
	 */
	private function schema(array $existing, array &$made): ISchemaWrapper {
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->willReturnCallback(static fn (string $name): bool => in_array($name, $existing, true));
		$schema->method('createTable')->willReturnCallback(function (string $name) use (&$made): ITable {
			$made[$name] = ['columns' => [], 'primary' => null, 'unique' => [], 'indexes' => []];
			$table = $this->createMock(ITable::class);
			$table->method('addColumn')->willReturnCallback(function (string $column, mixed $type, array $options = []) use (&$made, $name): IColumn {
				$made[$name]['columns'][$column] = ['type' => $type, 'notnull' => ($options['notnull'] ?? true)];
				return $this->createMock(IColumn::class);
			});
			$table->method('setPrimaryKey')->willReturnCallback(function (array $columns) use (&$made, $name, $table): ITable {
				$made[$name]['primary'] = $columns;
				return $table;
			});
			$table->method('addUniqueIndex')->willReturnCallback(function (array $columns, ?string $index = null) use (&$made, $name, $table): ITable {
				$made[$name]['unique'][$index] = $columns;
				return $table;
			});
			$table->method('addIndex')->willReturnCallback(function (array $columns, ?string $index = null) use (&$made, $name, $table): ITable {
				$made[$name]['indexes'][$index] = $columns;
				return $table;
			});
			return $table;
		});

		return $schema;
	}//end schema()

	/**
	 * Both tables are made with the design's columns and keys.
	 *
	 * @return void
	 */
	public function testBothTablesAreCreated(): void {
		$made = [];
		$schema = $this->schema([], $made);

		$step = new Version002013Date20261114000000();
		$returned = $step->changeSchema($this->createMock(IOutput::class), Closure::fromCallable(static fn () => $schema), []);

		$this->assertSame($schema, $returned);
		$this->assertSame(['launchpad_announcements', 'launchpad_ann_follows'], array_keys($made));
		$this->assertSame(
			['id', 'uuid', 'kind', 'title', 'body', 'category', 'level', 'dismissible', 'target_groups', 'status', 'publish_at', 'expires_at', 'allow_comments', 'author_id', 'notified_at', 'created_at', 'updated_at'],
			array_keys($made['launchpad_announcements']['columns'])
		);
		$this->assertFalse($made['launchpad_announcements']['columns']['expires_at']['notnull']);
		$this->assertSame(['uuid'], $made['launchpad_announcements']['unique']['launchpad_ann_uuid']);
		$this->assertSame(['id', 'user_id', 'category', 'created_at'], array_keys($made['launchpad_ann_follows']['columns']));
		$this->assertSame(['user_id', 'category'], $made['launchpad_ann_follows']['unique']['launchpad_ann_follow_uc']);
		$this->assertSame(['id'], $made['launchpad_ann_follows']['primary']);
	}//end testBothTablesAreCreated()

	/**
	 * Running again over existing tables changes nothing.
	 *
	 * @return void
	 */
	public function testExistingTablesAreLeftAlone(): void {
		$made = [];
		AnnouncementTablesBuilder::create($this->schema(['launchpad_announcements', 'launchpad_ann_follows'], $made));

		$this->assertSame([], $made);
	}//end testExistingTablesAreLeftAlone()
}//end class
