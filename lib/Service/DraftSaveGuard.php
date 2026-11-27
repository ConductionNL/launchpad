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
	 *
	 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
	 */
	public function __construct(
		private readonly PermissionService $permissions,
		private readonly RoleFeaturePermissionService $roleWidgets,
		private readonly WidgetPlacementService $placements,
	) {
	}//end __construct()

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
	public function authorize(int $dashboardId, string $userId, array $plan): void {
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
		foreach (['calculatedFields', ...WidgetPlacement::ACKNOWLEDGEMENT_FIELDS] as $field) {
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

		if (array_intersect(WidgetPlacement::ACKNOWLEDGEMENT_FIELDS, array_keys($changes)) !== []
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
}//end class
