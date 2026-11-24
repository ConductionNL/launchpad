<?php

/**
 * ResyncDiffer
 *
 * The diff engine of a template re-sync: partitions one copy's placements
 * against the template's (REQ-RESYNC-003, REQ-RESYNC-004) for the
 * `overwrite`, `merge` (REQ-RESYNC-007) and `add_only` (REQ-RESYNC-006)
 * strategies. It reads only; {@see TemplateResyncService} writes.
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
 * @spec openspec/changes/template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-006-add-only-strategy-adds-new-template-widgets-and-changes-nothing-else
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\WidgetPlacement;

/**
 * Diffs a copy against its template.
 *
 * @spec openspec/changes/template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-006-add-only-strategy-adds-new-template-widgets-and-changes-nothing-else
 */
class ResyncDiffer {

	/**
	 * Where a placement whose template placement is gone goes, per strategy;
	 * `toRemove` for a strategy not listed (REQ-RESYNC-006).
	 *
	 * @var array<string, string>
	 */
	private const REMOVED_FROM_TEMPLATE = [TemplateResyncService::STRATEGY_ADD_ONLY => 'toPreserve'];

	/**
	 * The strategy this differ diffs for; see {@see self::bound()}.
	 *
	 * @var string
	 */
	private string $strategy = TemplateResyncService::STRATEGY_MERGE;

	/**
	 * The copy's grid column count; see {@see self::bound()}.
	 *
	 * @var integer
	 */
	private int $columns = 12;

	/**
	 * Constructor.
	 *
	 * @param PlacementLayout $layout    Reads a placement's layout and its baseline.
	 * @param FreeSpotFinder  $freeSpots Where an added widget goes under `add_only`.
	 */
	public function __construct(
		private readonly PlacementLayout $layout = new PlacementLayout(),
		private readonly FreeSpotFinder $freeSpots = new FreeSpotFinder(),
	) {
	}//end __construct()

	/**
	 * A differ for one strategy and one copy's grid width.
	 *
	 * @param string $strategy `'overwrite'`, `'merge'` or `'add_only'`.
	 * @param int    $columns  The copy's grid column count.
	 *
	 * @return self The bound differ; this one is left as it was.
	 *
	 * @spec openspec/changes/template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-006-add-only-strategy-adds-new-template-widgets-and-changes-nothing-else
	 */
	public function bound(string $strategy, int $columns): self {
		$bound           = clone $this;
		$bound->strategy = $strategy;
		$bound->columns  = max(1, $columns);

		return $bound;
	}//end bound()

