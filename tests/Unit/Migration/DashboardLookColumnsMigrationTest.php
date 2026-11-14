<?php

/**
 * Version002014Date20261114120000 test
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Migration
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-each-dashboard-can-have-its-own-look-req-thm-001
 */

declare(strict_types=1);

namespace Unit\Migration;

use OCA\LaunchPad\Migration\Version002014Date20261114120000;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

class DashboardLookColumnsMigrationTest extends TestCase {

	public function testTheFourLookColumnsAreNullable(): void {
		$columns = Version002014Date20261114120000::COLUMNS;

		$this->assertSame(['theme_accent', 'theme_background_color', 'theme_background_resource', 'custom_css'], array_keys($columns));
		$this->assertSame([Types::STRING, Types::STRING, Types::STRING, Types::TEXT], array_column($columns, 0));
		foreach ($columns as $name => [, $options]) {
			$this->assertFalse($options['notnull'], $name . ' is nullable: empty follows the theme');
		}

		$this->assertSame(7, $columns['theme_accent'][1]['length'], '#rrggbb');
		$this->assertSame(7, $columns['theme_background_color'][1]['length']);
	}

	public function testNoTableNoChange(): void {
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->with('launchpad_dashboards')->willReturn(false);
		$schema->expects($this->never())->method('getTable');

		$this->assertNull((new Version002014Date20261114120000())->changeSchema($this->createMock(IOutput::class), fn () => $schema, []));
	}
}
