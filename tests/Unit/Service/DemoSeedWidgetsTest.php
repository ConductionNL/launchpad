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
		'talk'    => ['name' => 'Talk', 'icon' => 'MessageTextOutline', 'colour' => 'work-ink', 'appId' => 'spreed', 'url' => '/index.php/apps/spreed/'],
		'topdesk' => ['name' => 'TOPdesk', 'icon' => 'Phone', 'colour' => 'accent', 'url' => 'https://topdesk.example/'],
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

		$this->assertSame(expected: 'app-tile', actual: $result['placed'][0]['widgetId']);
		$this->assertSame(expected: ['appId' => 'spreed', 'colour' => 'work-ink', 'showCount' => true], actual: $result['placed'][0]['content']);
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
		$this->assertSame(expected: ['colour' => 'accent'], actual: $result['placed'][1]['content']);
	}//end testTileIsALauncherTileInItsTokenColourWithoutAppTileType()

	/**
	 * Tile of a disabled app is a launcher tile.
	 *
	 * @return void
	 */
	public function testTileOfADisabledAppIsALauncherTile(): void {
		$result = $this->expander(types: ['app-tile', 'tile'])->expand(entries: [['tile' => 'talk']]);

		$this->assertSame(expected: 'tile', actual: $result['placed'][0]['widgetId']);
		$this->assertSame(expected: '/index.php/apps/spreed/', actual: $result['placed'][0]['tileLinkValue']);
	}//end testTileOfADisabledAppIsALauncherTile()

	/**
	 * A tile entry marked iconOnly hands the flag to its placement.
	 *
	 * @return void
	 */
	public function testIconOnlyTileCarriesTheFlagInItsContent(): void {
		$result = $this->expander(types: ['tile'])->expand(entries: [['tile' => 'topdesk', 'iconOnly' => true], ['tile' => 'talk']]);

		$this->assertTrue($result['placed'][0]['content']['iconOnly']);
		$this->assertArrayNotHasKey(key: 'iconOnly', array: $result['placed'][1]['content']);
	}//end testIconOnlyTileCarriesTheFlagInItsContent()

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