	/**
	 * Partition one copy's placements against the template's current
	 * placements (REQ-RESYNC-003, REQ-RESYNC-004).
	 *
	 * Matching is by {@see WidgetPlacement::getTemplatePlacementId()}:
	 *
	 * - Matched + fields differ  → `toUpdate` (reconcile onto the copy).
	 * - Matched + fields equal   → `toPreserve` (no-op, both strategies).
	 * - Template-origin but the
	 *   matching template placement
	 *   no longer exists           → `toRemove` (admin deleted it from the
	 *                                template — both strategies).
	 * - No known origin
	 *   (user-added)                → `toPreserve` under `merge`,
	 *                                `toRemove` under `overwrite`.
	 * - A template placement with
	 *   no matching copy placement  → `toAdd` (new template placement, or
	 *                                a compulsory widget the user removed
	 *                                — restored either way).
	 *
	 * Compulsory widgets receive no special-case handling: they are
	 * ordinary template placements, so the `toAdd` (restore) / `toUpdate`
	 * (align position+flags) branches above already reconcile them under
	 * EVERY strategy, satisfying REQ-RESYNC-004 without a separate code
	 * path.
	 *
	 * Under `add_only` (REQ-RESYNC-006) a non-compulsory placement is never
	 * updated or removed, and each addition gets the first free spot on the
	 * copy (`addSpots`, by `toAdd` index). Under `merge` a placement the
	 * owner moved or resized keeps its layout (REQ-RESYNC-007).
	 *
	 * @param WidgetPlacement[] $templatePlacements The template's current
	 *                                              placements.
	 * @param WidgetPlacement[] $copyPlacements The copy's current
	 *                                          placements.
	 *
	 * @return array{
	 *     toAdd: WidgetPlacement[],
	 *     addSpots: array<int, array{gridX:int,gridY:int,gridWidth:int,gridHeight:int}>,
	 *     toUpdate: array<int, array{copy: WidgetPlacement, template: WidgetPlacement, keepLayout: bool}>,
	 *     toRemove: WidgetPlacement[],
	 *     toPreserve: WidgetPlacement[]
	 * }
	 *
	 * @spec openspec/changes/template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-006-add-only-strategy-adds-new-template-widgets-and-changes-nothing-else
	 * @spec openspec/changes/template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-007-merge-leaves-a-widget-where-the-person-put-it
	 */
	public function diff(
		array $templatePlacements,
		array $copyPlacements,
	): array {
		$templateById = [];
		foreach ($templatePlacements as $tp) {
			$templateById[$tp->getId()] = $tp;
		}

		$matchedTemplateIds = [];
		$buckets = [
			'toUpdate' => [],
			'toRemove' => [],
			'toPreserve' => [],
		];

		foreach ($copyPlacements as $cp) {
			$originId = $cp->getTemplatePlacementId();
			if ($originId !== null && isset($templateById[$originId]) === true) {
				$matchedTemplateIds[$originId] = true;
			}

			[$bucket, $entry] = $this->classifyCopyPlacement(copy: $cp, templateById: $templateById);
			if ($bucket === null) {
				// No known origin — a genuinely user-added placement.
				$bucket = 'toPreserve';
				if ($this->strategy === TemplateResyncService::STRATEGY_OVERWRITE) {
					$bucket = 'toRemove';
				}
			}

			$buckets[$bucket][] = $entry;
		}//end foreach

		$toAdd = $this->unmatchedTemplatePlacements(
			templatePlacements: $templatePlacements,
			matchedTemplateIds: $matchedTemplateIds
		);

		$addSpots = [];
		if ($this->strategy === TemplateResyncService::STRATEGY_ADD_ONLY) {
			$addSpots = $this->freeSpotsFor(toAdd: $toAdd, copyPlacements: $copyPlacements);
		}

		return [
			'toAdd' => $toAdd,
			'addSpots' => $addSpots,
			'toUpdate' => $buckets['toUpdate'],
			'toRemove' => $buckets['toRemove'],
			'toPreserve' => $buckets['toPreserve'],
		];
	}//end diff()

	/**
	 * The spot each added placement takes on a copy under `add_only`: its
	 * template spot when free, else the first free one, never over a widget
	 * already on the copy or added before it (REQ-RESYNC-006).
	 *
	 * @param WidgetPlacement[] $toAdd          The template placements to add.
	 * @param WidgetPlacement[] $copyPlacements The copy's placements.
	 *
	 * @return array<int, array{gridX:int,gridY:int,gridWidth:int,gridHeight:int}> The spots, by `toAdd` index.
	 *
	 * @spec openspec/changes/template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-006-add-only-strategy-adds-new-template-widgets-and-changes-nothing-else
	 */
	private function freeSpotsFor(array $toAdd, array $copyPlacements): array {
		$occupied = array_map(
			fn (WidgetPlacement $placement): array => $this->layout->rectOf(placement: $placement),
			array_values($copyPlacements)
		);

		$spots = [];
		foreach ($toAdd as $index => $templatePlacement) {
			$spot = $this->freeSpots->place(
				occupied: $occupied,
				columns: $this->columns,
				wanted: $this->layout->rectOf(placement: $templatePlacement)
			);
			$spots[$index] = $spot;
			$occupied[]    = $spot;
		}

		return $spots;
	}//end freeSpotsFor()

