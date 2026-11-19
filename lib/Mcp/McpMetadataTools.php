<?php

/**
 * LaunchPad McpMetadataTools
 *
 * The tools over a dashboard's metadata values: read and set. Each applies
 * the ADR-023 action and the per-dashboard check of its
 * `/api/dashboards/{uuid}/metadata` route and calls the service that route
 * calls (mcp-tool-surface REQ-MCP-002, REQ-MCP-003).
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

use Closure;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Exception\InvalidMetadataFieldException;
use OCA\LaunchPad\Service\MetadataService;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * The metadata tools.
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

		return $this->withDashboard(
			uuid: (string)$args['dashboardUuid'],
			operation: fn (Dashboard $dashboard): array => ($this->gate->requireView(dashboardId: (int)$dashboard->getId())
				?? ['metadata' => $this->metadata->getMetadataForDashboard(dashboardUuid: (string)$dashboard->getUuid())])
		);
	}//end getDashboardMetadata()

	/**
	 * Set metadata values on a dashboard whose metadata the caller may edit.
	 *
	 * @param array<string, mixed> $args The tool arguments: dashboardUuid, metadata (field key to value).
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
	 */
	public function setDashboardMetadata(array $args): array {
		$refusal = ($this->validator->uuid(args: $args, name: 'dashboardUuid')
			?? $this->gate->requireAction(action: 'dashboard-metadata.set-metadata'));
		if (is_array($args['metadata'] ?? null) === false) {
			$refusal ??= $this->validator->refuse(code: 'invalid_arguments', message: 'Argument metadata must be an object of field keys to values.');
		}

		if ($refusal !== null) {
			return $refusal;
		}

		return $this->withDashboard(
			uuid: (string)$args['dashboardUuid'],
			operation: fn (Dashboard $dashboard): array => ($this->gate->requireMetadataEdit(dashboardId: (int)$dashboard->getId())
				?? $this->writeMetadata(uuid: (string)$dashboard->getUuid(), values: $args['metadata']))
		);
	}//end setDashboardMetadata()

	/**
	 * Write the values, refusing an unknown or invalid field as the route does.
	 *
	 * @param string              $uuid   The dashboard UUID.
	 * @param array<string, mixed> $values Field key to value.
	 *
	 * @return array<string, mixed>
	 */
	private function writeMetadata(string $uuid, array $values): array {
		try {
			return ['metadata' => $this->metadata->setMetadataForDashboard(dashboardUuid: $uuid, keyValues: $values)];
		} catch (InvalidMetadataFieldException $e) {
			return $this->validator->refuse(code: 'invalid_arguments', message: $e->getMessage());
		}
	}//end writeMetadata()

	/**
	 * Find the dashboard and run the operation on it.
	 *
	 * @param string                      $uuid      The dashboard UUID.
	 * @param Closure(Dashboard): array<mixed> $operation The check and the service call.
	 *
	 * @return array<string, mixed>
	 */
	private function withDashboard(string $uuid, Closure $operation): array {
		try {
			$dashboard = $this->dashboards->findByUuid(uuid: $uuid);
		} catch (DoesNotExistException $e) {
			return $this->validator->failure(exception: $e);
		}

		return $operation($dashboard);
	}//end withDashboard()
}//end class
