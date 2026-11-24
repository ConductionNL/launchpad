<?php

/**
 * ViewAsGuard
 *
 * Who may preview a widget's rules as another person ("View as" in the
 * rules dialog, REQ-CVUI-006): someone who may edit that placement's rules
 * (the dashboard owner or an administrator). Viewing as yourself, or as
 * no one, is always allowed. A person who does not exist is in no group and
 * has no profile values, so viewing as them shows nothing about anyone.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use Exception;

/**
 * Decides whether a caller may view a placement's rules as a person.
 *
 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
 */
class ViewAsGuard {
	/**
	 * Refused: the caller may not edit the placement's rules.
	 */
	public const FORBIDDEN = 'You may not view this widget as another person';

	/**
	 * Constructor
	 *
	 * @param PermissionService $permissionService Who may edit a placement's rules.
	 * @param string|null       $userId            The signed-in user.
	 */
	public function __construct(
		private readonly PermissionService $permissionService,
		private readonly ?string $userId,
	) {
	}//end __construct()

	/**
	 * Why the signed-in user may not view as the person, or null when they may.
	 *
	 * @param string|null $subject     The person to view as; null for no one.
	 * @param int|null    $placementId The placement whose rules are edited.
	 *
	 * @return string|null `FORBIDDEN`, or null.
	 *
	 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
	 */
	public function refusal(?string $subject, ?int $placementId): ?string {
		if ($subject === null || $subject === $this->userId) {
			return null;
		}

		if ($this->userId === null || $placementId === null || $this->mayEditRules(placementId: $placementId) === false) {
			return self::FORBIDDEN;
		}

		return null;
	}//end refusal()

	/**
	 * Whether the caller may edit the rules of a placement: its dashboard's
	 * owner or an administrator, the same check the rule endpoints make.
	 *
	 * @param int $placementId The placement.
	 *
	 * @return bool True when allowed.
	 */
	private function mayEditRules(int $placementId): bool {
		try {
			$this->permissionService->verifyPlacementOwnership(userId: (string)$this->userId, placementId: $placementId);
			return true;
		} catch (Exception $e) {
			return false;
		}
	}//end mayEditRules()
}//end class
