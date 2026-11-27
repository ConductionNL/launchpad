<?php

/**
 * ConvertMultiTilesToWellsTest
 *
 * Every multi-tile becomes a well whose app list carries its settings, in
 * place (same id, cell, size, order, rules, style, title), nested ones too,
 * and a second run changes nothing (layout-widgets-replace-multi-tile D2b).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Repair
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-5
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Repair;

use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Repair\ConvertMultiTilesToWells;
use OCA\LaunchPad\Service\MultiTileNode;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Multi-tiles become wells of the viewer's apps.
 */
class ConvertMultiTilesToWellsTest extends TestCase {

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
	 * Run the step over the placements and return what it wrote.
	 *
	 * @param array<int, WidgetPlacement> $placements The stored placements.
	 *
	 * @return array<int, WidgetPlacement> The placements written.
	 */
	private function runOver(array $placements): array {
		$written = [];
		$mapper  = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$mapper->method('findAll')->willReturn($placements);
		$mapper->method('update')->willReturnCallback(
			function (WidgetPlacement $entity) use (&$written): WidgetPlacement {
				$written[] = $entity;
				return $entity;
			}
		);
		$step = new ConvertMultiTilesToWells(
			placements: $mapper,
			multiTile: new MultiTileNode(),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
		$step->run(output: $this->createMock(originalClassName: IOutput::class));
		return $written;
	}//end runOver()

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

		$written = $this->runOver(placements: [$multi, self::placement(id: 12, type: 'clock', content: [])]);

		$this->assertSame(expected: [$multi], actual: $written);
		$this->assertSame(expected: 'container', actual: $multi->getWidgetId());
		$this->assertSame(expected: 11, actual: $multi->getId());
		$this->assertSame(
			expected: [3, 2, 6, 4, 4],
			actual: [$multi->getGridX(), $multi->getGridY(), $multi->getGridWidth(), $multi->getGridHeight(), $multi->getSortOrder()]
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

		$this->runOver(placements: [$off, $defaults]);

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
		$fixed = self::placement(id: 3, type: 'app-tiles', content: ['apps' => [['id' => 'files'], ['id' => 'spreed']]]);

		$this->runOver(placements: [$fixed]);

		$content = $fixed->getContentArray();
		$this->assertArrayNotHasKey(key: 'appList', array: $content);
		$this->assertSame(expected: ['files', 'spreed'], actual: array_column(array_column($content['placements'], 'content'), 'appId'));
		$this->assertSame(expected: ['tile', 'tile'], actual: array_column($content['placements'], 'type'));
		$this->assertSame(expected: [0, 1], actual: array_column($content['placements'], 'gridX'));
	}//end testAFixedAppListBecomesAppTilesInOrder()

	/**
	 * A multi-tile inside a well or a tab is converted too, and a second run writes nothing.
	 *
	 * @return void
	 */
	public function testNestedMultiTilesAreConvertedAndASecondRunIsANoOp(): void {
		$tabs = self::placement(
			id: 4,
			type: 'tabs',
			content: ['tabs' => [['id' => 'a', 'title' => 'A', 'placements' => [['type' => 'app-tiles', 'content' => ['pinned' => ['files']]]]]]]
		);

		$this->assertCount(expectedCount: 1, haystack: $this->runOver(placements: [$tabs]));
		$child = $tabs->getContentArray()['tabs'][0]['placements'][0];
		$this->assertSame(expected: 'container', actual: $child['type']);
		$this->assertSame(expected: ['files'], actual: $child['content']['appList']['pinned']);

		$this->assertSame(expected: [], actual: $this->runOver(placements: [$tabs]));
	}//end testNestedMultiTilesAreConvertedAndASecondRunIsANoOp()

	/**
	 * A store that cannot be read is a warning, not a failed upgrade.
	 *
	 * @return void
	 */
	public function testAnUnreadableStoreIsAWarning(): void {
		$mapper = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$mapper->method('findAll')->willThrowException(new RuntimeException('db down'));
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('warning');

		(new ConvertMultiTilesToWells(
			placements: $mapper,
			multiTile: new MultiTileNode(),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		))->run(output: $output);
	}//end testAnUnreadableStoreIsAWarning()
}//end class
