<?php

/**
 * DraftSaveService
 *
 * Stores an edit-mode draft in one go (edit-mode-draft-and-save, design D2).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Exception\DraftConflictException;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Exception\StalePlacementException;

/**
 * Applies a whole draft (grid columns plus the full placement list) in one
 * transaction with its version: every change is checked against the
 * person's permission first, and nothing is stored when one is refused or
 * fails (REQ-EDIT-002, REQ-VERS-010).
 *
 * A placement in the list with an `id` is kept (and changed when a field
 * differs from what is stored), one without an `id` is added, and a stored
 * placement missing from the list is removed.
 *
 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
 */
class DraftSaveService {
	/**
	 * A tile's placement fields and the keys the tile route takes them by.
	 */
	private const TILE_KEYS = [
		'tileTitle'           => 'title',
		'tileIcon'            => 'icon',
		'tileIconType'        => 'iconType',
		'tileBackgroundColor' => 'bgColor',
		'tileTextColor'       => 'txtColor',
		'tileLinkType'        => 'linkType',
		'tileLinkValue'       => 'linkVal',
	];

	/**
	 * Constructor.
	 *
	 * @param WidgetService           $widgets    Reads, adds, changes and removes placements.
	 * @param DraftSaveGuard          $guard      Checks every change before anything is written.
	 * @param DashboardService        $dashboards Stores the grid columns.
	 * @param DashboardVersionService $versions   Runs the save with its version.
	 *
	 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
	 */
	public function __construct(
		private readonly WidgetService $widgets,
		private readonly DraftSaveGuard $guard,
		private readonly DashboardService $dashboards,
		private readonly DashboardVersionService $versions,
	) {
	}//end __construct()

	/**
	 * Store a draft: its grid columns and its full placement list.
	 *
	 * @param DashboardDraft $draft The draft.
	 *
	 * @return array{dashboard: Dashboard, placements: list<WidgetPlacement>, removed: list<WidgetPlacement>}
	 *         The stored dashboard and placements, and the removed widgets as they were stored.
	 *
	 * @throws InvalidArgumentException When the draft names a widget of another dashboard or is malformed (400).
	 * @throws ForbiddenException       When one change is not allowed (403); nothing is stored.
	 * @throws DraftConflictException   When a colleague changed a widget of the draft first (409); nothing is stored.
	 *
	 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
	 */
	public function save(DashboardDraft $draft): array {
		$plan = $this->plan(draft: $draft);
		$this->guard->authorize(draft: $draft, plan: $plan);
		$removed = $this->storedPlacements(dashboardId: $draft->dashboardId, placementIds: $plan['remove']);

		$dashboard = $this->versions->saveWithVersion(
			dashboardId: $draft->dashboardId,
			createdBy: $draft->userId,
			save: fn (): Dashboard => $this->apply(draft: $draft, plan: $plan)
		);

		return [
			'dashboard'  => $dashboard,
			'placements' => $this->widgets->getDashboardPlacements(dashboardId: $draft->dashboardId),
			'removed'    => $removed,
		];
	}//end save()

	/**
	 * The stored placements of a dashboard with the given ids, read before
	 * the save removes them so their content can still be cleaned up after.
	 *
	 * @param int       $dashboardId  The dashboard.
	 * @param list<int> $placementIds The placement ids.
	 *
	 * @return list<WidgetPlacement>
	 *
	 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
	 */
	private function storedPlacements(int $dashboardId, array $placementIds): array {
		if ($placementIds === []) {
			return [];
		}

		return array_values(
			array_filter(
				$this->widgets->getDashboardPlacements(dashboardId: $dashboardId),
				static fn (WidgetPlacement $placement): bool => in_array((int) $placement->getId(), $placementIds, true)
			)
		);
	}//end storedPlacements()

