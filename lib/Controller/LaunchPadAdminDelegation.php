<?php

/**
 * LaunchPadAdminDelegation
 *
 * Whether LaunchPad's admin settings are delegated to a user.
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
 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-1
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\IUserManager;
use OCP\Settings\IManager as ISettingsManager;

/**
 * Answers whether a non-admin passes `#[AuthorizedAdminSetting(LaunchPadAdmin)]`:
 * Nextcloud lets a user through when an admin delegated that setting to one
 * of their groups.
 *
 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-1
 */
class LaunchPadAdminDelegation {
	/**
	 * The admin settings section LaunchPad's settings live in.
	 *
	 * @var string
	 */
	private const SETTINGS_SECTION = 'launchpad';

	/**
	 * Constructor.
	 *
	 * @param IUserManager     $userManager     Resolves the user.
	 * @param ISettingsManager $settingsManager The admin settings the user may use.
	 */
	public function __construct(
		private readonly IUserManager $userManager,
		private readonly ISettingsManager $settingsManager,
	) {
	}//end __construct()

	/**
	 * Whether LaunchPad's admin settings are delegated to the user.
	 *
	 * @param string $userId The user's uid.
	 *
	 * @return bool True when the user may use them.
	 *
	 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-1
	 */
	public function isDelegated(string $userId): bool {
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return false;
		}

		$allowed = $this->settingsManager->getAllowedAdminSettings(section: self::SETTINGS_SECTION, user: $user);
		foreach ($allowed as $settings) {
			$found = array_filter(array: $settings, callback: static fn (object $setting): bool => $setting instanceof LaunchPadAdmin);
			if ($found !== []) {
				return true;
			}
		}

		return false;
	}//end isDelegated()
}//end class
