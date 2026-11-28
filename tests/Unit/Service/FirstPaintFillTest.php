<?php

/**
 * FirstPaintFillTest
 *
 * A dashboard that fills the height below the top bar (LpVandaag) in the
 * first-paint shell (first-paint-skeleton D3): the row tracks equal
 * nextcloud-vue's fillRowTracks on the shared fixture, the shell marks the
 * dashboard as filling and carries each frame's own cell and fit-row flag.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\FirstPaintShell;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the fill-height part of FirstPaintShell.
 *
 * @covers \OCA\LaunchPad\Service\FirstPaintShell
 * @uses   \OCA\LaunchPad\Service\FirstPaintLayout
 * @uses   \OCA\LaunchPad\Service\FirstPaintGridEngine
 */
class FirstPaintFillTest extends TestCase {

	/**
	 * The shared fixture's cases.
	 *
	 * @return array<string, array{0: list<list<int|bool>>, 1: list<string>}>
	 */
	public static function cases(): array {
		$fixture = json_decode((string) file_get_contents(__DIR__.'/../../fixtures/first-paint-fill-tracks.json'), true);
		$cases   = [];
		foreach ($fixture['cases'] as $case) {
			$cases[$case['name']] = [$case['cells'], $case['tracks']];
		}

		return $cases;
	}//end cases()

	/**
	 * The tracks equal the library's for every fixture case.
	 *
	 * @param list<list<int|bool>> $cells  The cells (x, y, w, h, fitRows).
	 * @param list<string>         $tracks The library's tracks.
	 *
	 * @return void
	 */
	#[DataProvider('cases')]
	public function testTracksEqualTheLibrary(array $cells, array $tracks): void {
		$frames = array_map(
			static fn (array $cell): array => ['cell' => ['x' => $cell[0], 'y' => $cell[1], 'w' => $cell[2], 'h' => $cell[3]], 'fitRows' => $cell[4]],
			$cells
		);
		$this->assertSame(expected: $tracks, actual: FirstPaintShell::fillRowTracks(frames: $frames));
	}//end testTracksEqualTheLibrary()

	/**
	 * A placement with `content.fillHeight` makes the shell fill; frames carry
	 * their own cell and fit-row flag; without it there are no tracks.
	 *
	 * @return void
	 */
	public function testTheShellFillsWhenAPlacementAsks(): void {
		$placements = [
			['id' => 1, 'widgetId' => 'header', 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 12, 'gridHeight' => 3, 'content' => ['ground' => true, 'fillHeight' => true, 'fitRows' => true]],
			['id' => 2, 'widgetId' => 'calendar', 'gridX' => 0, 'gridY' => 3, 'gridWidth' => 6, 'gridHeight' => 4],
		];
		$shell      = new FirstPaintShell();
		$model      = $shell->build(page: ['dashboard' => ['name' => 'Vandaag', 'gridColumns' => 12], 'placements' => $placements], catalogue: []);

		$this->assertTrue(condition: $model['fill']);
		$this->assertSame(expected: ['auto', 'auto', 'auto', 'minmax(0,1fr)', 'minmax(0,1fr)', 'minmax(0,1fr)', 'minmax(0,1fr)'], actual: $model['tracks']);
		$this->assertSame(expected: [true, false], actual: array_column($model['frames'], 'fitRows'));
		$this->assertSame(expected: ['x' => 0, 'y' => 3, 'w' => 6, 'h' => 4], actual: $model['frames'][1]['cell']);

		unset($placements[0]['content']['fillHeight']);
		$plain = $shell->build(page: ['dashboard' => ['name' => 'Vandaag', 'gridColumns' => 12], 'placements' => $placements], catalogue: []);
		$this->assertFalse(condition: $plain['fill']);
		$this->assertSame(expected: [], actual: $plain['tracks']);
	}//end testTheShellFillsWhenAPlacementAsks()
}//end class
