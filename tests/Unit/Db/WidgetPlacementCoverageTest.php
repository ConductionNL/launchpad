<?php

/**
 * WidgetPlacementCoverageTest
 *
 * Unit tests for the column aliasing of {@see WidgetPlacement}: the
 * acknowledgement content version property is stored in the shortened
 * `ack_content_version` column (Nextcloud's 30-character identifier limit),
 * in both directions, while every other property keeps the derived name.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Db
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Db;

use OCA\LaunchPad\Db\WidgetPlacement;
use PHPUnit\Framework\TestCase;

/**
 * Column alias tests for the placement entity.
 *
 * @covers \OCA\LaunchPad\Db\WidgetPlacement
 */
class WidgetPlacementCoverageTest extends TestCase {

	/**
	 * The aliased property maps to the shortened column.
	 *
	 * @return void
	 */
	public function testPropertyToColumnUsesAlias(): void {
		$column = (new WidgetPlacement())->propertyToColumn('acknowledgementContentVersion');

		$this->assertSame('ack_content_version', $column);
		$this->assertLessThanOrEqual(30, strlen($column));
	}//end testPropertyToColumnUsesAlias()

	/**
	 * Other properties keep the derived snake_case column.
	 *
	 * @return void
	 */
	public function testPropertyToColumnFallsBackToDerivedName(): void {
		$placement = new WidgetPlacement();

		$this->assertSame('grid_width', $placement->propertyToColumn('gridWidth'));
		$this->assertSame('requires_acknowledgement', $placement->propertyToColumn('requiresAcknowledgement'));
	}//end testPropertyToColumnFallsBackToDerivedName()

	/**
	 * The shortened column maps back to the aliased property.
	 *
	 * @return void
	 */
	public function testColumnToPropertyUsesAlias(): void {
		$this->assertSame('acknowledgementContentVersion', (new WidgetPlacement())->columnToProperty('ack_content_version'));
	}//end testColumnToPropertyUsesAlias()

	/**
	 * Other columns map to the derived camelCase property, including the
	 * long, unaliased spelling.
	 *
	 * @return void
	 */
	public function testColumnToPropertyFallsBackToDerivedName(): void {
		$placement = new WidgetPlacement();

		$this->assertSame('tileBackgroundColor', $placement->columnToProperty('tile_background_color'));
		$this->assertSame('acknowledgementContentVersion', $placement->columnToProperty('acknowledgement_content_version'));
	}//end testColumnToPropertyFallsBackToDerivedName()

	/**
	 * A row with the shortened column hydrates the aliased property.
	 *
	 * @return void
	 */
	public function testFromRowHydratesAliasedColumn(): void {
		$placement = WidgetPlacement::fromRow(['id' => '3', 'ack_content_version' => '7', 'grid_x' => '2']);

		$this->assertSame(7, $placement->getAcknowledgementContentVersion());
		$this->assertSame(2, $placement->getGridX());
		$this->assertSame(7, $placement->jsonSerialize()['acknowledgementContentVersion']);
	}//end testFromRowHydratesAliasedColumn()
}//end class
