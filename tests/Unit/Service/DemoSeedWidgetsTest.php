<?php

/**
 * DemoSeedWidgetsTest
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\DemoSeedWidgets;
use PHPUnit\Framework\TestCase;

/**
 * How seed entries become placement payloads.
 */
class DemoSeedWidgetsTest extends TestCase {
	private const TILES = [
		'talk'    => [
			'name'        => 'Talk',
			'icon'        => 'MessageTextOutline',
			'colour'      => 'work-ink',
			'appId'       => 'spreed',
			'url'         => '/index.php/apps/spreed/',
			'countSource' => ['dashboardWidget' => 'spreed'],
		],
		'topdesk' => ['name' => 'TOPdesk', 'icon' => 'Phone', 'colour' => 'accent', 'url' => 'https://topdesk.example/'],
		'zaak'    => [
			'name'        => 'Zaaksysteem',
			'icon'        => 'Briefcase',
			'colour'      => 'primary',
			'url'         => 'https://zaken.example/',
			'countSource' => ['register' => 'dossiq', 'schema' => 'case', 'filter' => ['assignee' => '@me']],
		],
		'word'    => [
			'name'   => 'Word',
			'icon'   => 'FileWord',
			'colour' => 'primary',
			'appId'  => 'richdocuments',
			'url'    => '/index.php/apps/files/?dir=/Permits',
		],
	];

	/**
	 * An expander that knows the given types and apps.
	 *
	 * @param array<int, string> $types The renderable types.
	 * @param array<int, string> $apps  The enabled apps.
	 *
	 * @return DemoSeedWidgets The expander.
	 */
	private function expander(array $types, array $apps=[]): DemoSeedWidgets {
		return new DemoSeedWidgets(
			tiles: self::TILES,
			knownTypes: array_fill_keys(keys: $types, value: true),
			enabledApps: array_fill_keys(keys: $apps, value: true)
		);
	}//end expander()

	/**
	 * Tile of an enabled app is an app tile when that type exists.
	 *
	 * @return void
	 */
	public function testTileOfAnEnabledAppIsAnAppTileWhenThatTypeExists(): void {
		$result = $this->expander(types: ['app-tile', 'tile'], apps: ['spreed'])->expand(entries: [['tile' => 'talk', 'gridX' => 3, 'gridY' => 1]]);

		$this->assertSame(expected: 'tile', actual: $result['placed'][0]['widgetId']);
		$this->assertSame(
			expected: [
				'mode'        => 'app',
				'appId'       => 'spreed',
				'colour'      => 'work-ink',
				'colourToken' => '--sc-work-ink',
				'textColour'  => 'white',
				'showCount'   => true,
				'icon'        => 'MessageTextOutline',
				'countSource' => ['dashboardWidget' => 'spreed'],
			],
			actual: $result['placed'][0]['content']
		);
		$tile = $result['placed'][0];
		$this->assertSame(expected: [3, 1, 2, 2], actual: [$tile['gridX'], $tile['gridY'], $tile['gridWidth'], $tile['gridHeight']]);
	}//end testTileOfAnEnabledAppIsAnAppTileWhenThatTypeExists()

	/**
	 * Tile is a launcher tile in its token colour without app tile type.
	 *
	 * @return void
	 */
	public function testTileIsALauncherTileInItsTokenColourWithoutAppTileType(): void {
		$result = $this->expander(types: ['tile'], apps: ['spreed'])->expand(entries: [['tile' => 'talk'], ['tile' => 'topdesk']]);

		$this->assertSame(expected: ['tile', 'tile'], actual: array_column(array: $result['placed'], column_key: 'widgetId'));
		$this->assertSame(expected: 'custom', actual: $result['placed'][1]['tileType']);
		$this->assertSame(expected: '#cc0000', actual: $result['placed'][1]['tileBackgroundColor']);
		$this->assertSame(expected: 'https://topdesk.example/', actual: $result['placed'][1]['tileLinkValue']);
		$this->assertSame(expected: ['colour' => 'accent', 'colourToken' => '--sc-accent'], actual: $result['placed'][1]['content']);
		$this->assertSame(
			expected: ['type' => 'nc-widget', 'widgetId' => 'spreed'],
			actual: $result['placed'][0]['content']['countSource']
		);
		$this->assertSame(expected: '--sc-work-ink', actual: $result['placed'][0]['content']['colourToken']);
	}//end testTileIsALauncherTileInItsTokenColourWithoutAppTileType()

