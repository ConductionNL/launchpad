<?php

/**
 * TileModeNodeMultiTileTest
 *
 * Every multi-tile becomes a well whose app list carries its settings, in
 * place (same id, cell, size, order, rules, style, title), nested ones too,
 * on upgrade and on import, and a second pass changes nothing
 * (layout-widgets-replace-multi-tile D2b).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/archive/2026-11-27-layout-widgets-replace-multi-tile/tasks.md#task-5
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Service\TileModeNode;
use PHPUnit\Framework\TestCase;

/**
 * Multi-tiles become wells of the viewer's apps.
 */
class TileModeNodeMultiTileTest extends TestCase {

	/**
	 * A placement of the given type and content.
	 *
	 * @param int                  $id      The id.
	 * @param string               $type    The widget type.
	 * @param array<string, mixed> $content The content.
	 *
	 * @return WidgetPlacement The placement.
	 */
	private static function placement(int $id, string $type, array $content): WidgetPlacement {
		$placement = new WidgetPlacement();
		$placement->setId($id);
		$placement->setWidgetId($type);
		$placement->setGridX(3);
		$placement->setGridY(2);
		$placement->setGridWidth(6);
		$placement->setGridHeight(4);
		$placement->setSortOrder(4);
		$placement->setContentArray(content: $content);
		return $placement;
	}//end placement()

	/**
	 * The settings become the well's app list; the rest of the placement stays.
	 *
	 * @return void
	 */
	public function testAMultiTileBecomesAWellWithItsSettings(): void {
		$multi = self::placement(
			id: 11,
			type: 'app-tiles',
			content: [
				'title'           => 'Apps',
				'pinned'          => ['zaaksysteem'],
				'exclude'         => ['photos'],
				'showFilter'      => true,
				'showMostUsed'    => true,
				'mostUsedLimit'   => 6,
				'backgroundColor' => '#fff',
			]
		);

		$this->assertSame(expected: 1, actual: (new TileModeNode())->convertPlacement(placement: $multi));
		$this->assertSame(expected: 'container', actual: $multi->getWidgetId());
		$this->assertSame(
			expected: [11, 3, 2, 6, 4, 4],
			actual: [$multi->getId(), $multi->getGridX(), $multi->getGridY(), $multi->getGridWidth(), $multi->getGridHeight(), $multi->getSortOrder()]
		);
		$this->assertSame(
			expected: [
				'title'           => 'Apps',
				'backgroundColor' => '#fff',
				'placements'      => [],
				'appList'         => ['pinned' => ['zaaksysteem'], 'exclude' => ['photos'], 'mostUsedLimit' => 6, 'showFilter' => true],
			],
			actual: $multi->getContentArray()
		);
	}//end testAMultiTileBecomesAWellWithItsSettings()

	/**
	 * Most used switched off means no limit; unset settings take the multi-tile's defaults.
	 *
	 * @return void
	 */
	public function testMostUsedOffMeansNoLimitAndDefaultsApply(): void {
		$off      = self::placement(id: 1, type: 'app-tiles', content: ['showMostUsed' => false, 'mostUsedLimit' => 6, 'showFilter' => false]);
		$defaults = self::placement(id: 2, type: 'app-tiles', content: []);

		(new TileModeNode())->convertPlacement(placement: $off);
		(new TileModeNode())->convertPlacement(placement: $defaults);

		$this->assertSame(
			expected: ['pinned' => [], 'exclude' => [], 'mostUsedLimit' => 0, 'showFilter' => false],
			actual: $off->getContentArray()['appList']
		);
		$this->assertSame(
			expected: ['pinned' => [], 'exclude' => [], 'mostUsedLimit' => 4, 'showFilter' => true],
			actual: $defaults->getContentArray()['appList']
		);
	}//end testMostUsedOffMeansNoLimitAndDefaultsApply()

	/**
	 * A multi-tile with a fixed list of apps keeps exactly those apps, in order.
	 *
	 * @return void
	 */
	public function testAFixedAppListBecomesAppTilesInOrder(): void {
		$fixed = self::placement(id: 3, type: 'app-tiles', content: ['apps' => [['id' => 'files'], 'spreed', ['label' => 'no id']]]);

		(new TileModeNode())->convertPlacement(placement: $fixed);

		$content = $fixed->getContentArray();
		$this->assertArrayNotHasKey(key: 'appList', array: $content);
		$this->assertSame(expected: ['files', 'spreed'], actual: array_column(array_column($content['placements'], 'content'), 'appId'));
		$this->assertSame(expected: ['tile', 'tile'], actual: array_column($content['placements'], 'type'));
		$this->assertSame(expected: [0, 1], actual: array_column($content['placements'], 'gridX'));
	}//end testAFixedAppListBecomesAppTilesInOrder()

	/**
	 * A multi-tile inside a tab is converted too, and a second pass changes nothing.
	 *
	 * @return void
	 */
	public function testNestedMultiTilesAreConvertedOnce(): void {
		$tabs = self::placement(
			id: 4,
			type: 'tabs',
			content: ['tabs' => [['id' => 'a', 'title' => 'A', 'placements' => [['type' => 'app-tiles', 'content' => ['pinned' => ['files']]]]]]]
		);

		$this->assertSame(expected: 1, actual: (new TileModeNode())->convertPlacement(placement: $tabs));
		$child = $tabs->getContentArray()['tabs'][0]['placements'][0];
		$this->assertSame(expected: 'container', actual: $child['type']);
		$this->assertSame(expected: ['files'], actual: $child['content']['appList']['pinned']);

		$this->assertSame(expected: 0, actual: (new TileModeNode())->convertPlacement(placement: $tabs));
	}//end testNestedMultiTilesAreConvertedOnce()
}//end class
