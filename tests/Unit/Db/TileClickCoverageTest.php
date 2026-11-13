<?php

/**
 * Tile click coverage test
 *
 * Pins the JSON shape of a tile click aggregate row and the integer
 * typing of its counters when hydrated from a database row.
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

use OCA\LaunchPad\Db\TileClick;
use PHPUnit\Framework\TestCase;

/**
 * The serialized shape of a tile click row.
 *
 * @covers \OCA\LaunchPad\Db\TileClick
 */
class TileClickCoverageTest extends TestCase {

	/**
	 * A row hydrated from the database serializes with typed counters
	 * under the frontend key names.
	 *
	 * @return void
	 */
	public function testJsonSerializeOfAHydratedRow(): void {
		$row = TileClick::fromRow(
			[
				'id'                 => '12',
				'placement_uuid'     => 'p-1',
				'dashboard_uuid'     => 'd-1',
				'click_bucket'       => '2026-11-03',
				'click_count'        => '8',
				'unique_actor_count' => '3',
			]
		);

		$this->assertSame(
			[
				'id'               => 12,
				'placementUuid'    => 'p-1',
				'dashboardUuid'    => 'd-1',
				'clickBucket'      => '2026-11-03',
				'clickCount'       => 8,
				'uniqueActorCount' => 3,
			],
			$row->jsonSerialize()
		);
		$this->assertSame(json_encode($row->jsonSerialize()), json_encode($row));
	}//end testJsonSerializeOfAHydratedRow()

	/**
	 * A fresh row serializes its unset strings as null, its untouched
	 * counter as zero and the values set through the setters.
	 *
	 * @return void
	 */
	public function testJsonSerializeOfANewRow(): void {
		$row = new TileClick();
		$row->setPlacementUuid('p-2');
		$row->setClickCount(1);

		$this->assertSame(
			[
				'id'               => null,
				'placementUuid'    => 'p-2',
				'dashboardUuid'    => null,
				'clickBucket'      => null,
				'clickCount'       => 1,
				'uniqueActorCount' => 0,
			],
			$row->jsonSerialize()
		);
	}//end testJsonSerializeOfANewRow()
}//end class
