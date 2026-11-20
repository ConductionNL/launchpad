<?php

/**
 * DashboardAudienceDeriver
 *
 * Derives who may view a dashboard as RBAC-matchable lists, the way
 * {@see \OCA\LaunchPad\Service\PermissionService::resolveAccessLevel()}
 * decides view access: a group dashboard is seen by its group (everyone for
 * the default group), a personal dashboard by its owner and by every user
 * and group it is shared with, an admin template by nobody here.
 * Administrators are admitted by OpenRegister itself.
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

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardShare;

/**
 * Derives a dashboard's audience from its type, owner and shares.
 */
class DashboardAudienceDeriver {

	/**
	 * The audience of a dashboard with its shares.
	 *
	 * @param Dashboard            $dashboard The dashboard.
	 * @param list<DashboardShare> $shares    Its user and group shares.
	 *
	 * @return DashboardAudience
	 *
	 * @spec openspec/changes/openregister-leaf-integrations/tasks.md#task-7
	 */
	public function derive(Dashboard $dashboard, array $shares): DashboardAudience {
		if ($dashboard->getType() === Dashboard::TYPE_GROUP_SHARED) {
			return $this->ofGroup(groupId: (string)$dashboard->getGroupId());
		}

		if ($dashboard->getType() === Dashboard::TYPE_ADMIN_TEMPLATE) {
			return new DashboardAudience(users: [], groups: [], everyone: false, collaborative: false);
		}

		$users  = [];
		$groups = [];
		$owner  = (string)$dashboard->getUserId();
		if ($owner !== '') {
			$users[] = $owner;
		}

		foreach ($shares as $share) {
			$with = (string)$share->getShareWith();
			if ($with === '') {
				continue;
			}

			if ($share->getShareType() === DashboardShare::SHARE_TYPE_GROUP) {
				$groups[] = $with;
				continue;
			}

			$users[] = $with;
		}

		$users  = array_values(array_unique($users));
		$groups = array_values(array_unique($groups));

		return new DashboardAudience(
			users: $users,
			groups: $groups,
			everyone: false,
			collaborative: ($groups !== [] || count($users) > 1)
		);
	}//end of()

	/**
	 * The audience of a group dashboard.
	 *
	 * @param string $groupId The dashboard's group.
	 *
	 * @return DashboardAudience
	 */
	private function ofGroup(string $groupId): DashboardAudience {
		if ($groupId === Dashboard::DEFAULT_GROUP_ID) {
			return new DashboardAudience(users: [], groups: [], everyone: true, collaborative: true);
		}

		$groups = [];
		if ($groupId !== '') {
			$groups[] = $groupId;
		}

		return new DashboardAudience(users: [], groups: $groups, everyone: false, collaborative: true);
	}//end ofGroup()
}//end class
