<?php

/**
 * LaunchPad McpDashboardTools
 *
 * The read tools over dashboards and widget types. Each validates its
 * arguments, applies the ADR-023 action of the matching HTTP route through the
 * gate, and calls the same service the route calls, so a tool sees exactly
 * what the caller sees in the app (mcp-tool-surface REQ-MCP-002).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @category Mcp
 * @package  OCA\LaunchPad\Mcp
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Mcp;

use OCA\LaunchPad\Controller\AllowedWidgetFilter;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use OCA\LaunchPad\Service\WidgetService;
use Throwable;

/**
 * Dashboard and widget-type read tools.
 *
 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
 */
class McpDashboardTools {

	/**
	 * Constructor.
	 *
	 * @param McpDashboardGate             $gate            Caller and action checks.
	 * @param McpArgumentValidator         $validator       Argument checks.
	 * @param DashboardService             $dashboards      The dashboard service the routes use.
	 * @param WidgetService                $widgets         The widget service the routes use.
	 * @param RoleFeaturePermissionService $roleFeaturePerm Which widget types a user's role allows.
	 */
	public function __construct(
		private readonly McpDashboardGate $gate,
		private readonly McpArgumentValidator $validator,
		private readonly DashboardService $dashboards,
		private readonly WidgetService $widgets,
		private readonly RoleFeaturePermissionService $roleFeaturePerm,
	) {
	}//end __construct()

	/**
	 * The dashboards the caller can see, as `GET /api/dashboards/visible` lists them.
	 *
	 * @param array<string, mixed> $args No arguments.
	 *
	 * @return array<string, mixed> `{items: [...]}`, or a refusal.
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public function listDashboards(array $args): array {
		$refusal = $this->gate->requireAction(action: 'dashboard.visible');
		if ($refusal !== null) {
			return $refusal;
		}

		$userId = $this->gate->currentUserId();
		$items = [];
		foreach ($this->dashboards->getVisibleToUser(userId: $userId) as $entry) {
			$row = $entry['dashboard']->jsonSerialize();
			$row['source'] = $entry['source'];
			$row['isOwner'] = ($entry['dashboard']->getUserId() === $userId);
			$items[] = $row;
		}

		return ['items' => $items];
	}//end listDashboards()

	/**
	 * One dashboard with its placements and the caller's permission level, when visible.
	 *
	 * An existing dashboard the caller cannot see answers not_found, as the
	 * HTTP route answers 404, so a tool cannot probe for existence.
	 *
	 * @param array<string, mixed> $args `dashboardUuid`.
	 *
	 * @return array<string, mixed> `{dashboard, placements, permissionLevel, isOwner}`, or a refusal.
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public function getDashboard(array $args): array {
		$refusal = ($this->validator->uuid(args: $args, name: 'dashboardUuid') ?? $this->gate->requireAction(action: 'dashboard.show'));
		if ($refusal !== null) {
			return $refusal;
		}

		$userId = $this->gate->currentUserId();
		$result = $this->visibleDashboard(uuid: (string)$args['dashboardUuid'], userId: $userId);
		if ($result === null) {
			return McpArgumentValidator::error(code: 'not_found', message: 'No dashboard with that UUID is visible to you.');
		}

		$placements = [];
		foreach ($result['placements'] as $placement) {
			$placements[] = $placement->jsonSerialize();
		}

		return [
			'dashboard' => $result['dashboard']->jsonSerialize(),
			'placements' => $placements,
			'permissionLevel' => $result['permissionLevel'],
			'isOwner' => ($result['dashboard']->getUserId() === $userId),
		];
	}//end getDashboard()

	/**
	 * The group-shared dashboards of a group the caller belongs to, as viewers see them.
	 *
	 * @param array<string, mixed> $args `groupId`.
	 *
	 * @return array<string, mixed> `{items: [...]}`, or a refusal.
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public function listGroupDashboards(array $args): array {
		$refusal = ($this->validator->string(args: $args, name: 'groupId', min: 1, max: 255)
			?? $this->gate->requireAction(action: 'dashboard.list-group'));
		if ($refusal !== null) {
			return $refusal;
		}

		$groupId = (string)$args['groupId'];
		if ($this->dashboards->userCanAccessGroup(userId: $this->gate->currentUserId(), groupId: $groupId) === false) {
			return McpArgumentValidator::error(code: 'forbidden', message: 'You are not a member of that group.');
		}

		$items = [];
		foreach ($this->dashboards->listGroupDashboards(groupId: $groupId) as $dashboard) {
			$items[] = $dashboard->toViewerArray();
		}

		return ['items' => $items];
	}//end listGroupDashboards()

	/**
	 * The widget types the caller's role may place.
	 *
	 * @param array<string, mixed> $args No arguments.
	 *
	 * @return array<string, mixed> `{items: [...]}`, or a refusal.
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public function listWidgetTypes(array $args): array {
		$refusal = $this->gate->requireAction(action: 'widget.list-available');
		if ($refusal !== null) {
			return $refusal;
		}

		$filter = new AllowedWidgetFilter(roleFeaturePerm: $this->roleFeaturePerm, userId: $this->gate->currentUserId());

		return ['items' => array_values($filter->filter(widgets: $this->widgets->getAvailableWidgets()))];
	}//end listWidgetTypes()

	/**
	 * A dashboard by uuid when the caller can see it, through the service the route uses.
	 *
	 * @param string $uuid   The dashboard uuid.
	 * @param string $userId The caller.
	 *
	 * @return array<string, mixed>|null `{dashboard, placements, permissionLevel}`, or null.
	 */
	private function visibleDashboard(string $uuid, string $userId): ?array {
		try {
			$dashboard = $this->dashboards->findByUuid(uuid: $uuid);
		} catch (Throwable) {
			return null;
		}

		return $this->dashboards->getDashboardForUser(dashboardId: (int)$dashboard->getId(), userId: $userId);
	}//end visibleDashboard()
}//end class