	/**
	 * A launcher tile with a register count source names it with its type.
	 *
	 * @return void
	 *
 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md#requirement-an-app-tile-shows-a-live-count-req-ict-002
	 */
	public function testALauncherTileNamesItsRegisterCountSource(): void {
		$result = $this->expander(types: ['tile'], apps: [])->expand(entries: [['tile' => 'zaak']]);

		$this->assertSame(
			expected: ['type' => 'register', 'register' => 'dossiq', 'schema' => 'case', 'filter' => ['assignee' => '@me']],
			actual: $result['placed'][0]['content']['countSource']
		);
	}//end testALauncherTileNamesItsRegisterCountSource()

	/**
	 * Tile of a disabled app is left out of the seeded dashboard.
	 *
	 * @return void
	 */
	public function testTileOfADisabledAppIsLeftOut(): void {
		$result = $this->expander(types: ['app-tile', 'tile'])->expand(entries: [['tile' => 'talk'], ['tile' => 'topdesk']]);

		// A demo dashboard shows no "not available" tile: the Talk tile is not placed.
		$this->assertCount(expectedCount: 1, haystack: $result['placed']);
		$this->assertSame(expected: 'tile', actual: $result['placed'][0]['widgetId']);
		$this->assertCount(expectedCount: 1, haystack: $result['skipped']);
	}//end testTileOfADisabledAppIsLeftOut()

	/**
	 * The tiles right of a left-out tile move left within its section, so the row has no hole.
	 *
	 * @return void
	 */
	public function testTilesCloseTheGapOfALeftOutTileWithinTheirSection(): void {
		$heading = ['widgetId' => 'section-heading', 'gridWidth' => 4, 'gridHeight' => 1, 'gridY' => 0];
		$result  = $this->expander(types: ['app-tile', 'tile', 'section-heading', 'clock'])->expand(
			entries: [
				[...$heading, 'gridX' => 0],
				[...$heading, 'gridX' => 4],
				['tile' => 'topdesk', 'gridX' => 3, 'gridY' => 1, 'gridWidth' => 1],
				['tile' => 'talk', 'gridX' => 4, 'gridY' => 1, 'gridWidth' => 1],
				['tile' => 'topdesk', 'gridX' => 5, 'gridY' => 1, 'gridWidth' => 1],
				['tile' => 'topdesk', 'gridX' => 7, 'gridY' => 1, 'gridWidth' => 1],
				['tile' => 'topdesk', 'gridX' => 5, 'gridY' => 3, 'gridWidth' => 1],
				['widgetId' => 'clock', 'gridX' => 9, 'gridY' => 0, 'gridWidth' => 3, 'gridHeight' => 3],
				['tile' => 'topdesk', 'gridX' => 12, 'gridY' => 1, 'gridWidth' => 1],
			]
		);

		$cells = array_map(
			static fn (array $payload): string => $payload['gridX'] . ',' . $payload['gridY'],
			array_values(array: array_filter(array: $result['placed'], callback: static fn (array $payload): bool => $payload['widgetId'] === 'tile'))
		);
		// The Talk gap at 4,1 closes inside the second section; the tile in the
		// first section, the other row and the tile past the clock stay put.
		$this->assertSame(expected: ['3,1', '4,1', '6,1', '5,3', '12,1'], actual: $cells);
	}//end testTilesCloseTheGapOfALeftOutTileWithinTheirSection()

	/**
	 * Tile of a disabled app whose address opens another app stays a launcher tile.
	 *
	 * @return void
	 */
	public function testTileOfADisabledAppLinkingElsewhereIsALauncherTile(): void {
		$result = $this->expander(types: ['app-tile', 'tile'])->expand(entries: [['tile' => 'word']]);

		$this->assertSame(expected: 'tile', actual: $result['placed'][0]['widgetId']);
		$this->assertSame(expected: '/index.php/apps/files/?dir=/Permits', actual: $result['placed'][0]['tileLinkValue']);
	}//end testTileOfADisabledAppLinkingElsewhereIsALauncherTile()

	/**
	 * Tile of a disabled app is not placed when the app tile type is missing.
	 *
	 * @return void
	 */
	public function testTileOfADisabledAppIsSkippedWithoutAppTileType(): void {
		$result = $this->expander(types: ['tile'])->expand(entries: [['tile' => 'talk'], ['tile' => 'topdesk']]);

		$this->assertCount(expectedCount: 1, haystack: $result['placed']);
		$this->assertSame(expected: 'tile', actual: $result['placed'][0]['widgetId']);
		$this->assertCount(expectedCount: 1, haystack: $result['skipped']);
	}//end testTileOfADisabledAppIsSkippedWithoutAppTileType()

