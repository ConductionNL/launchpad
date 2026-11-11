<?php

/**
 * AllowedWidgetFilter
 *
 * Narrows the available widgets to the ones the signed-in user's
 * role-feature-permission profile permits (REQ-RFP-001).
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/role-feature-permissions/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Service\RoleFeaturePermissionService;

/**
 * Narrows the available widgets to the ones a user may add.
 *
 * Built by {@see WidgetApiController::listAvailable()} per request.
 *
 * @spec openspec/specs/role-feature-permissions/spec.md
 */
final class AllowedWidgetFilter {
	/**
	 * Constructor.
	 *
	 * @param RoleFeaturePermissionService $roleFeaturePerm The role-feature permissions.
	 * @param string|null                  $userId          The signed-in user, if any.
	 */
	public function __construct(
		private readonly RoleFeaturePermissionService $roleFeaturePerm,
		private readonly ?string $userId,
	) {
	}//end __construct()

	/**
	 * The widgets the user's role-feature profile permits; every widget
	 * without a user or when nothing is configured (backwards-compat).
	 *
	 * @param array<int, array<string, mixed>> $widgets The available widgets.
	 *
	 * @return array<int, array<string, mixed>> The permitted widgets.
	 *
	 * @spec openspec/specs/role-feature-permissions/spec.md
	 */
	public function filter(array $widgets): array {
		if ($this->userId === null) {
			return $widgets;
		}

		$allowed = $this->roleFeaturePerm->getAllowedWidgetIds(
			userId: $this->userId
		);
		if ($allowed === null) {
			// Backwards-compat: nothing configured, return everything.
			return $widgets;
		}

		return self::widgetsWithIdIn(widgets: $widgets, allowed: $allowed);
	}//end filter()

	/**
	 * The widgets whose non-empty id is in the allowed list, in order.
	 *
	 * @param array<int, array<string, mixed>> $widgets The available widgets.
	 * @param array<int, string>               $allowed The allowed widget ids.
	 *
	 * @return array<int, array<string, mixed>> The kept widgets.
	 */
	private static function widgetsWithIdIn(array $widgets, array $allowed): array {
		$kept = [];
		foreach ($widgets as $widget) {
			$id = (string)($widget['id'] ?? '');
			if ($id !== '' && in_array(needle: $id, haystack: $allowed, strict: true) === true) {
				$kept[] = $widget;
			}
		}

		return $kept;
	}//end widgetsWithIdIn()
}//end class
