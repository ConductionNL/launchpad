<?php

/**
 * Tile click upsert golden test
 *
 * Pins the row that upsertClick writes: the increment of an existing
 * bucket row, and the new row (negative deltas floored at zero) when the
 * bucket has none, against a recorded fixture.
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
use OCA\LaunchPad\Db\TileClickMapper;
use PHPUnit\Framework\TestCase;

/**
 * The written rows, case by case.
 *
 * @covers \OCA\LaunchPad\Db\TileClickMapper
 * @uses   \OCA\LaunchPad\Db\TileClick
 */
class TileClickUpsertGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/tile-click-upsert.json';

	/**
	 * Run one upsert with the given click.
	 *
	 * @param TileClickMapper      $mapper The mapper.
	 * @param array<string, mixed> $click  The click.
	 *
	 * @return TileClick
	 */
	private static function upsert(TileClickMapper $mapper, array $click): TileClick {
		return $mapper->upsertClick(click: $click);
	}//end upsert()

	/**
	 * Every case writes the recorded row.
	 *
	 * @return void
	 */
	public function testRowsMatchTheRecordedFixture(): void {
		$click = static fn (int $clicks, int $unique): array => [
			'placementUuid'    => 'p-1',
			'dashboardUuid'    => 'd-1',
			'clickBucket'      => '2026-11-03',
			'clickCountDelta'  => $clicks,
			'uniqueCountDelta' => $unique,
		];
		$cases = [
			'new row'               => [$click(1, 1), null],
			'new row repeat actor'  => [$click(1, 0), null],
			'new row negative'      => [$click(-2, -1), null],
			'existing row'          => [$click(1, 1), [5, 2]],
			'existing repeat actor' => [$click(1, 0), [5, 2]],
			'existing negative'     => [$click(-2, -1), [5, 2]],
		];

		$actual = [];
		foreach ($cases as $name => [$args, $existing]) {
			$calls  = [];
			$mapper = $this->getMockBuilder(TileClickMapper::class)
				->disableOriginalConstructor()
				->onlyMethods(['findByPlacementAndBucket', 'insert', 'update'])
				->getMock();
			$mapper->method('findByPlacementAndBucket')->willReturnCallback(
				static function (string $placementUuid, string $clickBucket) use ($existing, &$calls): ?TileClick {
					$calls[] = ['find', $placementUuid, $clickBucket];
					if ($existing === null) {
						return null;
					}
					$row = new TileClick();
					$row->setPlacementUuid('p-1');
					$row->setDashboardUuid('d-old');
					$row->setClickBucket('2026-11-03');
					$row->setClickCount($existing[0]);
					$row->setUniqueActorCount($existing[1]);
					return $row;
				}
			);
			$record = static function (string $op) use (&$calls): \Closure {
				return static function (TileClick $row) use ($op, &$calls): TileClick {
					$calls[] = [$op, $row->getPlacementUuid(), $row->getDashboardUuid(), $row->getClickBucket(), $row->getClickCount(), $row->getUniqueActorCount()];
					return $row;
				};
			};
			$mapper->method('insert')->willReturnCallback($record('insert'));
			$mapper->method('update')->willReturnCallback($record('update'));

			$returned      = self::upsert($mapper, $args);
			$actual[$name] = ['calls' => $calls, 'returned' => [$returned->getClickCount(), $returned->getUniqueActorCount()]];
		}

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
			$this->markTestIncomplete('Fixture recorded.');
		}

		$this->assertSame(json_decode((string) file_get_contents(self::FIXTURE), true), $actual);
	}//end testRowsMatchTheRecordedFixture()
}//end class