	/**
	 * Sort the draft into added, changed and removed placements.
	 *
	 * @param DashboardDraft $draft The draft.
	 *
	 * @return array{add: list<array<string, mixed>>, change: array<int, array<string, mixed>>, remove: list<int>}
	 *
	 * @throws InvalidArgumentException When the draft is malformed or names a widget of another dashboard.
	 *
	 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
	 */
	public function plan(DashboardDraft $draft): array {
		$stored = [];
		foreach ($this->widgets->getDashboardPlacements(dashboardId: $draft->dashboardId) as $placement) {
			$stored[(int)$placement->getId()] = $placement;
		}

		$plan = ['add' => [], 'change' => [], 'remove' => []];
		$kept = [];
		foreach ($draft->placements as $item) {
			$id = self::draftId(item: $item);
			if ($id === null) {
				$plan['add'][] = self::newPlacement(item: $item);
				continue;
			}

			if (isset($stored[$id]) === false) {
				throw new InvalidArgumentException(message: 'Widget '.$id.' is not on this dashboard');
			}

			$kept[$id] = true;
			$plan['change'][$id] = self::changedFields(stored: $stored[$id], item: $item);
		}

		$plan['change'] = array_filter($plan['change'], static fn (array $changes): bool => $changes !== []);
		$plan['remove'] = array_values(array_diff(array_keys($stored), array_keys($kept)));

		return $plan;
	}//end plan()

	/**
	 * The stored id a draft placement names, or null for a new one.
	 *
	 * @param mixed $item The draft placement.
	 *
	 * @return int|null
	 *
	 * @throws InvalidArgumentException When it is not an object.
	 */
	private static function draftId(mixed $item): ?int {
		if (is_array($item) === false) {
			throw new InvalidArgumentException(message: 'Every placement of a draft must be an object');
		}

		if (isset($item['id']) === false || $item['id'] === '') {
			return null;
		}

		return (int)$item['id'];
	}//end draftId()

	/**
	 * The fields of a draft placement that differ from the stored one, with
	 * the version the draft started from when it names one (co-editing).
	 *
	 * @param WidgetPlacement      $stored The stored placement.
	 * @param array<string, mixed> $item   The draft placement.
	 *
	 * @return array<string, mixed> The changed fields; empty when nothing changed.
	 *
	 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
	 */
	public static function changedFields(WidgetPlacement $stored, array $item): array {
		$current = $stored->jsonSerialize();
		$changes = array_filter(
			array_intersect_key($item, array_flip(WidgetPlacement::EDITABLE_FIELDS)),
			static fn (mixed $value, string $field): bool => array_key_exists($field, $current) === false
				|| self::normal(value: $current[$field]) != self::normal(value: $value),
			ARRAY_FILTER_USE_BOTH
		);

		if ($changes !== [] && is_numeric($item['version'] ?? null) === true) {
			$changes['version'] = (int)$item['version'];
		}

		return $changes;
	}//end changedFields()

	/**
	 * A value as compared: JSON text decoded, booleans as 0 or 1.
	 *
	 * @param mixed $value The stored or draft value.
	 *
	 * @return mixed
	 */
	private static function normal(mixed $value): mixed {
		if (is_bool($value) === true) {
			return (int)$value;
		}

		if (is_string($value) === true && in_array(substr($value, 0, 1), ['{', '['], true) === true) {
			return json_decode(json: $value, associative: true) ?? $value;
		}

		return $value;
	}//end normal()

	/**
	 * The fields of a new draft placement.
	 *
	 * @param array<string, mixed> $item The draft placement without id.
	 *
	 * @return array<string, mixed> Its widget type, compulsory flag and fields.
	 *
	 * @throws InvalidArgumentException When it names no widget type.
	 */
	private static function newPlacement(array $item): array {
		$widgetId = $item['widgetId'] ?? null;
		if (is_string($widgetId) === false || $widgetId === '') {
			throw new InvalidArgumentException(message: 'A new widget in a draft needs its widgetId');
		}

		return ['widgetId' => $widgetId, 'isCompulsory' => (int)($item['isCompulsory'] ?? 0)]
			+ array_intersect_key($item, array_flip(WidgetPlacement::EDITABLE_FIELDS));
	}//end newPlacement()

