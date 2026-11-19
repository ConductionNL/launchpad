<?php

/**
 * DashboardAudience
 *
 * Who may view a dashboard, written the way an OpenRegister RBAC rule can
 * match it: bare user ids, bare group ids, and whether everyone may. It
 * follows {@see \OCA\LaunchPad\Service\PermissionService::resolveAccessLevel()}:
 * a group dashboard is seen by its group (everyone for the default group),
 * a personal dashboard by its owner and by every user and group it is
 * shared with. Administrators are admitted by OpenRegister itself.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\Discussion
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/openregister-leaf-integrations/specs/dashboard-talk-leaf/spec.md#requirement-req-leaf-003-discussion-access-follows-the-existing-view-guard
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\Discussion;

/**
 * The audience of one dashboard, as RBAC-matchable lists.
 */
final class DashboardAudience {

	/**
	 * Constructor.
	 *
	 * @param list<string> $users         User ids that may view it.
	 * @param list<string> $groups        Group ids whose members may view it.
	 * @param bool         $everyone      Whether every signed-in user may view it.
	 * @param bool         $collaborative Whether anyone but its owner may view it.
	 */
	public function __construct(
		public readonly array $users,
		public readonly array $groups,
		public readonly bool $everyone,
		public readonly bool $collaborative,
	) {
	}//end __construct()
}//end class
