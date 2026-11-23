<?php

/**
 * DemoSeedBoardWidgetsTest
 *
 * The Zuiddrecht board seed places the new widgets in the shape their types
 * read: app tiles in one of the eight theme colours, and widget groups headed
 * by a full-width section heading (Tussenkop).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/app-tile-widget/specs/app-tile-widget/spec.md
 */

declare(strict_types=1);

namespace Unit\Service;

use PHPUnit\Framework\TestCase;

/**
 * Checks the seed data against the widget types it places.
 */
class DemoSeedBoardWidgetsTest extends TestCase {
	/**
	 * The seed file.
	 *
	 * @var string
	 */
	private const SEED = __DIR__ . '/../../../data/demo-seed/zuiddrecht-boards.json';

	/**
	 * The app tile palette keys of nextcloud-vue's `APP_TILE_COLOURS`.
	 *
	 * @var array<int, string>
	 */
	private const TILE_COLOURS = ['primary', 'primary-deep', 'accent', 'accent-deep', 'work-ink', 'work-text', 'work-muted', 'work-field'];

	/**
	 * The seed.
	 *
	 * @return array<string, mixed>
	 */
	public static function seed(): array {
		return json_decode(json: (string)file_get_contents(self::SEED), associative: true);
	}//end seed()

	/**
	 * Every placement of a widget type, across the dashboards.
	 *
	 * @param string $type The widget type.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function placementsOf(string $type): array {
		$found = [];
		foreach (self::seed()['dashboards'] as $dashboard) {
			foreach ($dashboard['widgets'] as $widget) {
				if (($widget['widgetId'] ?? null) === $type) {
					$found[] = $widget;
				}
			}
		}

		return $found;
	}//end placementsOf()

	/**
	 * Every catalogue tile has an app tile colour.
	 *
	 * @return void
	 */
	public function testEveryTileHasAnAppTileColour(): void {
		foreach (self::seed()['tiles'] as $key => $tile) {
			$this->assertContains(needle: $tile['colour'], haystack: self::TILE_COLOURS, message: $key);
		}
	}//end testEveryTileHasAnAppTileColour()

	/**
	 * The section headings are the ones the boards draw, each full width with
	 * a title and one line of explanation, and no label heading is left.
	 *
	 * @return void
	 */
	public function testGroupsAreHeadedBySectionHeadings(): void {
		$expected = [
			'Mijn werkplek'        => ['Mijn apps', 'Samenwerken'],
			'Intranet Zuiddrecht'  => ['Samenwerken', 'Nieuws en media', 'Wegwijs'],
			'Team Vergunningen'    => ['Apps van het team'],
		];
		// LpWerkplek heads each group of tiles in its own column.
		$headingWidths = ['Mijn werkplek' => 4];
		foreach (self::seed()['dashboards'] as $dashboard) {
			$titles = [];
			foreach ($dashboard['widgets'] as $widget) {
				$this->assertNotSame(expected: 'label', actual: ($widget['widgetId'] ?? null), message: $dashboard['name']);
				if (($widget['widgetId'] ?? null) !== 'section-heading') {
					continue;
				}

				$titles[] = $widget['content']['title'];
				$width = ($headingWidths[$dashboard['name']] ?? $dashboard['gridColumns']);
				$this->assertSame(expected: $width, actual: $widget['gridWidth'], message: $widget['content']['title']);
				$this->assertNotSame(expected: '', actual: $widget['content']['description'], message: $widget['content']['title']);
			}

			$this->assertSame(expected: ($expected[$dashboard['name']] ?? []), actual: $titles, message: $dashboard['name']);
		}
	}//end testGroupsAreHeadedBySectionHeadings()

	/**
	 * No two sized placements of a dashboard share a grid cell, so the
	 * headings sit on rows of their own.
	 *
	 * @return void
	 */
	public function testSizedPlacementsDoNotOverlap(): void {
		foreach (self::seed()['dashboards'] as $dashboard) {
			$cells = [];
			foreach ($dashboard['widgets'] as $index => $widget) {
				$fallback = 0;
				if (isset($widget['tile']) === true) {
					$fallback = 2;
				}

				$width  = ($widget['gridWidth'] ?? $fallback);
				$height = ($widget['gridHeight'] ?? $fallback);
				for ($x = $widget['gridX']; $x < $widget['gridX'] + $width; $x++) {
					for ($y = $widget['gridY']; $y < $widget['gridY'] + $height; $y++) {
						$key = $x.':'.$y;
						$this->assertArrayNotHasKey(key: $key, array: $cells, message: $dashboard['name'].' placement '.$index);
						$cells[$key] = $index;
					}
				}
			}
		}
	}//end testSizedPlacementsDoNotOverlap()

