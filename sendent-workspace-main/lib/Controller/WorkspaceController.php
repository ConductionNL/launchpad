<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sendent B.V.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\SendentWorkspace\Controller;

use OCA\SendentWorkspace\Service\WorkspaceService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Dashboard\IManager;
use OCP\Dashboard\IWidget;
use OCP\Dashboard\IIconWidget;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\Util;

#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class WorkspaceController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IInitialState $initialState,
		private IManager $dashboardManager,
		private IConfig $config,
		private ?string $userId,
		private WorkspaceService $service,
		private IGroupManager $groupManager,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @return TemplateResponse
	 */
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/')]
	public function index(): TemplateResponse {
		Util::addStyle('sendentworkspace', 'workspace');
		Util::addScript('sendentworkspace', 'main');

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

		// Determine user's primary group for workspace layout
		$user = $this->userSession->getUser();
		$userGroups = $this->groupManager->getUserGroupIds($user);

		// Get configured groups order from app config
		$configuredGroups = json_decode(
			$this->config->getAppValue('sendentworkspace', 'group_order', '[]'),
			true
		) ?? [];

		// Find first matching group or use default
		$primaryGroup = 'default';
		foreach ($configuredGroups as $groupId) {
			if (in_array($groupId, $userGroups)) {
				$primaryGroup = $groupId;
				break;
			}
		}

		// Load group dashboards
		$groupDashboardsData = $this->service->getGroupDashboards($primaryGroup);
		$groupDashboards = array_map(fn($d) => [
			'id' => $d['id'],
			'name' => $d['name'],
			'icon' => $d['icon'] ?? 'ViewDashboard',
		], $groupDashboardsData['dashboards'] ?? []);
		$defaultDashboardId = $groupDashboardsData['defaultDashboardId'] ?? '';

		// Always include default group dashboards so they appear in the sidebar
		if ($primaryGroup !== 'default') {
			$defaultGroupData = $this->service->getGroupDashboards('default');
			$defaultDashIds = array_column($groupDashboards, 'id');
			foreach ($defaultGroupData['dashboards'] ?? [] as $d) {
				if (!in_array($d['id'], $defaultDashIds)) {
					$groupDashboards[] = [
						'id' => $d['id'],
						'name' => $d['name'],
						'icon' => $d['icon'] ?? 'ViewDashboard',
						'source' => 'default',
					];
				}
			}
			// Use default group's default dashboard if the matched group has none
			if ($defaultDashboardId === '' && !empty($defaultGroupData['defaultDashboardId'])) {
				$defaultDashboardId = $defaultGroupData['defaultDashboardId'];
			}
		}

		// Load user dashboards
		$userDashboards = [];
		$allowUserDashboards = $this->service->getAllowUserDashboards();
		if ($allowUserDashboards && $this->userId) {
			$userDashboardsData = $this->service->getUserDashboards($this->userId);
			$userDashboards = array_map(fn($d) => [
				'id' => $d['id'],
				'name' => $d['name'],
				'icon' => $d['icon'] ?? 'ViewDashboard',
			], $userDashboardsData['dashboards'] ?? []);
		}

		// Resolve active dashboard: user preference → group default → first
		$activeDashboardId = '';
		$dashboardSource = 'group';
		$layout = [];

		if ($this->userId) {
			$activeDashboardId = $this->service->getActiveDashboard($this->userId);
		}

		// Helper: find a group dashboard by ID from matched group or default group
		$defaultGroupData = $defaultGroupData ?? $groupDashboardsData;
		$findGroupDash = function (string $id) use ($groupDashboardsData, $defaultGroupData): ?array {
			foreach ($groupDashboardsData['dashboards'] ?? [] as $d) {
				if ($d['id'] === $id) {
					return $d;
				}
			}
			foreach ($defaultGroupData['dashboards'] ?? [] as $d) {
				if ($d['id'] === $id) {
					return $d;
				}
			}
			return null;
		};

		// Try to load the user's preferred dashboard
		if ($activeDashboardId !== '') {
			// Check if it's a user dashboard
			$foundInUser = false;
			if ($allowUserDashboards && $this->userId) {
				$userDashboardsData = $this->service->getUserDashboards($this->userId);
				foreach ($userDashboardsData['dashboards'] ?? [] as $ud) {
					if ($ud['id'] === $activeDashboardId) {
						$layout = $ud['layout'] ?? [];
						$dashboardSource = 'user';
						$foundInUser = true;
						break;
					}
				}
			}
			// Check if it's a group dashboard (local lookup, no extra service call)
			if (!$foundInUser) {
				$dash = $findGroupDash($activeDashboardId);
				if ($dash !== null) {
					$layout = $dash['layout'] ?? [];
					$dashboardSource = 'group';
				} else {
					// Preference points to deleted/invalid dashboard, reset
					$activeDashboardId = '';
				}
			}
		}

		// Fall back to group default → first dashboard
		if ($activeDashboardId === '') {
			if ($defaultDashboardId !== '') {
				$dash = $findGroupDash($defaultDashboardId);
				if ($dash !== null) {
					$activeDashboardId = $defaultDashboardId;
					$layout = $dash['layout'] ?? [];
					$dashboardSource = 'group';
				}
			}
			// Still empty? Use first group dashboard
			if ($activeDashboardId === '' && !empty($groupDashboardsData['dashboards'])) {
				$first = $groupDashboardsData['dashboards'][0];
				$activeDashboardId = $first['id'];
				$layout = $first['layout'] ?? [];
				$dashboardSource = 'group';
			}
		}

		// Load frontend scripts for ALL available Nextcloud widgets so their
		// callbacks are registered regardless of which dashboard is active.
		// This matches how Nextcloud's own dashboard page loads widgets.
		$allWidgets = $this->dashboardManager->getWidgets();
		foreach ($allWidgets as $widget) {
			$widget->load();
		}

		// Resolve group display name for the sidebar
		$primaryGroupName = $primaryGroup;
		if ($primaryGroup !== 'default') {
			$groupObj = $this->groupManager->get($primaryGroup);
			if ($groupObj !== null) {
				$primaryGroupName = $groupObj->getDisplayName();
			}
		}

		$this->initialState->provideInitialState('widgets', $widgets);
		$this->initialState->provideInitialState('layout', $layout);
		$this->initialState->provideInitialState('primaryGroup', $primaryGroup);
		$this->initialState->provideInitialState('primaryGroupName', $primaryGroupName);
		$this->initialState->provideInitialState('isAdmin', false);
		$this->initialState->provideInitialState('activeDashboardId', $activeDashboardId);
		$this->initialState->provideInitialState('dashboardSource', $dashboardSource);
		$this->initialState->provideInitialState('groupDashboards', $groupDashboards);
		$this->initialState->provideInitialState('userDashboards', $userDashboards);
		$this->initialState->provideInitialState('allowUserDashboards', $allowUserDashboards);

		$response = new TemplateResponse('sendentworkspace', 'index', [
			'id-app-content' => '#app-workspace',
			'id-app-navigation' => null,
		]);

		return $response;
	}
}
