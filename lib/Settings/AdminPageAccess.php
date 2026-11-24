<?php

/**
 * AdminPageAccess
 *
 * Answers whether the signed-in user may open Launchpad's administration
 * page: a Nextcloud administrator, or a user Nextcloud's admin delegation
 * grants the Launchpad settings ({@see LaunchPadAdmin}).
 *
 * @category  Settings
 * @package   OCA\LaunchPad\Settings
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * @spec openspec/changes/admin-full-width-page/specs/admin-settings/spec.md#requirement-the-administration-is-a-launchpad-page-over-the-full-width-req-adm-030
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
 * @spec openspec/changes/admin-full-width-page/specs/admin-settings/spec.md#requirement-the-administration-is-a-launchpad-page-over-the-full-width-req-adm-030
 */
class AdminPageAccess {
	/**
	 * Constructor.
	 *
	 * @param IUserSession  $userSession     The session of the request.
	 * @param IGroupManager $groupManager    Answers whether a user is an administrator.
	 * @param IManager      $settingsManager Answers which admin settings a delegated administrator holds.
	 *
	 * @spec openspec/changes/admin-full-width-page/specs/admin-settings/spec.md#requirement-the-administration-is-a-launchpad-page-over-the-full-width-req-adm-030
	 */
	public function __construct(
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
		private readonly IManager $settingsManager,
	) {
	}//end __construct()

	/**
	 * Whether the signed-in user is an administrator or a delegated
	 * Launchpad administrator.
	 *
	 * @return bool True when the page may render.
	 *
	 * @spec openspec/changes/admin-full-width-page/specs/admin-settings/spec.md#requirement-the-administration-is-a-launchpad-page-over-the-full-width-req-adm-030
	 */
	public function allows(): bool {
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
				if ($setting instanceof LaunchPadAdmin) {
					return true;
				}
			}
		}

		return false;
	}//end allows()
}//end class