	/**
	 * The start page puts its six app tiles straight on the grid, with no
	 * heading above them (board LpVandaag).
	 *
	 * @return void
	 */
	public function testTheStartPageTilesHaveNoHeading(): void {
		$today = array_values(array_filter(self::seed()['dashboards'], static fn (array $d): bool => $d['name'] === 'Vandaag'))[0];
		$tiles = array_filter($today['widgets'], static fn (array $w): bool => isset($w['tile']) === true);
		$this->assertCount(expectedCount: 6, haystack: $tiles);
		foreach ($today['widgets'] as $widget) {
			$this->assertNotContains(needle: ($widget['widgetId'] ?? null), haystack: ['label', 'section-heading']);
		}
	}//end testTheStartPageTilesHaveNoHeading()

	/**
	 * The wiki widgets carry the wiki-pages content: a space ('' reads every
	 * space, so the start dashboard lists the wiki's recent pages) and a list length.
	 *
	 * @return void
	 */
	public function testWikiWidgetsReadASpace(): void {
		$wikis = self::placementsOf(type: 'wiki-pages');
		$this->assertCount(expectedCount: 3, haystack: $wikis);
		foreach ($wikis as $wiki) {
			$this->assertSame(expected: ['space', 'limit'], actual: array_slice(array_keys($wiki['content']), 0, 2));
			$this->assertIsString(actual: $wiki['content']['space']);
		}

		$spaces = array_map(callback: static fn (array $wiki): string => $wiki['content']['space'], array: $wikis);
		$this->assertContains(needle: '', haystack: $spaces);

		$this->assertSame(expected: [], actual: self::placementsOf(type: 'wiki'));
	}//end testWikiWidgetsReadASpace()

	/**
	 * The work package widgets name their scope; a project list names its project.
	 *
	 * @return void
	 */
	public function testWorkPackageWidgetsNameTheirScope(): void {
		$widgets = self::placementsOf(type: 'work-packages');
		$this->assertCount(expectedCount: 4, haystack: $widgets);
		foreach ($widgets as $widget) {
			$this->assertSame(expected: ['scope', 'project', 'limit'], actual: array_slice(array_keys($widget['content']), 0, 3));
			$this->assertContains(needle: $widget['content']['scope'], haystack: ['mine', 'project']);
			if ($widget['content']['scope'] === 'project') {
				$this->assertSame(expected: 'havenpark', actual: $widget['content']['project']);
			}
		}
	}//end testWorkPackageWidgetsNameTheirScope()

	/**
	 * The case widgets read dossiq's register and schema.
	 *
	 * @return void
	 */
	public function testCaseWidgetsReadTheDossiqRegister(): void {
		$widgets = self::placementsOf(type: 'assigned-cases');
		$this->assertCount(expectedCount: 3, haystack: $widgets);
		foreach ($widgets as $widget) {
			$this->assertSame(expected: ['register' => 'dossiq', 'schema' => 'case', 'limit' => 4], actual: array_slice($widget['content'], 0, 3));
			$this->assertArrayNotHasKey(key: 'fallback', array: $widget);
		}

		$this->assertSame(expected: [], actual: self::placementsOf(type: 'my-cases'));
	}//end testCaseWidgetsReadTheDossiqRegister()

	/**
	 * The pie chart and the graph carry their entered values in the preset shape.
	 *
	 * @return void
	 */
	public function testChartsCarryTheirEnteredValues(): void {
		[$pie] = self::placementsOf(type: 'pie-chart');
		$this->assertSame(expected: 'manual', actual: $pie['content']['source']);
		$this->assertSame(expected: 312, actual: array_sum($pie['content']['series']));
		$this->assertCount(expectedCount: count($pie['content']['series']), haystack: $pie['content']['labels']);
		$this->assertTrue(condition: $pie['content']['showTotal']);

		[$graph] = self::placementsOf(type: 'graph');
		$this->assertSame(expected: 'manual', actual: $graph['content']['source']);
		$this->assertSame(expected: ['column', 'line'], actual: array_column($graph['content']['series'], 'type'));
		foreach ($graph['content']['series'] as $series) {
			$this->assertCount(expectedCount: count($graph['content']['categories']), haystack: $series['data']);
		}

		$this->assertArrayNotHasKey(key: 'fallback', array: $pie);
		$this->assertArrayNotHasKey(key: 'fallback', array: $graph);
	}//end testChartsCarryTheirEnteredValues()
}//end class
