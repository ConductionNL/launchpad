<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sendent B.V.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		['name' => 'workspace#index', 'url' => '/', 'verb' => 'GET'],
		// Resource serving (non-OCS route for StreamResponse)
		['name' => 'workspace_api#getResource', 'url' => '/resource/{filename}', 'verb' => 'GET'],
	],
	'ocs' => [
		// Layout management (legacy, delegates to default dashboard)
		['name' => 'workspace_api#getLayout', 'url' => '/api/v1/layout/{groupId}', 'verb' => 'GET'],
		['name' => 'workspace_api#saveLayout', 'url' => '/api/v1/layout/{groupId}', 'verb' => 'POST'],

		// Groups management
		['name' => 'workspace_api#getGroups', 'url' => '/api/v1/groups', 'verb' => 'GET'],
		['name' => 'workspace_api#updateGroups', 'url' => '/api/v1/groups', 'verb' => 'POST'],

		// Multi-dashboard management
		['name' => 'workspace_api#getGroupDashboards', 'url' => '/api/v1/dashboards/{groupId}', 'verb' => 'GET'],
		['name' => 'workspace_api#createGroupDashboard', 'url' => '/api/v1/dashboards/{groupId}', 'verb' => 'POST'],
		['name' => 'workspace_api#setDefaultDashboard', 'url' => '/api/v1/dashboards/{groupId}/default', 'verb' => 'POST'],
		['name' => 'workspace_api#getGroupDashboard', 'url' => '/api/v1/dashboards/{groupId}/{dashboardId}', 'verb' => 'GET'],
		['name' => 'workspace_api#updateGroupDashboard', 'url' => '/api/v1/dashboards/{groupId}/{dashboardId}', 'verb' => 'PUT'],
		['name' => 'workspace_api#deleteGroupDashboard', 'url' => '/api/v1/dashboards/{groupId}/{dashboardId}', 'verb' => 'DELETE'],

		// User dashboards
		['name' => 'workspace_api#getUserDashboards', 'url' => '/api/v1/user-dashboards', 'verb' => 'GET'],
		['name' => 'workspace_api#createUserDashboard', 'url' => '/api/v1/user-dashboards', 'verb' => 'POST'],
		['name' => 'workspace_api#updateUserDashboard', 'url' => '/api/v1/user-dashboards/{dashboardId}', 'verb' => 'PUT'],
		['name' => 'workspace_api#deleteUserDashboard', 'url' => '/api/v1/user-dashboards/{dashboardId}', 'verb' => 'DELETE'],

		// Active dashboard preference
		['name' => 'workspace_api#setActiveDashboard', 'url' => '/api/v1/active-dashboard', 'verb' => 'POST'],

		// App settings
		['name' => 'workspace_api#getSettings', 'url' => '/api/v1/settings', 'verb' => 'GET'],
		['name' => 'workspace_api#updateSettings', 'url' => '/api/v1/settings', 'verb' => 'POST'],

		// Widget items
		['name' => 'workspace_api#getWidgetItems', 'url' => '/api/v1/widget-items', 'verb' => 'GET'],

		// File creation
		['name' => 'workspace_api#createFile', 'url' => '/api/v1/create-file', 'verb' => 'POST'],

		// Resource upload and listing
		['name' => 'workspace_api#uploadResource', 'url' => '/api/v1/upload-resource', 'verb' => 'POST'],
		['name' => 'workspace_api#listResources', 'url' => '/api/v1/resources', 'verb' => 'GET'],
	],
];
