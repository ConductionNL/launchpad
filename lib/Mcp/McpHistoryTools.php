<?php

/**
 * LaunchPad McpHistoryTools
 *
 * The read tool over a dashboard's saved versions. It applies the ADR-023
 * action of `GET /api/dashboards/{uuid}/versions` and calls the service that
 * route calls (mcp-tool-surface REQ-MCP-002).
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

use Exception;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Service\DashboardVersionService;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * The version read tool.
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
		$refusal = ($this->validator->uuid(args: $args, name: 'dashboardUuid')
			?? $this->gate->requireAction(action: 'dashboard-version.list-versions'));
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			$dashboard = $this->dashboards->findByUuid(uuid: (string)$args['dashboardUuid']);
			return $this->versions->listVersions(dashboard: $dashboard, requestingUser: $this->gate->currentUserId());
		} catch (DoesNotExistException $e) {
			return $this->validator->failure(exception: $e);
		} catch (Exception $e) {
			if ($e->getMessage() === DashboardVersionService::ERR_FORBIDDEN_NOT_OWNER_OR_ADMIN) {
				return $this->validator->refuse(code: 'forbidden', message: 'Only the owner or an administrator may see the versions.');
			}

			return $this->validator->failure(exception: $e);
		}
	}//end listVersions()
}//end class
