<?php

/**
 * LaunchPadAdmin
 *
 * Launchpad's admin settings page under Nextcloud's administration settings,
 * `/settings/admin/launchpad` (REQ-ADM-032): every tab. Nextcloud's admin
 * delegation grants this class to a delegated Launchpad administrator, and
 * the administrator endpoints name it in `#[AuthorizedAdminSetting]`
 * (REQ-ADM-034). The Announcements tab alone is
 * {@see LaunchPadAnnouncementsAdmin}.
 *
 * @category  Settings
 * @package   OCA\LaunchPad\Settings
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-032
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\IDelegatedSettings;

class LaunchPadAdmin implements IDelegatedSettings {
	/**
	 * Constructor.
	 *
	 * @param AdminSettingsForm $form Renders the admin settings page.
	 *
	 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-032
	 */
	public function __construct(
		private readonly AdminSettingsForm $form,
	) {
	}//end __construct()

	/**
	 * The admin settings page with every tab.
	 *
	 * @return TemplateResponse The template response.
	 *
	 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-032
	 */
	public function getForm(): TemplateResponse {
		return $this->form->render();
	}//end getForm()

	/**
	 * Get the settings section ID.
	 *
	 * @return string The section ID.
	 */
	public function getSection(): string {
		return 'launchpad';
	}//end getSection()

	/**
	 * Get the settings priority.
	 *
	 * @return int The priority.
	 */
	public function getPriority(): int {
		return 10;
	}//end getPriority()

	/**
	 * Human-readable name of the delegated settings section.
	 *
	 * @return string|null The section name, or null to use the section default.
	 */
	public function getName(): ?string {
		return null;
	}//end getName()

	/**
	 * App config keys an authorized (delegated) admin may manage.
	 *
	 * Returned as a map of appId => list of allowed config keys. LaunchPad
	 * exposes no delegatable sub-keys yet, so this is intentionally empty;
	 * the attribute still scopes the endpoint to full admins.
	 *
	 * @return array<string,string[]> Map of appId to allowed config keys.
	 */
	public function getAuthorizedAppConfig(): array {
		return [];
	}//end getAuthorizedAppConfig()
}//end class
