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
		return $this->onDashboard(
			args: $args,
			action: 'dashboard-metadata.get-metadata',
			guard: fn (int $dashboardId): ?array => $this->gate->requireView(dashboardId: $dashboardId),
			operation: fn (string $uuid): array => ['metadata' => $this->metadata->getMetadataForDashboard(dashboardUuid: $uuid)]
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
		if (is_array($args['metadata'] ?? null) === false) {
			return $this->validator->refuse(code: 'invalid_arguments', message: 'Argument metadata must be an object of field keys to values.');
		}

		return $this->onDashboard(
			args: $args,
			action: 'dashboard-metadata.set-metadata',
			guard: fn (int $dashboardId): ?array => $this->gate->requireMetadataEdit(dashboardId: $dashboardId),
			operation: function (string $uuid) use ($args): array {
				try {
					return ['metadata' => $this->metadata->setMetadataForDashboard(dashboardUuid: $uuid, keyValues: $args['metadata'])];
				} catch (InvalidMetadataFieldException $e) {
					return $this->validator->refuse(code: 'invalid_arguments', message: $e->getMessage());
				}
			}
		);
	}//end setDashboardMetadata()

	/**
	 * Check the UUID and the ADR-023 action, find the dashboard, apply the
	 * per-dashboard guard and run the operation.
	 *
	 * @param array<string, mixed>          $args      The tool arguments.
	 * @param string                        $action    The route's ADR-023 action.
	 * @param Closure(int): (array|null)    $guard     The per-dashboard check.
	 * @param Closure(string): array<mixed> $operation The service call, given the UUID.
	 *
	 * @return array<string, mixed>
	 */
	private function onDashboard(array $args, string $action, Closure $guard, Closure $operation): array {
		$refusal = ($this->validator->uuid(args: $args, name: 'dashboardUuid') ?? $this->gate->requireAction(action: $action));
		if ($refusal !== null) {
			return $refusal;
		}

		$uuid = (string)$args['dashboardUuid'];
		try {
			$dashboard = $this->dashboards->findByUuid(uuid: $uuid);
		} catch (DoesNotExistException $e) {
			return $this->validator->failure(exception: $e);
		}

		return ($guard((int)$dashboard->getId()) ?? $operation($uuid));
	}//end onDashboard()
}//end class
