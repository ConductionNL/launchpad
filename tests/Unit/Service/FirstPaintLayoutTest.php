<?php

/**
 * FirstPaintLayoutTest
 *
 * The server's twin of the grid's column-change layout (first-paint-skeleton
 * D3) on the fixture src/utils/__tests__/narrowGridLayout.fixture.spec.js
 * reads too, and the column counts a dashboard shows.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\FirstPaintLayout;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for FirstPaintLayout.
 *
 * @covers \OCA\LaunchPad\Service\FirstPaintLayout
 */
class FirstPaintLayoutTest extends TestCase {

	/**
	 * The shared fixture's cases.
	 *
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public static function fixtureCases(): array {
		$fixture = json_decode((string) file_get_contents(__DIR__.'/../../fixtures/first-paint-layouts.json'), true);
		$cases   = [];
		foreach ($fixture['cases'] as $case) {
			$cases[$case['name']] = [$case];
		}

		return $cases;
	}//end fixtureCases()

	/**
	 * Every column count of every fixture case lays out as the grid does.
	 *
	 * @param array<string, mixed> $case The case.
	 *
	 * @return void
	 */
	#[DataProvider('fixtureCases')]
	public function testTheFixtureLayouts(array $case): void {
		$expected = [];
		foreach ($case['expected'] as $count => $positions) {
			$expected[(int) $count] = $positions;
		}

		$actual = FirstPaintLayout::positions(nodes: $case['nodes'], columns: $case['columns']);

		// Where each widget goes counts, not the order of the keys (the Vitest twin compares the same way).
		$this->assertSame(expected: self::byId(layout: $expected), actual: self::byId(layout: $actual));
	}//end testTheFixtureLayouts()

	/**
	 * A layout with every column count's widgets sorted by id.
	 *
	 * @param array<int, array<string, mixed>> $layout Column count => id => position.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function byId(array $layout): array {
		foreach ($layout as $count => $byId) {
			ksort($byId);
			$layout[$count] = $byId;
		}

		return $layout;
	}//end byId()

	/**
	 * The column counts: the dashboard's own, then 8 and 4 when they narrow it.
	 *
	 * @return void
	 */
	public function testColumnCounts(): void {
		$this->assertSame(expected: [12, 8, 4], actual: FirstPaintLayout::columnCounts(columns: 12));
		$this->assertSame(expected: [10, 8, 4], actual: FirstPaintLayout::columnCounts(columns: 10));
		$this->assertSame(expected: [8, 4], actual: FirstPaintLayout::columnCounts(columns: 8));
		$this->assertSame(expected: [6, 4], actual: FirstPaintLayout::columnCounts(columns: 6));
		$this->assertSame(expected: [4], actual: FirstPaintLayout::columnCounts(columns: 4));
		$this->assertSame(expected: [12, 8, 4], actual: FirstPaintLayout::columnCounts(columns: 0));
		$this->assertSame(expected: [6, 4], actual: array_keys((new FirstPaintLayout())->layout(nodes: [['id' => 'a', 'w' => 6]], columns: 6)));
	}//end testColumnCounts()

	/**
	 * On four columns tiles flow four to a row under their own heading, and
	 * a wider widget takes a full row.
	 *
	 * @return void
	 */
	public function testTilesStayUnderTheirHeading(): void {
		$nodes = [
			['id' => 'left', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 1],
			['id' => 'right', 'x' => 6, 'y' => 0, 'w' => 6, 'h' => 1],
			['id' => 'r1', 'x' => 6, 'y' => 1, 'w' => 1, 'h' => 2],
			['id' => 'l1', 'x' => 0, 'y' => 1, 'w' => 1, 'h' => 2],
		];

		$narrow = FirstPaintLayout::positions(nodes: $nodes, columns: 12)[4];

		$this->assertSame(expected: ['left', 'l1', 'right', 'r1'], actual: array_keys($narrow));
		$this->assertSame(expected: ['x' => 0, 'y' => 1, 'w' => 1, 'h' => 2], actual: $narrow['l1']);
		$this->assertSame(expected: ['x' => 0, 'y' => 3, 'w' => 4, 'h' => 1], actual: $narrow['right']);
	}//end testTilesStayUnderTheirHeading()
}//end class
