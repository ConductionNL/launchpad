<?php

/**
 * AdminPageAccess
 *
 * Answers whether the signed-in user may open Launchpad's administration
 * page at `/settings/admin/launchpad`, and which of its tabs: a Nextcloud
 * administrator or a user Nextcloud's admin delegation grants the Launchpad
 * settings ({@see LaunchPadAdmin}) holds every tab; a user it grants only
 * the announcements setting ({@see LaunchPadAnnouncementsAdmin}) holds the
 * Announcements tab (REQ-ADM-034).
 *
 * @category  Settings
 * @package   OCA\LaunchPad\Settings
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-delegated-admin-settings-per-area-req-adm-034
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Settings;

use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\Settings\IManager;

/**
 * The server-side check of the administration page.
 *
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-032
 */
class AdminPageAccess {
	/**
	 * Constructor.
	 *
	 * @param IUserSession  $userSession     The session of the request.
	 * @param IGroupManager $groupManager    Answers whether a user is an administrator.
	 * @param IManager      $settingsManager Answers which admin settings a delegated administrator holds.
	 *
	 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-032
	 */
	public function __construct(
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
		private readonly IManager $settingsManager,
	) {
	}//end __construct()

	/**
	 * Whether the signed-in user is an administrator or a delegated
	 * Launchpad administrator: holds the whole page ({@see LaunchPadAdmin}).
	 *
	 * @return bool True when every tab may render.
	 *
	 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-delegated-admin-settings-per-area-req-adm-034
	 */
	public function allows(): bool {
		return $this->holds(class: LaunchPadAdmin::class);
	}//end allows()

	/**
	 * Whether the signed-in user may manage announcements: an administrator,
	 * a delegated Launchpad administrator, or a person Nextcloud's admin
	 * delegation gives {@see LaunchPadAnnouncementsAdmin}.
	 *
	 * @return bool True when the Announcements tab may render.
	 *
	 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-delegated-admin-settings-per-area-req-adm-034
	 */
	public function allowsAnnouncements(): bool {
		return $this->allows() === true || $this->holds(class: LaunchPadAnnouncementsAdmin::class) === true;
	}//end allowsAnnouncements()

	/**
	 * The tabs of the admin settings page the signed-in user may open: every
	 * tab for a holder of the whole page, only Announcements for a person who
	 * holds only the announcements setting, none for anybody else.
	 *
	 * @return list<string> Tab ids, in the page's order.
	 *
	 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-delegated-admin-settings-per-area-req-adm-034
	 */
	public function tabs(): array {
		if ($this->allows() === true) {
			return AdminTabs::TABS;
		}

		if ($this->holds(class: LaunchPadAnnouncementsAdmin::class) === true) {
			return [AdminTabs::ANNOUNCEMENTS];
		}

		return [];
	}//end tabs()

	/**
	 * Whether the signed-in user holds a Launchpad admin settings class: an
	 * administrator holds every class, anybody else the ones Nextcloud's admin
	 * delegation gives them.
	 *
	 * @param string $class The settings class.
	 *
	 * @return bool True when held.
	 *
	 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-delegated-admin-settings-per-area-req-adm-034
	 */
	private function holds(string $class): bool {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return false;
		}

		if ($this->groupManager->isAdmin(userId: $user->getUID()) === true) {
			return true;
		}

		$allowed = $this->settingsManager->getAllowedAdminSettings(section: 'launchpad', user: $user);
		foreach ($allowed as $settings) {
			foreach ($settings as $setting) {
				if ($setting instanceof $class) {
					return true;
				}
			}
		}

		return false;
	}//end holds()
}//end class