	/**
	 * Write the plan: grid columns, removals, changes, additions. A widget a
	 * colleague changed first is collected; when there is one, the whole
	 * save fails with them so the transaction rolls back.
	 *
	 * @param DashboardDraft       $draft The draft.
	 * @param array<string, mixed> $plan  {@see self::plan()}.
	 *
	 * @return Dashboard The saved dashboard.
	 *
	 * @throws DraftConflictException When a colleague changed a widget of the draft first.
	 */
	private function apply(DashboardDraft $draft, array $plan): Dashboard {
		$data = [];
		if ($draft->gridColumns !== null) {
			$data['gridColumns'] = $draft->gridColumns;
		}

		$dashboard = $this->dashboards->updateDashboard(dashboardId: $draft->dashboardId, userId: $draft->userId, data: $data);

		foreach ($plan['remove'] as $placementId) {
			$this->widgets->removePlacement(placementId: $placementId);
		}

		$this->applyChanges(changes: $plan['change']);

		// Added last, so a new widget finds its spot in the layout as moved
		// and with the removed widgets gone.
		foreach ($plan['add'] as $layout) {
			$this->addPlacement(dashboardId: $draft->dashboardId, layout: $layout);
		}

		return $dashboard;
	}//end apply()

	/**
	 * Change the placements; widgets a colleague changed first fail the save
	 * together.
	 *
	 * @param array<int, array<string, mixed>> $changes The changed fields by placement id.
	 *
	 * @return void
	 *
	 * @throws DraftConflictException When a colleague changed a widget of the draft first.
	 */
	private function applyChanges(array $changes): void {
		$stale = [];
		foreach ($changes as $placementId => $fields) {
			try {
				$this->widgets->updatePlacement(placementId: (int)$placementId, data: $fields);
			} catch (StalePlacementException $e) {
				$stale[] = $e->getCurrent();
			}
		}

		if ($stale !== []) {
			throw new DraftConflictException(current: $stale);
		}
	}//end applyChanges()

	/**
	 * Add one new placement of the draft: created with its layout and
	 * content, then given the draft's other fields (title, style, tile
	 * fields), which creation does not take.
	 *
	 * @param int                  $dashboardId The dashboard.
	 * @param array<string, mixed> $layout      {@see self::newPlacement()}.
	 *
	 * @return void
	 */
	private function addPlacement(int $dashboardId, array $layout): void {
		if ($layout['widgetId'] === 'tile') {
			$this->addTile(dashboardId: $dashboardId, layout: $layout);
			return;
		}

		$created   = array_intersect_key($layout, array_flip(['isCompulsory', 'gridX', 'gridY', 'gridWidth', 'gridHeight', 'content']));
		$placement = $this->widgets->addWidget(dashboardId: $dashboardId, widgetId: $layout['widgetId'], layout: $created);

		$rest = array_diff_key($layout, $created, ['widgetId' => true]);
		if ($rest !== []) {
			$this->widgets->updatePlacement(placementId: (int)$placement->getId(), data: $rest);
		}
	}//end addPlacement()

	/**
	 * Add a tile of the draft the way the tile route does (a custom tile with
	 * its launch link checked), then its other fields.
	 *
	 * @param int                  $dashboardId The dashboard.
	 * @param array<string, mixed> $layout      {@see self::newPlacement()} of a `tile`.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
	 */
	private function addTile(int $dashboardId, array $layout): void {
		$tileData = array_intersect_key($layout, array_flip(['gridX', 'gridY', 'gridWidth', 'gridHeight', 'content']));
		foreach (self::TILE_KEYS as $field => $key) {
			if (array_key_exists($field, $layout) === true) {
				$tileData[$key] = $layout[$field];
			}
		}

		$placement = $this->widgets->addTileFromArray(dashboardId: $dashboardId, tileData: $tileData);

		$rest = array_diff_key($layout, $tileData, self::TILE_KEYS, ['widgetId' => true, 'isCompulsory' => true, 'content' => true]);
		if ($rest !== []) {
			$this->widgets->updatePlacement(placementId: (int)$placement->getId(), data: $rest);
		}
	}//end addTile()
}//end class