	/**
	 * Classify one copy placement against the template placements it may
	 * originate from (REQ-RESYNC-003).
	 *
	 * A placement whose template origin still exists goes to `toUpdate`
	 * when its fields differ and to `toPreserve` when they are equal; one
	 * whose origin the admin has since deleted goes to `toRemove` under
	 * both strategies. A placement with no known origin is user-added: the
	 * bucket is null and the strategy decides.
	 *
	 * Under `add_only` nothing but a compulsory placement is touched: a
	 * removed or changed template placement stays as the copy has it
	 * (REQ-RESYNC-006). Under `merge` a non-compulsory placement the owner
	 * moved or resized keeps its layout and takes the template's other
	 * fields (REQ-RESYNC-007).
	 *
	 * @param WidgetPlacement             $copy         The copy placement.
	 * @param array<int, WidgetPlacement> $templateById The template placements by ID.
	 *
	 * @return array{0: string|null, 1: WidgetPlacement|array{copy: WidgetPlacement, template: WidgetPlacement, keepLayout: bool}}
	 *     The bucket name (null for a user-added placement) and the entry to put in it.
	 *
	 * @spec openspec/changes/template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-006-add-only-strategy-adds-new-template-widgets-and-changes-nothing-else
	 * @spec openspec/changes/template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-007-merge-leaves-a-widget-where-the-person-put-it
	 */
	private function classifyCopyPlacement(WidgetPlacement $copy, array $templateById): array {
		$originId = $copy->getTemplatePlacementId();
		if ($originId === null) {
			return [null, $copy];
		}

		if (isset($templateById[$originId]) === false) {
			// The admin removed it from the template: gone, except under `add_only`.
			return [(self::REMOVED_FROM_TEMPLATE[$this->strategy] ?? 'toRemove'), $copy];
		}

		return $this->classifyMatched(copy: $copy, template: $templateById[$originId]);
	}//end classifyCopyPlacement()

	/**
	 * Classify a copy placement whose template placement still exists.
	 *
	 * @param WidgetPlacement $copy     The copy placement.
	 * @param WidgetPlacement $template The template placement it came from.
	 *
	 * @return array{0: string, 1: WidgetPlacement|array{copy: WidgetPlacement, template: WidgetPlacement, keepLayout: bool}}
	 *     The bucket name and the entry to put in it.
	 *
	 * @spec openspec/changes/template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-006-add-only-strategy-adds-new-template-widgets-and-changes-nothing-else
	 * @spec openspec/changes/template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-007-merge-leaves-a-widget-where-the-person-put-it
	 */
	private function classifyMatched(WidgetPlacement $copy, WidgetPlacement $template): array {
		$compulsory = ((int)$template->getIsCompulsory() === 1);
		if ($this->strategy === TemplateResyncService::STRATEGY_ADD_ONLY && $compulsory === false) {
			return ['toPreserve', $copy];
		}

		$ignore = array_flip($this->ownerLayoutFields(copy: $copy, template: $template));
		$differs = array_diff_key($this->templateFieldSnapshot(placement: $copy), $ignore)
			!== array_diff_key($this->templateFieldSnapshot(placement: $template), $ignore);

		if ($differs === true) {
			return [
				'toUpdate',
				[
					'copy' => $copy,
					'template' => $template,
					'keepLayout' => ($ignore !== []),
				],
			];
		}

		return ['toPreserve', $copy];
	}//end classifyMatched()

	/**
	 * The grid fields a matched placement keeps from its owner, so the diff
	 * does not compare them: under `merge`, for a non-compulsory widget the owner moved or resized (REQ-RESYNC-007).
	 *
	 * @param WidgetPlacement $copy     The copy's placement.
	 * @param WidgetPlacement $template The template placement it came from.
	 *
	 * @return array<int, string> The grid fields when the owner's position and size stay, else none.
	 *
	 * @spec openspec/changes/template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-007-merge-leaves-a-widget-where-the-person-put-it
	 */
	private function ownerLayoutFields(WidgetPlacement $copy, WidgetPlacement $template): array {
		if ($this->strategy !== TemplateResyncService::STRATEGY_MERGE || (int)$template->getIsCompulsory() === 1) {
			return [];
		}

		if ($this->layout->ownerMoved(placement: $copy) === false) {
			return [];
		}

		return PlacementLayout::FIELDS;
	}//end ownerLayoutFields()

