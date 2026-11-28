<?php

/**
 * TabsWidgetDepthTest
 *
 * A Tabs widget is a container whose tabs each hold placements: its levels
 * count with the wells' toward the depth limit of three (REQ-CONT-006,
 * layout-widgets-replace-multi-tile D1).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/archive/2026-11-27-layout-widgets-replace-multi-tile/tasks.md#task-1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Service\WidgetPlacementService;
use PHPUnit\Framework\TestCase;

/**
 * Depth limit across wells and tabs.
 */
class TabsWidgetDepthTest extends TestCase {

	/**
	 * A content blob with `$levels` nested layout widgets below it, alternating
	 * tabs and wells, the deepest holding a clock. The blob itself is a Tabs
	 * widget's content when `$asTabs` is true, else a well's.
	 *
	 * @param int  $levels How many layout widgets nest below this one.
	 * @param bool $asTabs Whether this level is a Tabs widget.
	 *
	 * @return array The content.
	 */
	private function nested(int $levels, bool $asTabs): array {
		$child = ['type' => 'clock', 'content' => []];
		if ($levels > 0) {
			$childIsTabs = ($asTabs === false);
			$type        = 'container';
			if ($childIsTabs === true) {
				$type = 'tabs';
			}

			$child = ['type' => $type, 'content' => $this->nested(levels: ($levels - 1), asTabs: $childIsTabs)];
		}

		if ($asTabs === true) {
			return ['tabs' => [['id' => 'a', 'title' => 'A', 'placements' => []], ['id' => 'b', 'title' => 'B', 'placements' => [$child]]]];
		}

		return ['placements' => [$child]];
	}//end nested()

	/**
	 * Three levels of wells and tabs below a Tabs widget or a well are allowed.
	 *
	 * @return void
	 */
	public function testThreeNestedLevelsOfTabsAndWellsAreAllowed(): void {
		$service = new WidgetPlacementService();
		$service->validateContainerDepth(content: $this->nested(levels: WidgetPlacementService::MAX_CONTAINER_DEPTH, asTabs: true));
		$service->validateContainerDepth(content: $this->nested(levels: WidgetPlacementService::MAX_CONTAINER_DEPTH, asTabs: false));
		$this->assertTrue(condition: true, message: 'three nested levels must pass');
	}//end testThreeNestedLevelsOfTabsAndWellsAreAllowed()

	/**
	 * A fourth level is refused when it starts in a tab.
	 *
	 * @return void
	 */
	public function testAFourthLevelBelowTabsIsRefused(): void {
		$this->expectException(exception: InvalidArgumentException::class);
		$this->expectExceptionMessage(message: 'container_depth_exceeded');
		(new WidgetPlacementService())->validateContainerDepth(content: $this->nested(levels: 4, asTabs: true));
	}//end testAFourthLevelBelowTabsIsRefused()

	/**
	 * A fourth level is refused when it starts in a well holding tabs.
	 *
	 * @return void
	 */
	public function testAFourthLevelBelowAWellOfTabsIsRefused(): void {
		$this->expectException(exception: InvalidArgumentException::class);
		(new WidgetPlacementService())->validateContainerDepth(content: $this->nested(levels: 4, asTabs: false));
	}//end testAFourthLevelBelowAWellOfTabsIsRefused()
}//end class
