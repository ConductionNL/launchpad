<?php

/**
 * TemplateRolloutSummary
 *
 * What a template re-sync's dry run tells the admin before they push
 * (REQ-RESYNC-008): which widgets the template gained and lost since its
 * copies were last synced, and how many people changed their copy.
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
 * @spec openspec/changes/template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-008-the-rollout-window-says-what-will-happen
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\WidgetPlacement;

/**
 * Summarises a template against its copies.
 *
 * @spec openspec/changes/template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-008-the-rollout-window-says-what-will-happen
 */
class TemplateRolloutSummary {

	/**
	 * Constructor.
	 *
	 * @param PlacementLayout $layout Tells whether an owner moved a placement.
	 */
	public function __construct(
		private readonly PlacementLayout $layout = new PlacementLayout(),
	) {
	}//end __construct()

	/**
	 * The summary of a template against its copies.
	 *
	 * A widget is gained when the template has it and no copy does yet; it is
	 * lost when a copy holds a template widget the template no longer has. A
	 * copy is changed by its owner when it holds a widget the owner added,
	 * a template widget the owner moved or resized, or lacks a template widget
	 * other copies hold (the owner removed it).
	 *
	 * @param WidgetPlacement[]        $templatePlacements The template's placements.
	 * @param array<int, WidgetPlacement[]> $copies         Each copy's placements.
	 *
	 * @return array{gained: array<int, string>, lost: array<int, string>, changedByOwnerCount: int}
	 *     The widget names gained and lost, and the number of copies their owner changed.
	 *
	 * @spec openspec/changes/template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-008-the-rollout-window-says-what-will-happen
	 */
	public function summarize(array $templatePlacements, array $copies): array {
		$held = [];
		foreach ($templatePlacements as $templatePlacement) {
			$held[(int)$templatePlacement->getId()] = 0;
		}

		$lost = [];
		foreach ($copies as $placements) {
			foreach ($this->originsOf(placements: $placements) as $originId => $placement) {
				if (isset($held[$originId]) === true) {
					$held[$originId]++;
					continue;
				}

				$lost[$originId] = $this->nameOf(placement: $placement);
			}
		}

		$gained = [];
		foreach ($templatePlacements as $templatePlacement) {
			if ($copies !== [] && $held[(int)$templatePlacement->getId()] === 0) {
				$gained[] = $this->nameOf(placement: $templatePlacement);
			}
		}

		$changed = 0;
		foreach ($copies as $placements) {
			if ($this->ownerChanged(placements: $placements, held: $held) === true) {
				$changed++;
			}
		}

		return [
			'gained'              => $gained,
			'lost'                => array_values($lost),
			'changedByOwnerCount' => $changed,
		];
	}//end summarize()

	/**
	 * A copy's template-origin placements by the template placement they came from.
	 *
	 * @param WidgetPlacement[] $placements The copy's placements.
	 *
	 * @return array<int, WidgetPlacement> The placements by origin id.
	 */
	private function originsOf(array $placements): array {
		$origins = [];
		foreach ($placements as $placement) {
			$originId = $placement->getTemplatePlacementId();
			if ($originId !== null) {
				$origins[(int)$originId] = $placement;
			}
		}

		return $origins;
	}//end originsOf()

	/**
	 * Whether the owner changed this copy.
	 *
	 * @param WidgetPlacement[]  $placements The copy's placements.
	 * @param array<int, int>    $held       Per template placement id, the number of copies holding it.
	 *
	 * @return bool True when the owner added, moved or removed a widget.
	 *
	 * @spec openspec/changes/template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-008-the-rollout-window-says-what-will-happen
	 */
	private function ownerChanged(array $placements, array $held): bool {
		foreach ($placements as $placement) {
			if ($placement->getTemplatePlacementId() === null || $this->layout->ownerMoved(placement: $placement) === true) {
				return true;
			}
		}

		$origins = $this->originsOf(placements: $placements);
		foreach ($held as $templateId => $count) {
			if ($count > 0 && isset($origins[$templateId]) === false) {
				return true;
			}
		}

		return false;
	}//end ownerChanged()

	/**
	 * The name a person reads for a widget.
	 *
	 * @param WidgetPlacement $placement The placement.
	 *
	 * @return string Its own title, its tile title, else its widget id.
	 */
	private function nameOf(WidgetPlacement $placement): string {
		foreach ([$placement->getCustomTitle(), $placement->getTileTitle()] as $name) {
			if ($name !== null && trim($name) !== '') {
				return $name;
			}
		}

		return (string)$placement->getWidgetId();
	}//end nameOf()
}//end class
