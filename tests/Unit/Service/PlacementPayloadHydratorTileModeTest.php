<?php

/**
 * PlacementPayloadHydratorTileModeTest
 *
 * REQ-APPTILE-006: an app tile met in an import file arrives as an App or
 * link tile in app mode; a link tile without a mode becomes a link tile.
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
 * @spec openspec/changes/archive/2026-11-27-app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-existing-app-tiles-and-link-tiles-become-app-or-link-tiles-req-apptile-006
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\PlacementPayloadHydrator;
use PHPUnit\Framework\TestCase;

class PlacementPayloadHydratorTileModeTest extends TestCase {

	/**
	 * An imported app tile is a tile in app mode at the same place.
	 *
	 * @return void
	 */
	public function testAnImportedAppTileBecomesAnAppModeTile(): void {
		$placement = (new PlacementPayloadHydrator())->hydrate(dashboardId: 5, payload: [
			'widgetId' => 'app-tile',
			'gridX' => 3,
			'gridY' => 2,
			'content' => ['appId' => 'files', 'colour' => 'accent'],
		]);

		$this->assertSame(expected: 'tile', actual: $placement->getWidgetId());
		$this->assertSame(expected: [3, 2], actual: [$placement->getGridX(), $placement->getGridY()]);
		$this->assertSame(
			expected: ['appId' => 'files', 'colour' => 'accent', 'mode' => 'app', 'colourToken' => '--sc-accent'],
			actual: $placement->getContentArray()
		);
	}//end testAnImportedAppTileBecomesAnAppModeTile()

	/**
	 * Another widget is imported as it is.
	 *
	 * @return void
	 */
	public function testOtherWidgetsAreUntouched(): void {
		$placement = (new PlacementPayloadHydrator())->hydrate(dashboardId: 5, payload: [
			'widgetId' => 'news',
			'content' => ['url' => 'https://example.org/feed'],
		]);

		$this->assertSame(expected: 'news', actual: $placement->getWidgetId());
		$this->assertSame(expected: ['url' => 'https://example.org/feed'], actual: $placement->getContentArray());
	}//end testOtherWidgetsAreUntouched()
}//end class
