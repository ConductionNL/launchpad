<?php

/**
 * KeyContainerChildren unit tests (REQ-WDG-050).
 *
 * @category  Test
 * @package   Unit\Repair
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

namespace Unit\Repair;

use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Repair\KeyContainerChildren;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
 */
class KeyContainerChildrenTest extends TestCase {

	/**
	 * A stored placement whose content was written before children had keys.
	 *
	 * @param int                  $id      Its id.
	 * @param string               $type    Its widget type.
	 * @param array<string, mixed> $content The content.
	 *
	 * @return WidgetPlacement
	 */
	private static function stored(int $id, string $type, array $content): WidgetPlacement {
		$placement = new WidgetPlacement();
		$placement->setId($id);
		$placement->setWidgetId($type);
		$placement->setContent(json_encode($content));
		return $placement;
	}//end stored()

	public function testKeysOnlyTheWidgetsWithAChildWithoutAKey(): void {
		$well   = self::stored(id: 12, type: 'container', content: ['placements' => [['type' => 'tile'], ['type' => 'tile', 'id' => 4]]]);
		$keyed  = self::stored(id: 13, type: 'container', content: ['placements' => [['type' => 'tile', 'uuid' => 'a']]]);
		$tabs   = self::stored(id: 14, type: 'tabs', content: ['tabs' => [['id' => 't', 'placements' => [['type' => 'clock']]]]]);
		$mapper = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$mapper->method('findByWidgetId')->willReturnCallback(
			static fn (string $type): array => ($type === 'container' ? [$well, $keyed] : [$tabs])
		);
		$mapper->expects($this->exactly(2))->method('update')->willReturnArgument(0);
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('info')->with('2 well(s) or tabs widget(s) got keys for their widgets.');

		$step = new KeyContainerChildren(placements: $mapper, logger: $this->createMock(originalClassName: LoggerInterface::class));
		$this->assertSame('Give every widget inside a well or tabs widget a key of its own', $step->getName());
		$step->run(output: $output);

		$this->assertSame('12-0', $well->getContentArray()['placements'][0]['uuid']);
		$this->assertSame(4, $well->getContentArray()['placements'][1]['id']);
		$this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $tabs->getContentArray()['tabs'][0]['placements'][0]['uuid']);
	}//end testKeysOnlyTheWidgetsWithAChildWithoutAKey()

	public function testAFailureIsAWarningNotAFailedUpgrade(): void {
		$mapper = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$mapper->method('findByWidgetId')->willThrowException(new RuntimeException('db down'));
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('warning')->with('The widgets inside wells were not given keys: db down');

		(new KeyContainerChildren(placements: $mapper, logger: $this->createMock(originalClassName: LoggerInterface::class)))->run(output: $output);
	}//end testAFailureIsAWarningNotAFailedUpgrade()
}//end class
