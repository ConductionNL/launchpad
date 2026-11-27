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
	 * The placement fields a draft may change, as the placement update takes them.
	 */
	public const FIELDS = [
		'gridX',
		'gridY',
		'gridWidth',
		'gridHeight',
		'isVisible',
		'showTitle',
		'customTitle',
		'customIcon',
		'styleConfig',
		'content',
		'calculatedFields',
		'tileTitle',
		'tileIcon',
		'tileIconType',
		'tileBackgroundColor',
		'tileTextColor',
		'tileLinkType',
		'tileLinkValue',
		'requiresAcknowledgement',
		'acknowledgementPrompt',
		'acknowledgementDeadline',
		'reacknowledgeOnChange',
		'acknowledgementContentVersion',
		'ruleMatch',
	];

	/**
	 * The acknowledgement fields, which need the acknowledgement permission.
	 */
	private const ACK_FIELDS = [
		'requiresAcknowledgement',
		'acknowledgementPrompt',
		'acknowledgementDeadline',
		'reacknowledgeOnChange',
		'acknowledgementContentVersion',
	];

	/**
	 * Constructor.
	 *
	 * @param WidgetService                $widgets     Reads, adds, changes and removes placements.
	 * @param PermissionService            $permissions The per-change permission checks.
	 * @param RoleFeaturePermissionService $roleWidgets Which widget types a role may add.
	 * @param DashboardService             $dashboards  Stores the grid columns.
	 * @param DashboardVersionService      $versions    Runs the save with its version.
	 * @param WidgetPlacementService       $placements  Checks container nesting.
	 *
	 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
	 */
	public function __construct(
		private readonly WidgetService $widgets,
		private readonly PermissionService $permissions,
		private readonly RoleFeaturePermissionService $roleWidgets,
		private readonly DashboardService $dashboards,
		private readonly DashboardVersionService $versions,
		private readonly WidgetPlacementService $placements,
	) {
	}//end __construct()

	/**
	 * Store a draft: its grid columns and its full placement list.
	 *
	 * @param int                              $dashboardId The dashboard.
	 * @param string                           $userId      The person saving.
	 * @param int|null                         $gridColumns The draft's grid columns, or null to keep them.
	 * @param list<array<string, mixed>>       $draft       The full placement list of the draft.
	 *
	 * @return array{dashboard: Dashboard, placements: list<WidgetPlacement>} The stored dashboard and placements.
	 *
	 * @throws InvalidArgumentException When the draft names a widget of another dashboard or is malformed (400).
	 * @throws ForbiddenException       When one change is not allowed (403); nothing is stored.
	 * @throws DraftConflictException   When a colleague changed a widget of the draft first (409); nothing is stored.
	 *
	 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
	 */
	public function save(int $dashboardId, string $userId, ?int $gridColumns, array $draft): array {
		$plan = $this->plan(dashboardId: $dashboardId, draft: $draft);
		$this->authorize(dashboardId: $dashboardId, userId: $userId, plan: $plan);

		$dashboard = $this->versions->saveWithVersion(
			dashboardId: $dashboardId,
			createdBy: $userId,
			save: fn (): Dashboard => $this->apply(dashboardId: $dashboardId, userId: $userId, gridColumns: $gridColumns, plan: $plan)
		);

		return [
			'dashboard'  => $dashboard,
			'placements' => $this->widgets->getDashboardPlacements(dashboardId: $dashboardId),
		];
	}//end save()

	/**
	 * Sort the draft into added, changed and removed placements.
	 *
	 * @param int                        $dashboardId The dashboard.
	 * @param list<array<string, mixed>> $draft       The full placement list of the draft.
	 *
	 * @return array{add: list<array<string, mixed>>, change: array<int, array<string, mixed>>, remove: list<int>}
	 *
	 * @throws InvalidArgumentException When the draft is malformed or names a widget of another dashboard.
	 *
	 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
	 */
	public function plan(int $dashboardId, array $draft): array {
		$stored = [];
		foreach ($this->widgets->getDashboardPlacements(dashboardId: $dashboardId) as $placement) {
			$stored[(int)$placement->getId()] = $placement;
		}

		$plan = ['add' => [], 'change' => [], 'remove' => []];
		$kept = [];
		foreach ($draft as $item) {
			if (is_array($item) === false) {
				throw new InvalidArgumentException(message: 'Every placement of a draft must be an object');
			}

			if (isset($item['id']) === false || $item['id'] === null || $item['id'] === '') {
				$plan['add'][] = $this->newPlacement(item: $item);
				continue;
			}

			$id = (int)$item['id'];
			if (isset($stored[$id]) === false) {
				throw new InvalidArgumentException(message: 'Widget '.$id.' is not on this dashboard');
			}

			$kept[$id] = true;
			$changes   = self::changedFields(stored: $stored[$id], item: $item);
			if ($changes !== []) {
				$plan['change'][$id] = $changes;
			}
		}//end foreach

		$plan['remove'] = array_values(array_diff(array_keys($stored), array_keys($kept)));

		return $plan;
	}//end plan()

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
		$changes = [];
		foreach (self::FIELDS as $field) {
			if (array_key_exists($field, $item) === false) {
				continue;
			}

			if (array_key_exists($field, $current) === true && self::same(left: $current[$field], right: $item[$field]) === true) {
				continue;
			}

			$changes[$field] = $item[$field];
		}

		if ($changes !== [] && isset($item['version']) === true && is_numeric($item['version']) === true) {
			$changes['version'] = (int)$item['version'];
		}

		return $changes;
	}//end changedFields()

	/**
	 * Whether a stored value and a draft value are the same, comparing
	 * JSON objects by content and numbers by value.
	 *
	 * @param mixed $left  The stored value.
	 * @param mixed $right The draft value.
	 *
	 * @return bool
	 */
	private static function same(mixed $left, mixed $right): bool {
		$normal = static function (mixed $value): mixed {
			if (is_string($value) === true && ($value === '' || in_array($value[0], ['{', '['], true) === true)) {
				$decoded = json_decode(json: $value, associative: true);
				return $decoded ?? $value;
			}

			if (is_bool($value) === true) {
				return (int)$value;
			}

			return $value;
		};

		return $normal($left) == $normal($right);
	}//end same()

	/**
	 * The fields of a new draft placement.
	 *
	 * @param array<string, mixed> $item The draft placement without id.
	 *
	 * @return array<string, mixed> Its widget type, layout and fields.
	 *
	 * @throws InvalidArgumentException When it names no widget type.
	 */
	private function newPlacement(array $item): array {
		$widgetId = $item['widgetId'] ?? null;
		if (is_string($widgetId) === false || $widgetId === '') {
			throw new InvalidArgumentException(message: 'A new widget in a draft needs its widgetId');
		}

		$layout = ['widgetId' => $widgetId, 'isCompulsory' => (int)($item['isCompulsory'] ?? 0)];
		foreach (self::FIELDS as $field) {
			if (array_key_exists($field, $item) === true) {
				$layout[$field] = $item[$field];
			}
		}

		return $layout;
	}//end newPlacement()

	/**
	 * Check every change of the plan before anything is written: removing,
	 * adding (by widget type) and changing (with the acknowledgement and
	 * rule-match fields their own checks), and container nesting.
	 *
	 * @param int                  $dashboardId The dashboard.
	 * @param string               $userId      The person saving.
	 * @param array<string, mixed> $plan        {@see self::plan()}.
	 *
	 * @return void
	 *
	 * @throws ForbiddenException When one change is not allowed.
	 * @throws InvalidArgumentException When a container nests too deep.
	 *
	 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
	 */
	private function authorize(int $dashboardId, string $userId, array $plan): void {
		foreach ($plan['remove'] as $placementId) {
			if ($this->permissions->canRemoveWidget(userId: $userId, placementId: $placementId) === false) {
				throw new ForbiddenException(message: $this->permissions->widgetRefusal(userId: $userId, placementId: $placementId));
			}
		}

		foreach ($plan['add'] as $layout) {
			$this->assertMayAdd(dashboardId: $dashboardId, userId: $userId, layout: $layout);
		}

		foreach ($plan['change'] as $placementId => $changes) {
			$this->assertMayChange(placementId: (int)$placementId, userId: $userId, changes: $changes);
		}
	}//end authorize()

	/**
	 * Refuse a new widget the person may not add, a compulsory one they may
	 * not place, or one whose container nests too deep.
	 *
	 * @param int                  $dashboardId The dashboard.
	 * @param string               $userId      The person saving.
	 * @param array<string, mixed> $layout      The new placement.
	 *
	 * @return void
	 *
	 * @throws ForbiddenException When it may not be added.
	 */
	private function assertMayAdd(int $dashboardId, string $userId, array $layout): void {
		if ($this->permissions->canAddWidget(userId: $userId, dashboardId: $dashboardId) === false
			|| $this->roleWidgets->isWidgetAllowed(userId: $userId, widgetId: $layout['widgetId']) === false
		) {
			throw new ForbiddenException(message: $this->permissions->widgetRefusal(userId: $userId, dashboardId: $dashboardId));
		}

		if ($layout['isCompulsory'] === 1 && $this->permissions->mayPlaceCompulsory(userId: $userId, dashboardId: $dashboardId) === false) {
			throw new ForbiddenException(message: 'Only an administrator may place a required widget');
		}

		// A new widget has no id to check these against yet; they are set on
		// the saved widget through its own form.
		foreach (['calculatedFields', ...self::ACK_FIELDS] as $field) {
			if (empty($layout[$field]) === false) {
				throw new InvalidArgumentException(message: $field.' can be set once the widget is saved');
			}
		}

		$this->assertDepth(fields: $layout);
	}//end assertMayAdd()

	/**
	 * Refuse a change to a widget the person may not edit, an acknowledgement
	 * they may not manage, or a rule match they do not own.
	 *
	 * @param int                  $placementId The placement.
	 * @param string               $userId      The person saving.
	 * @param array<string, mixed> $changes     The changed fields.
	 *
	 * @return void
	 *
	 * @throws ForbiddenException When the change is not allowed.
	 */
	private function assertMayChange(int $placementId, string $userId, array $changes): void {
		if ($this->permissions->canStyleWidget(userId: $userId, placementId: $placementId) === false) {
			throw new ForbiddenException(message: $this->permissions->widgetRefusal(userId: $userId, placementId: $placementId));
		}

		if (array_intersect(self::ACK_FIELDS, array_keys($changes)) !== []
			&& $this->permissions->canManageAcknowledgement(userId: $userId, placementId: $placementId) === false
		) {
			throw new ForbiddenException(message: 'Only an admin or the template owner may set an acknowledgement requirement');
		}

		if (array_key_exists('ruleMatch', $changes) === true) {
			if (in_array($changes['ruleMatch'], WidgetPlacement::RULE_MATCHES, true) === false) {
				throw new InvalidArgumentException(message: 'ruleMatch must be any or all');
			}

			try {
				$this->permissions->verifyPlacementOwnership(userId: $userId, placementId: $placementId);
			} catch (\Exception) {
				throw new ForbiddenException(message: 'Only the dashboard owner or an admin may change how the rules combine');
			}
		}

		if (array_key_exists('calculatedFields', $changes) === true) {
			// Calculated fields are compiled and written to their source by
			// the widget's own save; the draft does not carry them yet.
			throw new InvalidArgumentException(message: 'Calculated fields are saved from the widget form, not with the draft');
		}

		$this->assertDepth(fields: $changes);
	}//end assertMayChange()

	/**
	 * Refuse container content nested deeper than allowed (REQ-CONT-006).
	 *
	 * @param array<string, mixed> $fields The placement fields.
	 *
	 * @return void
	 */
	private function assertDepth(array $fields): void {
		if (isset($fields['content']) === true && is_array($fields['content']) === true) {
			$this->placements->validateContainerDepth(content: $fields['content']);
		}
	}//end assertDepth()

	/**
	 * Write the plan: grid columns, removals, changes, additions. A widget a
	 * colleague changed first is collected; when there is one, the whole
	 * save fails with them so the transaction rolls back.
	 *
	 * @param int                  $dashboardId The dashboard.
	 * @param string               $userId      The person saving.
	 * @param int|null             $gridColumns The grid columns, or null.
	 * @param array<string, mixed> $plan        {@see self::plan()}.
	 *
	 * @return Dashboard The saved dashboard.
	 *
	 * @throws DraftConflictException When a colleague changed a widget of the draft first.
	 */
	private function apply(int $dashboardId, string $userId, ?int $gridColumns, array $plan): Dashboard {
		$data = [];
		if ($gridColumns !== null) {
			$data['gridColumns'] = $gridColumns;
		}

		$dashboard = $this->dashboards->updateDashboard(dashboardId: $dashboardId, userId: $userId, data: $data);

		foreach ($plan['remove'] as $placementId) {
			$this->widgets->removePlacement(placementId: $placementId);
		}

		$stale = [];
		foreach ($plan['change'] as $placementId => $changes) {
			try {
				$this->widgets->updatePlacement(placementId: (int)$placementId, data: $changes);
			} catch (StalePlacementException $e) {
				$stale[] = $e->getCurrent();
			}
		}

		if ($stale !== []) {
			throw new DraftConflictException(current: $stale);
		}

		// Added last, so a new widget finds its spot in the layout as moved
		// and with the removed widgets gone.
		foreach ($plan['add'] as $layout) {
			$this->addPlacement(dashboardId: $dashboardId, layout: $layout);
		}

		return $dashboard;
	}//end apply()

	/**
	 * Add one new placement of the draft: created with its layout and
	 * content, then given the draft's other fields (title, style, tile and
	 * acknowledgement fields), which creation does not take.
	 *
	 * @param int                  $dashboardId The dashboard.
	 * @param array<string, mixed> $layout      {@see self::newPlacement()}.
	 *
	 * @return void
	 */
	private function addPlacement(int $dashboardId, array $layout): void {
		$created = ['isCompulsory' => $layout['isCompulsory']];
		foreach (['gridX', 'gridY', 'gridWidth', 'gridHeight', 'content'] as $field) {
			if (array_key_exists($field, $layout) === true) {
				$created[$field] = $layout[$field];
			}
		}

		$placement = $this->widgets->addWidget(dashboardId: $dashboardId, widgetId: $layout['widgetId'], layout: $created);

		$rest = array_diff_key($layout, $created, ['widgetId' => true]);
		if ($rest !== []) {
			$this->widgets->updatePlacement(placementId: (int)$placement->getId(), data: $rest);
		}
	}//end addPlacement()
}//end class
