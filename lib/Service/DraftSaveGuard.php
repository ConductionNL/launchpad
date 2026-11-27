<?php

/**
 * DraftSaveGuard
 *
 * Checks every change of an edit-mode draft before anything is written
 * (edit-mode-draft-and-save, design D2).
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
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Exception\ForbiddenException;

/**
 * The per-change checks of a draft save: the same permissions the
 * per-widget routes ask, so one refused change refuses the whole save.
 *
 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
 */
class DraftSaveGuard {
	/**
	 * Constructor.
	 *
	 * @param PermissionService            $permissions The per-change permission checks.
	 * @param RoleFeaturePermissionService $roleWidgets Which widget types a role may add.
	 * @param WidgetPlacementService       $placements  Checks container nesting.
	 * @param DraftRules                   $rules       Reads, checks and stores the draft's visibility rules.
	 *
	 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
	 */
	public function __construct(
		private readonly PermissionService $permissions,
		private readonly RoleFeaturePermissionService $roleWidgets,
		private readonly WidgetPlacementService $placements,
		public readonly DraftRules $rules,
	) {
	}//end __construct()

	/**
	 * Check every change of the plan before anything is written: removing,
	 * adding and changing, each with the checks of its own route.
	 *
	 * @param DashboardDraft       $draft The draft.
	 * @param array<string, mixed> $plan  {@see DraftSaveService::plan()}.
	 *
	 * @return void
	 *
	 * @throws ForbiddenException       When one change is not allowed.
	 * @throws InvalidArgumentException When one change is malformed.
	 *
	 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
	 */
	public function authorize(DashboardDraft $draft, array $plan): void {
		foreach ($plan['remove'] as $placementId) {
			$this->assertMayRemove(userId: $draft->userId, placementId: $placementId);
		}

		foreach ($plan['add'] as $layout) {
			$this->assertMayAdd(draft: $draft, layout: $layout);
		}

		foreach ($plan['change'] as $placementId => $changes) {
			$this->assertMayChange(userId: $draft->userId, change: ['id' => (int)$placementId, 'fields' => $changes]);
		}

		foreach (array_keys($plan['rules'] ?? []) as $placementId) {
			$this->rules->assertMayReplace(userId: $draft->userId, placementId: (int)$placementId);
		}
	}//end authorize()

	/**
	 * Refuse removing a widget the person may not remove (a compulsory one).
	 *
	 * @param string $userId      The person saving.
	 * @param int    $placementId The placement.
	 *
	 * @return void
	 *
	 * @throws ForbiddenException When it may not be removed.
	 */
	private function assertMayRemove(string $userId, int $placementId): void {
		if ($this->permissions->canRemoveWidget(userId: $userId, placementId: $placementId) === false) {
			throw new ForbiddenException(message: $this->permissions->widgetRefusal(userId: $userId, placementId: $placementId));
		}
	}//end assertMayRemove()

	/**
	 * Refuse a new widget the person may not add, a compulsory one they may
	 * not place, fields that need a saved widget, or a container nested too
	 * deep.
	 *
	 * @param DashboardDraft       $draft  The draft.
	 * @param array<string, mixed> $layout The new placement.
	 *
	 * @return void
	 *
	 * @throws ForbiddenException       When it may not be added.
	 * @throws InvalidArgumentException When it carries fields that need a saved widget.
	 */
	private function assertMayAdd(DashboardDraft $draft, array $layout): void {
		if ($this->permissions->canAddWidget(userId: $draft->userId, dashboardId: $draft->dashboardId) === false
			|| $this->roleWidgets->isWidgetAllowed(userId: $draft->userId, widgetId: $layout['widgetId']) === false
		) {
			throw new ForbiddenException(message: $this->permissions->widgetRefusal(userId: $draft->userId, dashboardId: $draft->dashboardId));
		}

		if ($layout['isCompulsory'] === 1
			&& $this->permissions->mayPlaceCompulsory(userId: $draft->userId, dashboardId: $draft->dashboardId) === false
		) {
			throw new ForbiddenException(message: 'Only an administrator may place a required widget');
		}

		// A new widget has no id to check these against yet; they are set on
		// the saved widget through its own form.
		$later = array_filter(
			array_intersect_key($layout, array_flip(['calculatedFields', ...WidgetPlacement::ACKNOWLEDGEMENT_FIELDS])),
			static fn (mixed $value): bool => empty($value) === false
		);
		if ($later !== []) {
			throw new InvalidArgumentException(message: array_key_first($later).' can be set once the widget is saved');
		}

		$this->assertDepth(fields: $layout);
	}//end assertMayAdd()

	/**
	 * Refuse a change to a widget the person may not edit, an acknowledgement
	 * they may not manage, a rule match they do not own, or calculated fields.
	 *
	 * @param string                                          $userId The person saving.
	 * @param array{id: int, fields: array<string, mixed>}    $change The placement and its changed fields.
	 *
	 * @return void
	 *
	 * @throws ForbiddenException       When the change is not allowed.
	 * @throws InvalidArgumentException When the change is malformed.
	 */
	private function assertMayChange(string $userId, array $change): void {
		if ($this->permissions->canStyleWidget(userId: $userId, placementId: $change['id']) === false) {
			throw new ForbiddenException(message: $this->permissions->widgetRefusal(userId: $userId, placementId: $change['id']));
		}

		if (array_intersect(WidgetPlacement::ACKNOWLEDGEMENT_FIELDS, array_keys($change['fields'])) !== []
			&& $this->permissions->canManageAcknowledgement(userId: $userId, placementId: $change['id']) === false
		) {
			throw new ForbiddenException(message: 'Only an admin or the template owner may set an acknowledgement requirement');
		}

		if (array_key_exists('calculatedFields', $change['fields']) === true) {
			// Calculated fields are compiled and written to their source by
			// the widget's own save; the draft does not carry them yet.
			throw new InvalidArgumentException(message: 'Calculated fields are saved from the widget form, not with the draft');
		}

		$this->assertRuleMatch(userId: $userId, change: $change);
		$this->assertDepth(fields: $change['fields']);
	}//end assertMayChange()

	/**
	 * Refuse a rule match that is not any or all, or one set by someone who
	 * does not own the widget's dashboard (REQ-VIS-014).
	 *
	 * @param string                                       $userId The person saving.
	 * @param array{id: int, fields: array<string, mixed>} $change The placement and its changed fields.
	 *
	 * @return void
	 *
	 * @throws ForbiddenException       When they may not change it.
	 * @throws InvalidArgumentException When the value is not any or all.
	 */
	private function assertRuleMatch(string $userId, array $change): void {
		if (array_key_exists('ruleMatch', $change['fields']) === false) {
			return;
		}

		if (in_array($change['fields']['ruleMatch'], WidgetPlacement::RULE_MATCHES, true) === false) {
			throw new InvalidArgumentException(message: 'ruleMatch must be any or all');
		}

		try {
			$this->permissions->verifyPlacementOwnership(userId: $userId, placementId: $change['id']);
		} catch (\Exception) {
			throw new ForbiddenException(message: 'Only the dashboard owner or an admin may change how the rules combine');
		}
	}//end assertRuleMatch()

	/**
	 * Refuse container content nested deeper than allowed (REQ-CONT-006).
	 *
	 * @param array<string, mixed> $fields The placement fields.
	 *
	 * @return void
	 */
	private function assertDepth(array $fields): void {
		if (is_array($fields['content'] ?? null) === true) {
			$this->placements->validateContainerDepth(content: $fields['content']);
		}
	}//end assertDepth()
}//end class
