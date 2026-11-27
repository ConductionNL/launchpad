<?php

/**
 * AnnouncementAudience
 *
 * Who an announcement is for: the members of its target groups, or, for an
 * announcement to everyone, every user who has logged in. Shared by the
 * Seen and confirmed report and the reminder (REQ-ANN-009).
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
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\Announcement;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;

/**
 * Resolves the audience of an announcement.
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 */
class AnnouncementAudience {

	/**
	 * Constructor.
	 *
	 * @param IGroupManager $groupManager The members of a target group.
	 * @param IUserManager  $userManager  Everyone, for an announcement to all.
	 */
	public function __construct(
		private readonly IGroupManager $groupManager,
		private readonly IUserManager $userManager,
	) {
	}//end __construct()

	/**
	 * The audience, sorted.
	 *
	 * @param Announcement $announcement The announcement.
	 *
	 * @return string[] User ids.
	 *
	 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
	 */
	public function of(Announcement $announcement): array {
		$members = [];
		$targets = $announcement->getTargetGroupList();
		if ($targets === []) {
			$this->userManager->callForSeenUsers(
				callback: static function (IUser $user) use (&$members): bool {
					$members[$user->getUID()] = true;
					return true;
				}
			);
		}

		foreach ($targets as $groupId) {
			foreach ($this->groupManager->get($groupId)?->getUsers() ?? [] as $user) {
				$members[$user->getUID()] = true;
			}
		}

		$audience = array_map('strval', array_keys($members));
		sort($audience);

		return $audience;
	}//end of()
}//end class
