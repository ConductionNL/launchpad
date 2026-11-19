<?php

/**
 * LaunchPad McpMetadataTools
 *
 * The read tool over a dashboard's metadata values. It applies the ADR-023
 * action and the view check of `GET /api/dashboards/{uuid}/metadata` and calls
 * the service that route calls (mcp-tool-surface REQ-MCP-002).
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

use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Service\MetadataService;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * The metadata read tool.
 *
 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-7
 */
class McpMetadataTools {

	/**
	 * Constructor.
	 *
	 * @param McpDashboardGate     $gate       The caller, the ADR-023 action and the view check.
	 * @param McpArgumentValidator $validator  The argument checks.
	 * @param DashboardMapper      $dashboards Finds the dashboard by UUID, as the route does.
	 * @param MetadataService      $metadata   The service the route calls.
	 */
	public function __construct(
		private readonly McpDashboardGate $gate,
		private readonly McpArgumentValidator $validator,
		private readonly DashboardMapper $dashboards,
		private readonly MetadataService $metadata,
	) {
	}//end __construct()

	/**
	 * The metadata values of a dashboard the caller can see.
	 *
	 * @param array<string, mixed> $args The tool arguments: dashboardUuid.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-7
	 */
	public function getDashboardMetadata(array $args): array {
		$refusal = ($this->validator->uuid(args: $args, name: 'dashboardUuid')
			?? $this->gate->requireAction(action: 'dashboard-metadata.get-metadata'));
		if ($refusal !== null) {
			return $refusal;
		}

		$uuid = (string)$args['dashboardUuid'];
		try {
			$dashboard = $this->dashboards->findByUuid(uuid: $uuid);
		} catch (DoesNotExistException $e) {
			return $this->validator->failure(exception: $e);
		}

		$refusal = $this->gate->requireView(dashboardId: (int)$dashboard->getId());
		if ($refusal !== null) {
			return $refusal;
		}

		return ['metadata' => $this->metadata->getMetadataForDashboard(dashboardUuid: $uuid)];
	}//end getDashboardMetadata()
}//end class
