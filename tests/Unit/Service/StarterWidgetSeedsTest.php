<?php

/**
 * StarterWidgetSeeds Test.
 *
 * Covers which apps the first-run starter set gives a tile.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\StarterWidgetSeeds;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the starter seeds.
 */
class StarterWidgetSeedsTest extends TestCase {
	/**
	 * The app ids of the app tiles in a seed list.
	 *
	 * @param array<int, array<string, mixed>> $seeds The seeds.
	 *
	 * @return array<int, string>
	 */
	private static function tileApps(array $seeds): array {
		$apps = [];
		foreach ($seeds as $seed) {
			if ($seed['widgetId'] === 'tile' && ($seed['content']['mode'] ?? null) === 'app') {
				$apps[] = $seed['content']['appId'];
			}
		}

		return $apps;
	}//end tileApps()

	/**
	 * The most-used apps come first, in their own order.
	 *
	 * @return void
	 */
	public function testMostUsedAppsComeFirst(): void {
		$seeds = StarterWidgetSeeds::seeds(menuAppIds: ['photos', 'mail', 'deck', 'files']);
		$this->assertSame(expected: ['files', 'mail', 'deck', 'photos'], actual: self::tileApps(seeds: $seeds));
	}//end testMostUsedAppsComeFirst()

	/**
	 * Other menu apps fill the row up to four tiles; launchpad and duplicates never get one.
	 *
	 * @return void
	 */
	public function testOtherAppsFillTheRowUpToFour(): void {
		$seeds = StarterWidgetSeeds::seeds(menuAppIds: ['launchpad', 'photos', 'photos', 'forms', '', 'tables', 'collectives']);
		$this->assertSame(expected: ['photos', 'forms', 'tables', 'collectives'], actual: self::tileApps(seeds: $seeds));
	}//end testOtherAppsFillTheRowUpToFour()

	/**
	 * Without an app menu the apps are one app grid.
	 *
	 * @return void
	 */
	public function testNoMenuFallsBackToTheAppGrid(): void {
		$seeds = StarterWidgetSeeds::seeds(menuAppIds: []);
		// Layout-widgets-replace-multi-tile D3: a well that lists each viewer's apps.
		$this->assertSame(expected: 'container', actual: $seeds[0]['widgetId']);
		$this->assertSame(expected: ['launchpad'], actual: $seeds[0]['content']['appList']['exclude']);
		$this->assertSame(expected: [], actual: self::tileApps(seeds: $seeds));
		$this->assertCount(expectedCount: 6, haystack: $seeds);
	}//end testNoMenuFallsBackToTheAppGrid()

	/**
	 * Every seed fits the 12-column grid and the tiles share the top row.
	 *
	 * @return void
	 */
	public function testSeedsFitTheGrid(): void {
		$seeds = StarterWidgetSeeds::seeds(menuAppIds: ['files', 'mail', 'calendar', 'spreed', 'deck']);
		foreach ($seeds as $order => $seed) {
			$this->assertLessThanOrEqual(expected: 12, actual: $seed['gridX'] + $seed['gridWidth']);
			$this->assertSame(expected: $order, actual: $seed['sortOrder']);
		}
		$this->assertSame(expected: ['files', 'mail', 'calendar', 'spreed'], actual: self::tileApps(seeds: $seeds));
	}//end testSeedsFitTheGrid()
}//end class
