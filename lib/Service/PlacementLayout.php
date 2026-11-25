<?php

/**
 * PlacementLayout
 *
 * The grid layout of a widget placement as one comparable value, and the
 * layout a template sync last gave a copy's placement (its baseline). A
 * `merge` re-sync keeps the owner's position and size when the placement's
 * current layout differs from that baseline (REQ-RESYNC-007).
 *
 * @category Service
 * @package  OCA\LaunchPad\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-11-25-template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-007-merge-leaves-a-widget-where-the-person-put-it
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\WidgetPlacement;

/**
 * Reads, compares and stamps a placement's grid layout.
 *
 * @spec openspec/changes/archive/2026-11-25-template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-007-merge-leaves-a-widget-where-the-person-put-it
 */
class PlacementLayout {

	/**
	 * The grid fields a layout is made of.
	 *
	 * @var array<int, string>
	 */
	public const FIELDS = ['gridX', 'gridY', 'gridWidth', 'gridHeight'];

	/**
	 * The placement's layout as `gridX,gridY,gridWidth,gridHeight`.
	 *
	 * @param WidgetPlacement $placement The placement.
	 *
	 * @return string The layout.
	 *
	 * @spec openspec/changes/archive/2026-11-25-template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-007-merge-leaves-a-widget-where-the-person-put-it
	 */
	public function layoutOf(WidgetPlacement $placement): string {
		return implode(
			separator: ',',
			array: [
				(int)$placement->getGridX(),
				(int)$placement->getGridY(),
				(int)$placement->getGridWidth(),
				(int)$placement->getGridHeight(),
			]
		);
	}//end layoutOf()

	/**
	 * The placement's layout as a rectangle.
	 *
	 * @param WidgetPlacement $placement The placement.
	 *
	 * @return array{gridX:int,gridY:int,gridWidth:int,gridHeight:int} The rectangle.
	 *
	 * @spec openspec/changes/archive/2026-11-25-template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-006-add-only-strategy-adds-new-template-widgets-and-changes-nothing-else
	 */
	public function rectOf(WidgetPlacement $placement): array {
		return [
			'gridX'      => (int)$placement->getGridX(),
			'gridY'      => (int)$placement->getGridY(),
			'gridWidth'  => (int)$placement->getGridWidth(),
			'gridHeight' => (int)$placement->getGridHeight(),
		];
	}//end rectOf()

	/**
	 * Record the placement's current layout as the one the template sync gave it.
	 *
	 * @param WidgetPlacement $placement The copy's placement (mutated).
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-25-template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-007-merge-leaves-a-widget-where-the-person-put-it
	 */
	public function stamp(WidgetPlacement $placement): void {
		$placement->setSyncedLayout($this->layoutOf(placement: $placement));
	}//end stamp()

	/**
	 * Whether the owner moved or resized the placement since the template last
	 * synced it. Unknown (no baseline) reads as not moved.
	 *
	 * @param WidgetPlacement $placement The copy's placement.
	 *
	 * @return bool True when the current layout differs from the baseline.
	 *
	 * @spec openspec/changes/archive/2026-11-25-template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-007-merge-leaves-a-widget-where-the-person-put-it
	 */
	public function ownerMoved(WidgetPlacement $placement): bool {
		$baseline = $placement->getSyncedLayout();
		if ($baseline === null || $baseline === '') {
			return false;
		}

		return $baseline !== $this->layoutOf(placement: $placement);
	}//end ownerMoved()

	/**
	 * Put a layout back onto a placement.
	 *
	 * @param WidgetPlacement                                          $placement The placement (mutated).
	 * @param array{gridX:int,gridY:int,gridWidth:int,gridHeight:int} $rect      The layout.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-25-template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-006-add-only-strategy-adds-new-template-widgets-and-changes-nothing-else
	 */
	public function apply(WidgetPlacement $placement, array $rect): void {
		$placement->setGridX($rect['gridX']);
		$placement->setGridY($rect['gridY']);
		$placement->setGridWidth($rect['gridWidth']);
		$placement->setGridHeight($rect['gridHeight']);
	}//end apply()
}//end class
