<?php

/**
 * LaunchPad McpHistoryTools
 *
 * The tools over a dashboard's saved versions: list, create and restore. Each
 * applies the ADR-023 action of its `/api/dashboards/{uuid}/versions` route and
 * calls the service that route calls (mcp-tool-surface REQ-MCP-002, REQ-MCP-003).
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
use Exception;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Service\DashboardVersionService;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * The version tools.
 *
 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-7
 */
class McpHistoryTools {

	/**
	 * Constructor.
	 *
	 * @param McpDashboardGate        $gate       The caller and the ADR-023 action check.
	 * @param McpArgumentValidator    $validator  The argument checks.
	 * @param DashboardMapper         $dashboards Finds the dashboard by UUID, as the route does.
	 * @param DashboardVersionService $versions   The service the route calls.
	 */
	public function __construct(
		private readonly McpDashboardGate $gate,
		private readonly McpArgumentValidator $validator,
		private readonly DashboardMapper $dashboards,
		private readonly DashboardVersionService $versions,
	) {
	}//end __construct()

	/**
	 * The saved versions of a dashboard, when the caller may see them.
	 *
	 * @param array<string, mixed> $args The tool arguments: dashboardUuid.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-7
	 */
	public function listVersions(array $args): array {
		$refusal = $this->refusal(args: $args, action: 'dashboard-version.list-versions');
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->withDashboard(
			uuid: (string)$args['dashboardUuid'],
			operation: fn (Dashboard $dashboard, string $userId): array => $this->versions->listVersions(
				dashboard: $dashboard,
				requestingUser: $userId
			)
		);
	}//end listVersions()

	/**
	 * Save the dashboard's current state as a named version.
	 *
	 * @param array<string, mixed> $args The tool arguments: dashboardUuid, note.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
	 */
	public function createDashboardVersion(array $args): array {
		$refusal = ($this->validator->optional(args: $args, name: 'note', refusal: $this->validator->string(args: $args, name: 'note', min: 1, max: 500))
			?? $this->refusal(args: $args, action: 'dashboard-version.create-version'));
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->withDashboard(
			uuid: (string)$args['dashboardUuid'],
			operation: fn (Dashboard $dashboard, string $userId): array => [
				'version' => $this->versions->createExplicitSnapshot(
					dashboard: $dashboard,
					requestingUser: $userId,
					note: ($args['note'] ?? null)
				)->jsonSerialize(),
			]
		);
	}//end createDashboardVersion()

	/**
	 * Restore the dashboard to one of its saved versions.
	 *
	 * @param array<string, mixed> $args The tool arguments: dashboardUuid, versionNumber.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
	 */
	public function restoreDashboardVersion(array $args): array {
		$refusal = ($this->validator->positiveId(args: $args, name: 'versionNumber')
			?? $this->refusal(args: $args, action: 'dashboard-version.restore-version'));
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->withDashboard(
			uuid: (string)$args['dashboardUuid'],
			operation: function (Dashboard $dashboard, string $userId) use ($args): array {
				$result = $this->versions->restoreVersion(
					dashboard: $dashboard,
					versionNumber: (int)$args['versionNumber'],
					restoringUser: $userId
				);
				return ['version' => $result['version']->jsonSerialize(), 'snapshot' => $result['snapshot']];
			}
		);
	}//end restoreDashboardVersion()

	/**
	 * A refusal for a bad dashboard UUID or a caller without the route's
	 * ADR-023 action, or NULL to proceed.
	 *
	 * @param array<string, mixed> $args   The tool arguments.
	 * @param string               $action The route's ADR-023 action.
	 *
	 * @return array<string, mixed>|null
	 */
	private function refusal(array $args, string $action): ?array {
		return ($this->validator->uuid(args: $args, name: 'dashboardUuid') ?? $this->gate->requireAction(action: $action));
	}//end refusal()

	/**
	 * Find the dashboard and run the operation on it for the caller, mapping
	 * the version service's refusals as the routes do.
	 *
	 * @param string                                   $uuid      The dashboard UUID.
	 * @param Closure(Dashboard, string): array<mixed> $operation The service call.
	 *
	 * @return array<string, mixed>
	 */
	private function withDashboard(string $uuid, Closure $operation): array {
		try {
			return $operation($this->dashboards->findByUuid(uuid: $uuid), $this->gate->currentUserId());
		} catch (DoesNotExistException $e) {
			return $this->validator->failure(exception: $e);
		} catch (Exception $e) {
			if ($e->getMessage() === DashboardVersionService::ERR_FORBIDDEN_NOT_OWNER_OR_ADMIN) {
				return $this->validator->refuse(code: 'forbidden', message: 'Only the owner or an administrator may do this.');
			}

			return $this->validator->failure(exception: $e);
		}
	}//end withDashboard()
}//end class
