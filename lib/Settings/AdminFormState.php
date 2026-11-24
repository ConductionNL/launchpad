<?php

/**
 * AdminFormState
 *
 * The initial state of LaunchPad's admin settings page (REQ-INIT-002): every
 * group, the configured group order (falling back to the legacy
 * `configured_groups` setting, REQ-ASET-012), the available widgets, whether
 * users may make their own dashboards and the file types a link can create,
 * provided through {@see \OCA\LaunchPad\Service\InitialStateBuilder} so the
 * page never renders with a partial payload. Kept apart from the settings
 * class, which every administrator endpoint depends on.
 *
 * @spec openspec/specs/initial-state-contract/spec.md#requirement-versioned-key-set-per-page-req-init-002
 *
 * @category  Settings
 * @package   OCA\LaunchPad\Settings
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Settings;

use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\InitialState\Page;
use OCA\LaunchPad\Service\InitialStateBuilder;
use OCP\AppFramework\Services\IInitialState;
use OCP\IGroupManager;

/**
 * Provides the admin settings page's initial state.
 *
 * @spec openspec/specs/initial-state-contract/spec.md#requirement-versioned-key-set-per-page-req-init-002
 */
class AdminFormState {
	/**
	 * Constructor.
	 *
	 * @param IInitialState        $initialState    The Nextcloud initial-state service.
	 * @param IGroupManager        $groupManager    Group manager (full group list).
	 * @param AdminPageSources     $sources         The widget service, setting mapper, dashboard service and file service.
	 * @param AdminSettingsService $settingsService Admin-settings service exposing the `group_order` setting
	 *                                              (REQ-ASET-012).
	 */
	public function __construct(
		private readonly IInitialState $initialState,
		private readonly IGroupManager $groupManager,
		private readonly AdminPageSources $sources,
		private readonly AdminSettingsService $settingsService,
	) {
	}//end __construct()

	/**
	 * Provide the full admin initial-state contract.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/initial-state-contract/spec.md#requirement-versioned-key-set-per-page-req-init-002
	 * @spec openspec/changes/archive/2026-11-24-admin-full-width-page/specs/admin-settings/spec.md#requirement-the-administration-is-a-launchpad-page-over-the-full-width-req-adm-030
	 */
	public function provide(): void {
		$allGroups = [];
		foreach ($this->groupManager->search(search: '') as $group) {
			$allGroups[] = [
				'id' => $group->getGID(),
				'displayName' => $group->getDisplayName(),
			];
		}

		// REQ-ASET-012: prefer the new `group_order` setting (defensive
		// read returns []). Falls back to the legacy `configured_groups`
		// key for installs that wrote it before the cutover so the
		// initial render still shows the admin's previous selection.
		$configuredGroups = $this->settingsService->getGroupOrder();
		if ($configuredGroups === []) {
			$legacy = $this->sources->settingMapper->getValue(
				key: 'configured_groups',
				default: []
			);
			if (is_array($legacy) === true) {
				$filtered = array_filter(
					array: $legacy,
					callback: static function ($entry) {
						return is_string($entry) === true && $entry !== '';
					}
				);
				$configuredGroups = array_values(array: $filtered);
			}
		}

		$allowUserDashboards = $this->sources->dashboardService->getAllowUserDashboards();

		(new InitialStateBuilder(
			initialState: $this->initialState,
			page: Page::ADMIN
		))
			->setAllGroups($allGroups)
			->setConfiguredGroups($configuredGroups)
			->setWidgets($this->sources->widgetService->getAvailableWidgets())
			->setAllowUserDashboards($allowUserDashboards)
			->setLinkCreateFileExtensions(
				$this->sources->fileService->getAllowedExtensions()
			)
			// The page renders in the Launchpad shell only for a (delegated)
			// administrator, whose More menu then offers the admin entries.
			->setIsAdmin(true)
			->apply();
	}//end provide()
}//end class
