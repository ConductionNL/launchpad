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
			'Vandaag'              => ['Vandaag aan het werk', 'Kennis en nieuws', "Collega's", 'Wegwijs', 'Mijn apps', 'Samenwerken'],
			'Intranet Zuiddrecht'  => ['Samenwerken', 'Nieuws en media', "Collega's", 'Wegwijs'],
			'Team Vergunningen'    => ['Apps van het team'],
		];
		// LpVandaag heads its two groups of tiles side by side, each half the width.
		$headingWidths = ['Vandaag' => ['Mijn apps' => 6, 'Samenwerken' => 6]];
		foreach (self::seed()['dashboards'] as $dashboard) {
			$titles = [];
			foreach ($dashboard['widgets'] as $widget) {
				$this->assertNotSame(expected: 'label', actual: ($widget['widgetId'] ?? null), message: $dashboard['name']);
				if (($widget['widgetId'] ?? null) !== 'section-heading') {
					continue;
				}

				$titles[] = $widget['content']['title'];
				$width = ($headingWidths[$dashboard['name']][$widget['content']['title']] ?? $dashboard['gridColumns']);
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
	 * The start page is the one page of the working day (board LpVandaag):
	 * the greeting with the counters and the weather, then the work of today,
	 * then knowledge and the intranet, then the app tiles. It scrolls, so no
	 * placement asks it to fill the screen, and My workspace repeats none of
	 * its widgets.
	 *
	 * @return void
	 */
	public function testTheStartPageHoldsTheWholeWorkingDay(): void {
		$boards = array_column(array: self::seed()['dashboards'], column_key: null, index_key: 'key');
		$types  = array_map(callback: static fn (array $w): string => ($w['widgetId'] ?? 'tile'), array: $boards['vandaag']['widgets']);
		$expected = [
			'header', 'weather', 'attention', 'nc-widget', 'calendar', 'assigned-cases',
			'work-packages', 'wiki-pages', 'announcements', 'people', 'menu', 'links',
		];
		foreach ($expected as $type) {
			$this->assertContains(needle: $type, haystack: $types);
		}

		$this->assertCount(expectedCount: 12, haystack: array_keys(array: $types, filter_value: 'tile'));
		$order = [];
		foreach (['header', 'attention', 'nc-widget', 'wiki-pages', 'tile'] as $type) {
			$rows = [];
			foreach ($boards['vandaag']['widgets'] as $widget) {
				if (($widget['widgetId'] ?? 'tile') === $type) {
					$rows[] = $widget['gridY'];
				}
			}

			$order[] = min($rows);
		}

		$sorted = $order;
		sort($sorted);
		$this->assertSame(expected: $sorted, actual: $order);

		foreach ($boards['vandaag']['widgets'] as $widget) {
			$this->assertArrayNotHasKey(key: 'fillHeight', array: ($widget['content'] ?? []));
		}

		$workspace = array_map(callback: static fn (array $w): string => ($w['widgetId'] ?? 'tile'), array: $boards['werkplek']['widgets']);
		$onTheStartPage = ['weather', 'nc-widget', 'calendar', 'assigned-cases', 'work-packages', 'wiki-pages', 'links', 'tile'];
		$this->assertSame(expected: [], actual: array_values(array_intersect($workspace, $onTheStartPage)));
	}//end testTheStartPageHoldsTheWholeWorkingDay()

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

	/**
	 * The management board's "Koppelingen van dit dashboard" card is the
	 * connections widget, with the old text card as its fallback.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-25-dashboard-connections-widget/specs/app-connections/spec.md#requirement-req-lp-conn-005-a-dashboard-shows-the-state-of-its-own-connections
	 */
	public function testTheManagementBoardShowsItsConnections(): void {
		[$card] = self::placementsOf(type: 'connections');
		$this->assertSame(expected: 'Koppelingen van dit dashboard', actual: $card['customTitle']);
		$this->assertSame(expected: 'text', actual: $card['fallback']['widgetId']);
	}//end testTheManagementBoardShowsItsConnections()

	/**
	 * The shared boards draw their widget cards without an Actions menu, as
	 * the personal ones do.
	 *
	 * @return void
	 */
	public function testSharedBoardWidgetsHaveNoActionsMenu(): void {
		foreach (self::seed()['dashboards'] as $dashboard) {
			if (in_array(needle: $dashboard['board'], haystack: ['LpAfdeling', 'LpManagement'], strict: true) === false) {
				continue;
			}

			foreach ($dashboard['widgets'] as $widget) {
				if (isset($widget['widgetId']) === false || $widget['widgetId'] === 'section-heading') {
					continue;
				}

				$this->assertFalse(condition: $widget['content']['showActions'] ?? true, message: $dashboard['board'] . ' ' . $widget['widgetId']);
			}
		}
	}//end testSharedBoardWidgetsHaveNoActionsMenu()

	/**
	 * A container's children name their widget type under `type`, the key the
	 * container renders them by.
	 *
	 * @return void
	 */
	public function testContainerChildrenNameTheirType(): void {
		$containers = self::placementsOf(type: 'container');
		$this->assertNotSame(expected: [], actual: $containers);
		foreach ($containers as $container) {
			foreach ($container['content']['placements'] as $child) {
				$this->assertSame(expected: 'stat', actual: $child['type'] ?? null);
				// The container's inner grid has four columns (REQ-CONT-002).
				$this->assertSame(expected: 1, actual: $child['gridWidth']);
				$this->assertLessThan(expected: 4, actual: $child['gridX']);
				// A dossiq case carries the role of its status (statusType.role), the same for every case type.
				$this->assertContains(
					needle: $child['content']['source']['filter']['statusRole'] ?? null,
					haystack: ['intake', 'pending-info', 'in-progress', 'review', 'closed', 'stranded']
				);
				$this->assertArrayNotHasKey(key: 'widgetId', array: $child);
			}
		}
	}//end testContainerChildrenNameTheirType()

	/**
	 * The public start page and every kiosk entry are seeded dashboards, and
	 * Communications may write announcements (LpPubliek, LpKiosk, LpAankondiging).
	 *
	 * @return void
	 */
	public function testPublishingNamesSeededDashboards(): void {
		$seed  = self::seed();
		$uuids = array_column(array: $seed['dashboards'], column_key: 'uuid');
		$this->assertContains(needle: $seed['publishing']['startPage'], haystack: $uuids);
		// The seed never switches the start page on: it would take over the plain login address.
		$this->assertFalse(condition: $seed['publishing']['enableStartPage']);
		foreach ($seed['publishing']['kioskPlaylists'] as $playlist) {
			$this->assertCount(expectedCount: 4, haystack: $playlist['entries']);
			foreach ($playlist['entries'] as $entry) {
				$this->assertContains(needle: $entry['dashboardUuid'], haystack: $uuids);
			}
		}

		$this->assertContains(needle: 'communicatie', haystack: $seed['adminSettings']['announcement_editor_groups']);
	}//end testPublishingNamesSeededDashboards()

	/**
	 * Every "Nieuwe collega's" placement lists new colleagues by start date
	 * (`selectionMode: 'new'`, the spec's default windows), not by account age.
	 *
	 * @spec openspec/changes/archive/2026-11-25-people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
	 *
	 * @return void
	 */
	public function testNewColleaguePlacementsListByStartDate(): void {
		$placements = array_values(
			array: array_filter(
				array: self::placementsOf(type: 'people'),
				callback: static fn (array $widget): bool => ($widget['customTitle'] ?? '') === "Nieuwe collega's"
			)
		);
		$this->assertNotEmpty(actual: $placements);
		foreach ($placements as $placement) {
			$this->assertSame(expected: 'new', actual: $placement['content']['selectionMode']);
			$this->assertSame(expected: 30, actual: $placement['content']['newSinceDays']);
			$this->assertSame(expected: 14, actual: $placement['content']['newUntilDays']);
		}
	}//end testNewColleaguePlacementsListByStartDate()
}//end class
