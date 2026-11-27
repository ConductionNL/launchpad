<?php

/**
 * LaunchPadAnnouncementsAdmin
 *
 * The Announcements tab of Launchpad's admin settings page as a setting of
 * its own (REQ-ADM-034): an administrator delegates it, through Nextcloud's
 * Administration privileges, to the people who manage announcements without
 * making them Launchpad administrators. They then see the Launchpad entry in
 * the admin settings with only the Announcements tab. The announcement
 * management endpoints name this class in `#[AuthorizedAdminSetting]`.
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

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\Settings\IDelegatedSettings;

/**
 * The delegable announcements setting.
 *
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-delegated-admin-settings-per-area-req-adm-034
 */
class LaunchPadAnnouncementsAdmin implements IDelegatedSettings {
	/**
	 * Constructor.
	 *
	 * @param AdminSettingsForm $form   Renders the admin settings page.
	 * @param AdminPageAccess   $access Answers whether the viewer holds the whole page.
	 * @param IL10N             $l      The app's translations.
	 *
	 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-delegated-admin-settings-per-area-req-adm-034
	 */
	public function __construct(
		private readonly AdminSettingsForm $form,
		private readonly AdminPageAccess $access,
		private readonly IL10N $l,
	) {
	}//end __construct()

	/**
	 * The page with only the Announcements tab, or nothing when the viewer
	 * holds the whole page: {@see LaunchPadAdmin} mounts it then, so it
	 * mounts once.
	 *
	 * @return TemplateResponse The template response.
	 *
	 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-delegated-admin-settings-per-area-req-adm-034
	 */
	public function getForm(): TemplateResponse {
		if ($this->access->allows() === true) {
			return $this->form->empty();
		}

		return $this->form->render();
	}//end getForm()

	/**
	 * The section: Launchpad's, next to {@see LaunchPadAdmin}.
	 *
	 * @return string The section ID.
	 *
	 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-delegated-admin-settings-per-area-req-adm-034
	 */
	public function getSection(): string {
		return 'launchpad';
	}//end getSection()

	/**
	 * After {@see LaunchPadAdmin}, so the whole page renders first.
	 *
	 * @return int The priority.
	 *
	 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-delegated-admin-settings-per-area-req-adm-034
	 */
	public function getPriority(): int {
		return 20;
	}//end getPriority()

	/**
	 * The name Nextcloud's Administration privileges show for this setting.
	 *
	 * @return string|null The name.
	 *
	 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-delegated-admin-settings-per-area-req-adm-034
	 */
	public function getName(): ?string {
		return $this->l->t(text: 'Launchpad announcements');
	}//end getName()

	/**
	 * App config keys a delegated announcements manager may change: none,
	 * announcements are stored through Launchpad's own endpoints.
	 *
	 * @return array<string,string[]> Map of appId to allowed config keys.
	 *
	 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-delegated-admin-settings-per-area-req-adm-034
	 */
	public function getAuthorizedAppConfig(): array {
		return [];
	}//end getAuthorizedAppConfig()
}//end class
