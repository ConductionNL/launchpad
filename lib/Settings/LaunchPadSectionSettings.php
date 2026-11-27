<?php

/**
 * LaunchPadSectionSettings
 *
 * What Launchpad's delegable admin settings classes share: they sit in
 * Launchpad's admin section and delegate no app config keys of their own
 * (REQ-ADM-034). {@see LaunchPadAdmin} is the whole page,
 * {@see LaunchPadAnnouncementsAdmin} the Announcements tab; neither is the
 * other, so holding one never grants the other.
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

use OCP\Settings\IDelegatedSettings;

/**
 * A delegable setting in Launchpad's admin section.
 *
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-delegated-admin-settings-per-area-req-adm-034
 */
abstract class LaunchPadSectionSettings implements IDelegatedSettings {
	/**
	 * Launchpad's admin section.
	 *
	 * @return string The section ID.
	 *
	 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-delegated-admin-settings-per-area-req-adm-034
	 */
	public function getSection(): string {
		return 'launchpad';
	}//end getSection()

	/**
	 * App config keys a delegated holder may change: none, Launchpad stores
	 * its settings through its own endpoints, which name the class in
	 * `#[AuthorizedAdminSetting]`.
	 *
	 * @return array<string,string[]> Map of appId to allowed config keys.
	 *
	 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-delegated-admin-settings-per-area-req-adm-034
	 */
	public function getAuthorizedAppConfig(): array {
		return [];
	}//end getAuthorizedAppConfig()
}//end class
