<?php

/**
 * DemoSeedBoardWidgetsTest
 *
 * The Zuiddrecht board seed places the new widgets in the shape their types
 * read: app tiles in one of the eight theme colours, and tile groups headed by
 * a group heading label.
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
	 * Every tile group heading is a group heading label.
	 *
	 * @return void
	 */
	public function testTileGroupsAreHeadedByGroupHeadingLabels(): void {
		$labels = self::placementsOf(type: 'label');
		$this->assertNotEmpty(actual: $labels);
		foreach ($labels as $label) {
			$this->assertTrue(condition: $label['content']['groupHeading'] ?? false, message: (string)$label['content']['text']);
		}
	}//end testTileGroupsAreHeadedByGroupHeadingLabels()

	/**
	 * The wiki widgets carry the wiki-pages content: a space and a list length.
	 *
	 * @return void
	 */
	public function testWikiWidgetsReadASpace(): void {
		$wikis = self::placementsOf(type: 'wiki-pages');
		$this->assertCount(expectedCount: 2, haystack: $wikis);
		foreach ($wikis as $wiki) {
			$this->assertSame(expected: ['space', 'limit'], actual: array_keys($wiki['content']));
			$this->assertNotSame(expected: '', actual: $wiki['content']['space']);
		}

		$this->assertSame(expected: [], actual: self::placementsOf(type: 'wiki'));
	}//end testWikiWidgetsReadASpace()
}//end class
