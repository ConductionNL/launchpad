<?php

/**
 * LaunchPad McpCatalogueTools
 *
 * Read tools over the dashboard tree and the template gallery. Each applies
 * the ADR-023 action of the matching HTTP route through the gate and calls the
 * service that route calls (mcp-tool-surface REQ-MCP-002).
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

use OCA\LaunchPad\Controller\DashboardApiHistory;
use OCA\LaunchPad\Controller\DashboardApiServices;
use OCA\LaunchPad\Service\AdminTemplateService;

/**
 * The dashboard tree and template gallery read tools.
 *
 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-7
 */
class McpCatalogueTools {

	/**
	 * Constructor.
	 *
	 * @param McpDashboardGate     $gate       The caller and the ADR-023 action check.
	 * @param DashboardApiServices $services  The dashboard service, whose visible set `GET /api/dashboards/tree` filters by.
	 * @param DashboardApiHistory  $history   The tree service `GET /api/dashboards/tree` builds with.
	 * @param AdminTemplateService $templates The gallery `GET /api/templates/gallery` lists.
	 */
	public function __construct(
		private readonly McpDashboardGate $gate,
		private readonly DashboardApiServices $services,
		private readonly DashboardApiHistory $history,
		private readonly AdminTemplateService $templates,
	) {
	}//end __construct()

	/**
	 * The dashboard tree, filtered to the dashboards the caller can see.
	 *
	 * @param array<string, mixed> $args The tool arguments (none).
	 *
	 * @return array<string, mixed>
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) The provider passes every tool its arguments.
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-7
	 */
	public function getDashboardTree(array $args): array {
		$refusal = $this->gate->requireAction(action: 'dashboard.tree');
		if ($refusal !== null) {
			return $refusal;
		}

		$visibleUuids = [];
		foreach ($this->services->dashboardService->getVisibleToUser(userId: $this->gate->currentUserId()) as $entry) {
			$uuid = $entry['dashboard']->getUuid();
			if ($uuid !== null && $uuid !== '') {
				$visibleUuids[$uuid] = true;
			}
		}

		return ['tree' => $this->history->treeService->getFilteredTree(visibleUuids: $visibleUuids)];
	}//end getDashboardTree()

	/**
	 * The template gallery, optionally for one category.
	 *
	 * @param array<string, mixed> $args The tool arguments: category, sort.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-7
	 */
	public function listTemplates(array $args): array {
		$validator = new McpArgumentValidator();
		$refusal   = ($validator->string(args: $args, name: 'category', min: 1, max: 255, required: false)
			?? $validator->oneOf(args: $args, name: 'sort', allowed: ['name', 'updatedAt'], required: false)
			?? $this->gate->requireAction(action: 'template.gallery'));
		if ($refusal !== null) {
			return $refusal;
		}

		return [
			'templates' => $this->templates->getGallery(
				category: ($args['category'] ?? null),
				sortBy: ($args['sort'] ?? 'name')
			),
		];
	}//end listTemplates()
}//end class
