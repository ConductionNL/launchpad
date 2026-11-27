<?php

/**
 * ConvertTilesToAppOrLinkTilesTest
 *
 * REQ-APPTILE-006: on upgrade every app tile becomes an App or link tile in
 * app mode with the same id and everything else, a tile without a mode a link
 * tile, groups included; the step is idempotent and a failure is a warning.
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
 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-existing-app-tiles-and-link-tiles-become-app-or-link-tiles-req-apptile-006
 */

declare(strict_types=1);

namespace Unit\Repair;

use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Repair\ConvertTilesToAppOrLinkTiles;
use OCA\LaunchPad\Service\TileModeNode;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ConvertTilesToAppOrLinkTilesTest extends TestCase {

	/**
	 * A placement of the given type and content.
	 *
	 * @param int                  $id      The id.
	 * @param string               $type    The widget type.
	 * @param array<string, mixed> $content The content.
	 *
	 * @return WidgetPlacement
	 */
	private static function placement(int $id, string $type, array $content): WidgetPlacement {
		$placement = new WidgetPlacement();
		$placement->setId($id);
		$placement->setWidgetId($type);
		$placement->setGridX(3);
		$placement->setGridY(2);
		$placement->setGridWidth(2);
		$placement->setGridHeight(2);
		$placement->setSortOrder(4);
		$placement->setContentArray(content: $content);
		return $placement;
	}//end placement()

	/**
	 * Run the step over the placements and return what it wrote.
	 *
	 * @param array<int, WidgetPlacement> $placements The stored placements.
	 * @param string                      $info       The expected summary.
	 *
	 * @return array<int, WidgetPlacement> The placements written.
	 */
	private function runOver(array $placements, string $info): array {
		$written = [];
		$mapper  = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$mapper->method('findAll')->willReturn($placements);
		$mapper->method('update')->willReturnCallback(function ($entity) use (&$written) {
			$written[] = $entity;
			return $entity;
		});
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('info')->with($info);
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$step   = new ConvertTilesToAppOrLinkTiles(placements: $mapper, tileMode: new TileModeNode(), logger: $logger);
		$this->assertSame(expected: 'Turn app tiles and link tiles into App or link tiles', actual: $step->getName());
		$step->run(output: $output);
		return $written;
	}//end runOver()

	/**
	 * An old app tile survives the upgrade: same id, place, app and colour.
	 *
	 * @return void
	 */
	public function testAnOldAppTileSurvives(): void {
		$appTile = self::placement(id: 11, type: 'app-tile', content: ['appId' => 'files', 'colour' => 'accent', 'showCount' => false, 'pinned' => true]);
		$link    = self::placement(id: 12, type: 'tile', content: ['title' => 'Intranet']);
		$done    = self::placement(id: 13, type: 'tile', content: ['mode' => 'app', 'appId' => 'deck']);
		$other   = self::placement(id: 14, type: 'news', content: ['url' => 'https://example.org/feed']);
		$written = $this->runOver(placements: [$appTile, $link, $done, $other], info: '2 tile(s) in 2 placement(s) became App or link tiles.');

		$this->assertSame(expected: [11, 12], actual: array_map(fn ($p) => $p->getId(), $written));
		$this->assertSame(expected: 'tile', actual: $appTile->getWidgetId());
		$place = [$appTile->getGridX(), $appTile->getGridY(), $appTile->getGridWidth(), $appTile->getGridHeight(), $appTile->getSortOrder()];
		$this->assertSame(expected: [3, 2, 2, 2, 4], actual: $place);
		$this->assertSame(
			expected: ['appId' => 'files', 'colour' => 'accent', 'showCount' => false, 'pinned' => true, 'mode' => 'app', 'colourToken' => '--sc-accent'],
			actual: $appTile->getContentArray()
		);
		$this->assertSame(expected: ['title' => 'Intranet', 'mode' => 'link'], actual: $link->getContentArray());
	}//end testAnOldAppTileSurvives()

	/**
	 * Tiles inside a group are converted too, at any depth.
	 *
	 * @return void
	 */
	public function testConvertsTilesInsideGroups(): void {
		$group = self::placement(id: 21, type: 'container', content: ['title' => 'Werk', 'placements' => [
			['id' => 'a', 'type' => 'app-tile', 'content' => ['appId' => 'mail']],
			['id' => 'b', 'type' => 'tile', 'content' => ['title' => 'Wiki']],
			['id' => 'c', 'type' => 'container', 'content' => ['placements' => [
				['id' => 'd', 'type' => 'app-tile', 'content' => ['appId' => 'deck', 'colourToken' => '--sc-primary', 'colour' => 'accent']],
			]]],
			['id' => 'e', 'type' => 'label', 'content' => ['text' => 'Hallo']],
		]]);
		$this->runOver(placements: [$group], info: '3 tile(s) in 1 placement(s) became App or link tiles.');

		$children = $group->getContentArray()['placements'];
		$this->assertSame(expected: ['tile', 'tile', 'container', 'label'], actual: array_column($children, 'type'));
		$this->assertSame(expected: ['a', 'b', 'c', 'e'], actual: array_column($children, 'id'));
		$this->assertSame(expected: ['appId' => 'mail', 'mode' => 'app'], actual: $children[0]['content']);
		$this->assertSame(expected: 'link', actual: $children[1]['content']['mode']);
		$nested = $children[2]['content']['placements'][0];
		$seen   = [$nested['type'], $nested['content']['mode'], $nested['content']['colourToken']];
		$this->assertSame(expected: ['tile', 'app', '--sc-primary'], actual: $seen);
		$this->assertSame(expected: ['text' => 'Hallo'], actual: $children[3]['content']);
	}//end testConvertsTilesInsideGroups()

	/**
	 * Running the step twice changes nothing the second time.
	 *
	 * @return void
	 */
	public function testIsIdempotent(): void {
		$placements = [
			self::placement(id: 31, type: 'app-tile', content: ['appId' => 'files']),
			self::placement(id: 32, type: 'container', content: ['placements' => [['type' => 'tile', 'content' => []]]]),
		];
		$this->runOver(placements: $placements, info: '2 tile(s) in 2 placement(s) became App or link tiles.');
		$before  = array_map(fn ($p) => [$p->getWidgetId(), $p->getContentArray()], $placements);
		$written = $this->runOver(placements: $placements, info: '0 tile(s) in 0 placement(s) became App or link tiles.');
		$this->assertSame(expected: [], actual: $written);
		$this->assertSame(expected: $before, actual: array_map(fn ($p) => [$p->getWidgetId(), $p->getContentArray()], $placements));
	}//end testIsIdempotent()

	/**
	 * A failure is a warning.
	 *
	 * @return void
	 */
	public function testAFailureIsAWarning(): void {
		$mapper = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$mapper->method('findAll')->willThrowException(new RuntimeException('database gone'));
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('warning')->with('The tiles were not turned into App or link tiles: database gone');
		$output->expects($this->never())->method('info');

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		(new ConvertTilesToAppOrLinkTiles(placements: $mapper, tileMode: new TileModeNode(), logger: $logger))->run(output: $output);
	}//end testAFailureIsAWarning()
}//end class
