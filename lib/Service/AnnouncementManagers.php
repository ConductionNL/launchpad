<?php

/**
 * AnnouncementManagers
 *
 * Who may manage announcements (announcements REQ-ANN-001, admin-settings
 * REQ-ADM-034): a Nextcloud administrator, a delegated Launchpad
 * administrator ({@see LaunchPadAdmin}) and a person to whom Nextcloud's admin
 * delegation gives the announcements setting
 * ({@see LaunchPadAnnouncementsAdmin}). Nextcloud's delegation is the one
 * authority; the server checks it before every management operation.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\AdminSettingKey;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCA\LaunchPad\Settings\LaunchPadAnnouncementsAdmin;
use OCP\IUserManager;
use OCP\Settings\IManager;

/**
 * Answers whether a user manages announcements.
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
 */
class AnnouncementManagers {
	/**
	 * Constructor.
	 *
	 * @param IUserManager         $userManager     Finds the user.
	 * @param IManager             $settingsManager Answers which admin settings a user holds by delegation.
	 * @param AdminSettingMapper   $settings        Reads the former editor groups.
	 * @param AdminTemplateService $userGroups      The user's groups.
	 *
	 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function __construct(
		private readonly IUserManager $userManager,
		private readonly IManager $settingsManager,
		private readonly AdminSettingMapper $settings,
		private readonly AdminTemplateService $userGroups,
	) {
	}//end __construct()

	/**
	 * Whether a user manages announcements.
	 *
	 * @param string $userId The user.
	 *
	 * @return bool True for a holder of either Launchpad setting, or a member of a former editor group.
	 *
	 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function manages(string $userId): bool {
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return false;
		}

		foreach ($this->settingsManager->getAllowedAdminSettings(section: 'launchpad', user: $user) as $settings) {
			foreach ($settings as $setting) {
				if ($setting instanceof LaunchPadAnnouncementsAdmin || $setting instanceof LaunchPadAdmin) {
					return true;
				}
			}
		}

		$editors = $this->settings->getValue(key: AdminSettingKey::ANNOUNCEMENT_EDITOR_GROUPS->value, default: []);
		if (is_array($editors) === false || $editors === []) {
			return false;
		}

		return array_intersect($editors, $this->userGroups->getUserGroupIdsFor($userId)) !== [];
	}//end manages()
}//end class
