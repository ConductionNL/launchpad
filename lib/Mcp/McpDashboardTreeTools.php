<?php

/**
 * LaunchPad McpDashboardTreeTools
 *
 * The dashboard tree read tool. Like `GET /api/dashboards/tree` it applies the
 * ADR-023 action, builds the caller's visible set and asks the tree service
 * for the tree filtered to it, so no dashboard outside that set is named
 * (mcp-tool-surface REQ-MCP-002).
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

use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;

/**
 * The read tool over the dashboard tree.
 *
 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
 */
class McpDashboardTreeTools {

	/**
	 * Constructor.
	 *
	 * @param McpDashboardGate     $gate       The caller and the ADR-023 action check.
	 * @param DashboardService     $dashboards Which dashboards the caller can see.
	 * @param DashboardTreeService $tree       The tree `GET /api/dashboards/tree` reads.
	 */
	public function __construct(
		private readonly McpDashboardGate $gate,
		private readonly DashboardService $dashboards,
		private readonly DashboardTreeService $tree,
	) {
	}//end __construct()

	/**
	 * The tree of the dashboards the caller can see.
	 *
	 * @param array<string, mixed> $args The tool arguments (none).
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public function getDashboardTree(array $args): array {
		$refusal = $this->gate->requireAction(action: 'dashboard.tree');
		if ($refusal !== null) {
			return $refusal;
		}

		$visible = [];
		foreach ($this->dashboards->getVisibleToUser(userId: $this->gate->currentUserId()) as $entry) {
			$uuid = (string)$entry['dashboard']->getUuid();
			if ($uuid !== '') {
				$visible[$uuid] = true;
			}
		}

		return ['items' => $this->tree->getFilteredTree(visibleUuids: $visible)];
	}//end getDashboardTree()
}//end class
