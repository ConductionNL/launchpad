<?php

/**
 * ConvertGroupLinksToTilesTest
 *
 * REQ-TILE-041: on upgrade the address links stored inside groups become tile
 * nodes; a group without them is not written; a failure is a warning.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Repair
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
 */

declare(strict_types=1);

namespace Unit\Repair;

use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Repair\ConvertGroupLinksToTiles;
use OCA\LaunchPad\Service\GroupTileNode;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ConvertGroupLinksToTilesTest extends TestCase {

	/**
	 * A group placement with the given content.
	 *
	 * @param array<string, mixed> $content The content.
	 *
	 * @return WidgetPlacement
	 */
	private static function group(array $content): WidgetPlacement {
		$placement = new WidgetPlacement();
		$placement->setWidgetId('container');
		$placement->setContentArray(content: $content);
		return $placement;
	}//end group()

	/**
	 * Only a group with address links is written, with its links as tiles.
	 *
	 * @return void
	 */
	public function testWritesOnlyTheGroupsWithLinks(): void {
		$withLinks = self::group(content: ['title' => 'Werk', 'placements' => [
			['type' => 'link', 'content' => ['label' => 'Intranet', 'url' => 'https://intranet.example.org', 'actionType' => 'external']],
			['type' => 'link', 'content' => ['label' => 'Wiki', 'url' => '/apps/collectives']],
		]]);
		$done      = self::group(content: ['title' => 'Apps', 'placements' => [
			['type' => 'tile', 'content' => ['title' => 'Mail', 'linkType' => 'url', 'linkValue' => '/apps/mail']],
		]]);
		$mapper    = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$mapper->method('findByWidgetId')->with('container')->willReturn([$withLinks, $done]);
		$mapper->expects($this->once())->method('update')->with($withLinks)->willReturnArgument(0);
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('info')->with('2 link(s) in 1 group(s) became tiles.');

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$step   = new ConvertGroupLinksToTiles(placements: $mapper, tileNode: new GroupTileNode(), logger: $logger);
		$this->assertSame(expected: 'Turn the links inside tile groups into tiles', actual: $step->getName());
		$step->run(output: $output);

		$children = $withLinks->getContentArray()['placements'];
		$this->assertSame(expected: ['tile', 'tile'], actual: array_column($children, 'type'));
		$this->assertSame(expected: 'Werk', actual: $withLinks->getContentArray()['title']);
		$this->assertSame(expected: '/apps/collectives', actual: $children[1]['content']['linkValue']);
	}//end testWritesOnlyTheGroupsWithLinks()

	/**
	 * A failure is a warning.
	 *
	 * @return void
	 */
	public function testAFailureIsAWarning(): void {
		$mapper = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$mapper->method('findByWidgetId')->willThrowException(new RuntimeException('database gone'));
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('warning')->with('The links inside tile groups were not turned into tiles: database gone');
		$output->expects($this->never())->method('info');

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		(new ConvertGroupLinksToTiles(placements: $mapper, tileNode: new GroupTileNode(), logger: $logger))->run(output: $output);
	}//end testAFailureIsAWarning()
}//end class
