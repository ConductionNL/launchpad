<?php

/**
 * GroupDashboardCreation
 *
 * Who may create a group dashboard for a group (REQ-DASH-014).
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
 * @spec openspec/specs/dashboards/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\RoleAssignment;

/**
 * An Admin (a Nextcloud administrator is one) may create a group dashboard
 * for any group, an Editor for a group they belong to. The default group
 * means everyone, so an Editor cannot aim a dashboard at it.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
class GroupDashboardCreation {
	/**
	 * Constructor.
	 *
	 * @param RoleService          $roles     Resolves the effective LaunchPad role.
	 * @param AdminTemplateService $templates Resolves a user's groups (REQ-TMPL-013).
	 */
	public function __construct(
		private readonly RoleService $roles,
		private readonly AdminTemplateService $templates,
	) {
	}//end __construct()

	/**
	 * Whether a user may create a group dashboard for a group.
	 *
	 * @param string $userId  The user ID.
	 * @param string $groupId The group the dashboard is for.
	 *
	 * @return bool Whether the user may create it.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function allows(string $userId, string $groupId): bool {
		$role = $this->roles->getEffectiveRole(userId: $userId);
		if ($role === RoleAssignment::ROLE_ADMIN) {
			return true;
		}

		if ($role !== RoleAssignment::ROLE_EDITOR || $groupId === Dashboard::DEFAULT_GROUP_ID) {
			return false;
		}

		return in_array(
			needle: $groupId,
			haystack: $this->templates->getUserGroupIdsFor(userId: $userId),
			strict: true
		);
	}//end allows()
}//end class
