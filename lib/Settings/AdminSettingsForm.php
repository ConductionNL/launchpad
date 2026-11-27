<?php

/**
 * AdminSettingsForm
 *
 * Renders Launchpad's admin settings page inside Nextcloud's settings
 * framework at `/settings/admin/launchpad` (REQ-ADM-032): the mount point of
 * the settings bundle (`src/settings.js`), the admin initial state with the
 * tabs the viewer holds (REQ-ADM-034) and the app version for the version
 * card. Both Launchpad admin settings classes render through it, so the page
 * mounts once whichever class Nextcloud asks first.
 *
 * @category  Settings
 * @package   OCA\LaunchPad\Settings
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-036
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Settings;

use OCA\LaunchPad\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Util;

/**
 * The admin settings page's template, script and initial state.
 *
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-036
 */
class AdminSettingsForm {
	/**
	 * Constructor.
	 *
	 * @param AdminFormState  $formState  Provides the admin initial state.
	 * @param AdminPageAccess $access     Answers which tabs the viewer holds.
	 * @param IAppManager     $appManager Reads the running app version.
	 *
	 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-036
	 */
	public function __construct(
		private readonly AdminFormState $formState,
		private readonly AdminPageAccess $access,
		private readonly IAppManager $appManager,
	) {
	}//end __construct()

	/**
	 * The page: its mount point, with the settings bundle and the initial
	 * state of the tabs the viewer holds. A viewer who holds no tab gets an
	 * empty form, so nothing of the page reaches them.
	 *
	 * @return TemplateResponse The settings form.
	 *
	 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-036
	 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-delegated-admin-settings-per-area-req-adm-034
	 */
	public function render(): TemplateResponse {
		$tabs = $this->access->tabs();
		if ($tabs === []) {
			return $this->empty();
		}

		$this->formState->provide(
			tabs: $tabs,
			version: $this->appManager->getAppVersion(appId: Application::APP_ID)
		);
		$this->addAssets();

		return new TemplateResponse(appName: Application::APP_ID, templateName: 'settings/admin');
	}//end render()

	/**
	 * Load the settings bundle and the app stylesheet.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) Util is Nextcloud's only API for page scripts and styles.
	 *
	 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-036
	 */
	protected function addAssets(): void {
		Util::addScript(application: Application::APP_ID, file: 'launchpad-settings');
		// The library stylesheet, linked instead of inserted by the bundle
		// (the build copies it to css/nextcloud-vue.css).
		Util::addStyle(application: Application::APP_ID, file: 'nextcloud-vue');
		Util::addStyle(application: Application::APP_ID, file: 'launchpad');
	}//end addAssets()

	/**
	 * An empty form: what a second settings class renders when the first
	 * already mounts the page, and what a viewer without a tab gets.
	 *
	 * @return TemplateResponse The empty form.
	 *
	 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-delegated-admin-settings-per-area-req-adm-034
	 */
	public function empty(): TemplateResponse {
		return new TemplateResponse(appName: Application::APP_ID, templateName: 'settings/empty');
	}//end empty()
}//end class
