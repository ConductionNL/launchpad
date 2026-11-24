<?php

/**
 * LaunchPadAdmin
 *
 * Launchpad's entry under Nextcloud's administration settings. The
 * administration itself is a Launchpad page over the full width
 * (`/apps/launchpad/admin/{tab}`, {@see \OCA\LaunchPad\Controller\AdminPageController});
 * this entry says so in one line and links to it (REQ-ADM-031). The class
 * is still the setting Nextcloud's admin delegation grants, which the
 * administrator endpoints name in `#[AuthorizedAdminSetting]`.
 *
 * @category  Settings
 * @package   OCA\LaunchPad\Settings
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Settings;

use OCA\LaunchPad\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IURLGenerator;
use OCP\Settings\IDelegatedSettings;

class LaunchPadAdmin implements IDelegatedSettings {
	/**
	 * Constructor.
	 *
	 * @param IURLGenerator $urlGenerator Builds the address of the administration page.
	 *
	 * @spec openspec/changes/archive/2026-11-24-admin-full-width-page/specs/admin-settings/spec.md#requirement-nextclouds-settings-point-to-the-page-req-adm-031
	 */
	public function __construct(
		private readonly IURLGenerator $urlGenerator,
	) {
	}//end __construct()

	/**
	 * The settings entry: one line and a link to the administration page.
	 *
	 * @return TemplateResponse The template response.
	 *
	 * @spec openspec/changes/archive/2026-11-24-admin-full-width-page/specs/admin-settings/spec.md#requirement-nextclouds-settings-point-to-the-page-req-adm-031
	 */
	public function getForm(): TemplateResponse {
		return new TemplateResponse(
			appName: Application::APP_ID,
			templateName: 'settings/admin',
			params: [
				'adminPageUrl' => $this->urlGenerator->linkToRoute(
					routeName: Application::APP_ID . '.adminPage.show',
					arguments: ['tab' => 'general']
				),
			]
		);
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
