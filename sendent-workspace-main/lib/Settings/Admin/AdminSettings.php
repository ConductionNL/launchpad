<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sendent B.V.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\SendentWorkspace\Settings\Admin;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\IManager;
use OCP\Dashboard\IWidget;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Settings\ISettings;
use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\Util;

class AdminSettings implements ISettings {

	public function __construct(
		private IConfig $config,
		private IL10N $l,
		private IGroupManager $groupManager,
		private IUserSession $userSession,
		private IInitialState $initialState,
		private IManager $dashboardManager,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		Util::addScript('sendentworkspace', 'admin');
		Util::addStyle('sendentworkspace', 'admin');
		Util::addStyle('sendentworkspace', 'workspace');

		// Get all groups
		$allGroups = $this->groupManager->search('');
		$allGroupIds = array_map(fn($group) => [
			'id' => $group->getGID(),
			'displayName' => $group->getDisplayName(),
		], $allGroups);

		// Get configured groups order
		$configuredGroups = json_decode(
			$this->config->getAppValue('sendentworkspace', 'group_order', '[]'),
			true
		) ?? [];

		// Get available Nextcloud widgets
		$widgets = array_values(array_map(function (IWidget $widget) {
			return [
				'id' => $widget->getId(),
				'title' => $widget->getTitle(),
				'iconClass' => $widget->getIconClass(),
				'iconUrl' => $widget instanceof IIconWidget ? $widget->getIconUrl() : '',
				'url' => $widget->getUrl() ?? '',
			];
		}, $this->dashboardManager->getWidgets()));

		// Get allow_user_dashboards setting
		$allowUserDashboards = $this->config->getAppValue('sendentworkspace', 'allow_user_dashboards', '0') === '1';

		$this->initialState->provideInitialState('allGroups', $allGroupIds);
		$this->initialState->provideInitialState('configuredGroups', $configuredGroups);
		$this->initialState->provideInitialState('widgets', $widgets);
		$this->initialState->provideInitialState('allowUserDashboards', $allowUserDashboards);

		return new TemplateResponse('sendentworkspace', 'admin', []);
	}

	/**
	 * @return string the section ID, e.g. 'sharing'
	 */
	public function getSection(): string {
		return 'sendentworkspace';
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 * the admin section. The forms are arranged in ascending order of the
	 * priority values. It is required to return a value between 0 and 100.
	 */
	public function getPriority(): int {
		return 10;
	}
}