	/**
	 * Template placements the copy has no counterpart for — i.e. the
	 * additions a re-sync must make.
	 *
	 * @param WidgetPlacement[] $templatePlacements The template's current placements.
	 * @param array<int, boolean> $matchedTemplateIds Set of template placement IDs
	 *                                                already matched to a copy
	 *                                                placement, keyed by ID.
	 *
	 * @return WidgetPlacement[] The unmatched template placements, in input order.
	 */
	private function unmatchedTemplatePlacements(array $templatePlacements, array $matchedTemplateIds): array {
		$toAdd = [];
		foreach ($templatePlacements as $tp) {
			if (isset($matchedTemplateIds[$tp->getId()]) === false) {
				$toAdd[] = $tp;
			}
		}

		return $toAdd;
	}//end unmatchedTemplatePlacements()

	/**
	 * Whether a diff carries any mutation (add/update/remove). Used to
	 * decide `affectedCount` and to skip untouched copies entirely
	 * (REQ-RESYNC-005 idempotency).
	 *
	 * @param array<string, mixed> $diff The diff computed by
	 *                                   {@see self::diff()}.
	 *
	 * @return bool True when applying the diff would change the copy.
	 *
	 * @spec openspec/specs/admin-templates/spec.md#requirement-req-resync-005-re-sync-is-idempotent-audited-async-capable-and-notifies-users
	 */
	public function hasChanges(array $diff): bool {
		return count($diff['toAdd']) > 0
			|| count($diff['toUpdate']) > 0
			|| count($diff['toRemove']) > 0;
	}//end hasChanges()

	/**
	 * The reconcilable field snapshot compared for equality and applied
	 * on update. Kept as one array so the equality check
	 * (the diff) and the field-copy
	 * (applyTemplateFields / cloneTemplatePlacementInto)
	 * can never silently drift apart (the field-copy lives in
	 * {@see TemplateResyncService}).
	 *
	 * @param WidgetPlacement $placement The placement to snapshot.
	 *
	 * @return array<string, mixed> The comparable field values.
	 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
	 */
	private function templateFieldSnapshot(WidgetPlacement $placement): array {
		return [
			'widgetId' => $placement->getWidgetId(),
			'gridX' => $placement->getGridX(),
			'gridY' => $placement->getGridY(),
			'gridWidth' => $placement->getGridWidth(),
			'gridHeight' => $placement->getGridHeight(),
			'isCompulsory' => $placement->getIsCompulsory(),
			'isVisible' => $placement->getIsVisible(),
			'styleConfig' => $placement->getStyleConfig(),
			'customTitle' => $placement->getCustomTitle(),
			'customIcon' => $placement->getCustomIcon(),
			'showTitle' => $placement->getShowTitle(),
			'sortOrder' => $placement->getSortOrder(),
			'content' => $placement->getContent(),
			'tileType' => $placement->getTileType(),
			'tileTitle' => $placement->getTileTitle(),
			'tileIcon' => $placement->getTileIcon(),
			'tileIconType' => $placement->getTileIconType(),
			'tileBackgroundColor' => $placement->getTileBackgroundColor(),
			'tileTextColor' => $placement->getTileTextColor(),
			'tileLinkType' => $placement->getTileLinkType(),
			'tileLinkValue' => $placement->getTileLinkValue(),
			'requiresAcknowledgement' => $placement->getRequiresAcknowledgement(),
			'acknowledgementPrompt' => $placement->getAcknowledgementPrompt(),
			'acknowledgementDeadline' => $placement->getAcknowledgementDeadline(),
			'reacknowledgeOnChange' => $placement->getReacknowledgeOnChange(),
			'acknowledgementContentVersion' => $placement->getAcknowledgementContentVersion(),
			'announcementKey' => $placement->getAnnouncementKey(),
			'ruleMatch' => $placement->getRuleMatch(),
		];
	}//end templateFieldSnapshot()
}//end class
