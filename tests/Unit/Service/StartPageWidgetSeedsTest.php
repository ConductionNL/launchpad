<?php

/**
 * StartPageWidgetSeeds test
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

use OCA\LaunchPad\Service\StartPageWidgetSeeds;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

/**
 * The start page follows board LpVandaag and works on a plain Nextcloud.
 *
 * @covers \OCA\LaunchPad\Service\StartPageWidgetSeeds
 * @spec openspec/specs/default-widget-bundle/spec.md#requirement-req-dwb-008-the-start-page-is-the-vandaag-layout
 */
class StartPageWidgetSeedsTest extends TestCase {

	/**
	 * The strings the seeds asked to translate.
	 *
	 * @var array<int, string>
	 */
	private array $translated = [];

	/**
	 * The board's order: greeting and weather, today's attention, the working
	 * day, news and colleagues, then the apps and the brand tiles.
	 *
	 * @return void
	 */
	public function testTheStartPageFollowsTheBoard(): void {
		$seeds = $this->seeds();

		$this->assertSame(
			expected: [
				'header', 'weather', 'attention', 'section-heading', 'nc-widget', 'calendar', 'recent-work',
				'section-heading', 'announcements', 'people', 'section-heading', 'section-heading', 'container',
				'tile', 'tile', 'tile',
			],
			actual: array_column($seeds, 'widgetId')
		);
		$this->assertSame(expected: range(0, 15), actual: array_column($seeds, 'sortOrder'));
		$this->assertSame(expected: 'mail-unread', actual: $seeds[4]['content']['widgetId']);
		$this->assertSame(
			expected: ['Conduction', 'Sendent', 'Nextcloud'],
			actual: array_column(array_column(array_slice($seeds, 13), 'tile'), 'title')
		);
	}//end testTheStartPageFollowsTheBoard()

	/**
	 * Every placement sits inside the 12 columns and none overlaps another.
	 *
	 * @return void
	 */
	public function testPlacementsFitTheGridWithoutOverlap(): void {
		$cells = [];
		foreach ($this->seeds() as $index => $seed) {
			$this->assertGreaterThanOrEqual(expected: 0, actual: $seed['gridX']);
			$this->assertLessThanOrEqual(expected: 12, actual: ($seed['gridX'] + $seed['gridWidth']));
			for ($x = $seed['gridX']; $x < $seed['gridX'] + $seed['gridWidth']; $x++) {
				for ($y = $seed['gridY']; $y < $seed['gridY'] + $seed['gridHeight']; $y++) {
					$this->assertArrayNotHasKey(key: $x.':'.$y, array: $cells, message: 'seed '.$index.' overlaps');
					$cells[$x.':'.$y] = true;
				}
			}
		}
	}//end testPlacementsFitTheGridWithoutOverlap()

	/**
	 * No seed names a register, a wiki space or an organisation's own text,
	 * and every visible title is translated.
	 *
	 * @return void
	 */
	public function testNothingIsDemoContentAndEveryTitleIsTranslated(): void {
		$seeds = $this->seeds();
		$encoded = (string)json_encode($seeds);
		$this->assertStringNotContainsString(needle: 'register', haystack: $encoded);
		$this->assertStringNotContainsString(needle: 'dossiq', haystack: $encoded);
		$this->assertStringNotContainsString(needle: 'Zuiddrecht', haystack: $encoded);

		foreach ($seeds as $seed) {
			if (isset($seed['customTitle']) === true) {
				$this->assertSame(expected: 1, actual: $seed['showTitle']);
				$this->assertContains(needle: $seed['customTitle'], haystack: $this->translated);
			}

			if ($seed['widgetId'] === 'section-heading') {
				$this->assertSame(expected: 0, actual: $seed['showTitle']);
				$this->assertContains(needle: $seed['content']['title'], haystack: $this->translated);
				$this->assertContains(needle: $seed['content']['description'], haystack: $this->translated);
			}
		}
	}//end testNothingIsDemoContentAndEveryTitleIsTranslated()

	/**
	 * Without brand tiles the start page still seeds, just without them.
	 *
	 * @return void
	 */
	public function testWithoutBrandTilesTheRestStays(): void {
		$seeds = StartPageWidgetSeeds::seeds(l10n: $this->l10n(), brandTiles: [['widgetId' => 'files', 'tile' => null]]);

		$this->assertCount(expectedCount: 13, haystack: $seeds);
		$this->assertNotContains(needle: 'tile', haystack: array_column($seeds, 'widgetId'));
	}//end testWithoutBrandTilesTheRestStays()

	/**
	 * The seeds with the three brand tiles of the default bundle.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function seeds(): array {
		$brands = [];
		foreach (['Conduction', 'Sendent', 'Nextcloud'] as $title) {
			$brands[] = ['widgetId' => 'tile', 'tile' => ['title' => $title]];
		}

		$brands[] = ['widgetId' => 'files', 'tile' => null];

		return StartPageWidgetSeeds::seeds(l10n: $this->l10n(), brandTiles: $brands);
	}//end seeds()

	/**
	 * An IL10N that records what it translates and answers unchanged.
	 *
	 * @return IL10N
	 */
	private function l10n(): IL10N {
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnCallback(function (string $text): string {
			$this->translated[] = $text;
			return $text;
		});

		return $l10n;
	}//end l10n()
}//end class