	/**
	 * A tile entry marked iconOnly hands the flag to its placement.
	 *
	 * @return void
	 */
	public function testIconOnlyTileCarriesTheFlagInItsContent(): void {
		$result = $this->expander(types: ['tile'], apps: ['spreed'])->expand(
			entries: [['tile' => 'topdesk', 'iconOnly' => true, 'fitRows' => true], ['tile' => 'talk']]
		);

		$this->assertTrue(condition: $result['placed'][0]['content']['iconOnly']);
		$this->assertTrue(condition: $result['placed'][0]['content']['fitRows']);
		$this->assertArrayNotHasKey(key: 'iconOnly', array: $result['placed'][1]['content']);
	}//end testIconOnlyTileCarriesTheFlagInItsContent()

	/**
	 * A tile entry hands its pin to its placement and drops a setting of the
	 * wrong type; a static count or outage is no longer carried (REQ-ICT-002,
	 * REQ-ICT-003: both are read live).
	 *
	 * @return void
	 */
	public function testTileCarriesItsMarks(): void {
		$result = $this->expander(types: ['tile'], apps: ['spreed'])->expand(
			entries: [['tile' => 'topdesk', 'pinned' => true, 'badge' => 3, 'status' => 'down'], ['tile' => 'talk', 'badge' => '3']]
		);

		$this->assertTrue(condition: $result['placed'][0]['content']['pinned']);
		$this->assertArrayNotHasKey(key: 'badge', array: $result['placed'][0]['content']);
		$this->assertArrayNotHasKey(key: 'status', array: $result['placed'][0]['content']);
		$this->assertArrayNotHasKey(key: 'badge', array: $result['placed'][1]['content']);
	}//end testTileCarriesItsMarks()

	/**
	 * Unregistered type uses its fallback or is skipped.
	 *
	 * @return void
	 */
	public function testUnregisteredTypeUsesItsFallbackOrIsSkipped(): void {
		$entries = [
			['widgetId' => 'my-cases', 'customTitle' => 'Mijn zaken', 'fallback' => ['widgetId' => 'object-list', 'gridX' => 0]],
			['widgetId' => 'wiki', 'customTitle' => 'Wiki'],
			['widgetId' => 'clock'],
			['tile' => 'unknown'],
		];

		$result = $this->expander(types: ['object-list', 'clock'])->expand(entries: $entries);

		$this->assertSame(expected: ['object-list', 'clock'], actual: array_column(array: $result['placed'], column_key: 'widgetId'));
		$this->assertSame(expected: ['my-cases -> object-list'], actual: $result['fallbacks']);
		$this->assertSame(expected: ['wiki (Wiki)', 'tile unknown'], actual: $result['skipped']);
		$this->assertSame(expected: [0, 2], actual: array_column(array: $result['placed'], column_key: 'sortOrder'));
	}//end testUnregisteredTypeUsesItsFallbackOrIsSkipped()

	/**
	 * Registered type is placed without its fallback.
	 *
	 * @return void
	 */
	public function testRegisteredTypeIsPlacedWithoutItsFallback(): void {
		$result = $this->expander(types: ['my-cases', 'object-list'])->expand(
			entries: [['widgetId' => 'my-cases', 'fallback' => ['widgetId' => 'object-list']]]
		);

		$this->assertSame(expected: 'my-cases', actual: $result['placed'][0]['widgetId']);
		$this->assertArrayNotHasKey(key: 'fallback', array: $result['placed'][0]);
		$this->assertSame(expected: [], actual: $result['fallbacks']);
	}//end testRegisteredTypeIsPlacedWithoutItsFallback()
	/**
	 * Nc widget of an unregistered nextcloud widget is skipped.
	 *
	 * @return void
	 */
	public function testNcWidgetOfAnUnregisteredNextcloudWidgetIsSkipped(): void {
		$entries = [
			['widgetId' => 'nc-widget', 'customTitle' => 'Mail', 'content' => ['widgetId' => 'mail']],
			['widgetId' => 'nc-widget', 'content' => ['widgetId' => 'activity']],
		];

		$result = $this->expander(types: ['nc-widget', 'activity'])->expand(entries: $entries);

		$this->assertSame(expected: 'activity', actual: $result['placed'][0]['content']['widgetId']);
		$this->assertSame(expected: ['nc-widget (Mail)'], actual: $result['skipped']);
	}//end testNcWidgetOfAnUnregisteredNextcloudWidgetIsSkipped()
}//end class
