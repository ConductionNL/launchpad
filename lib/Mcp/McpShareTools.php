<?php

/**
 * LaunchPad McpShareTools
 *
 * The share read tool. Like `GET /api/dashboard/{id}/shares` it needs a
 * signed-in caller and lists the shares through DashboardShareService, which
 * refuses anyone but the dashboard's owner (mcp-tool-surface REQ-MCP-002).
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

use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Service\DashboardShareService;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * The read tool over a dashboard's shares.
 *
 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
 */
class McpShareTools {

	/**
	 * Constructor.
	 *
	 * @param McpDashboardGate      $gate      The caller.
	 * @param McpArgumentValidator  $validator Checks the arguments.
	 * @param DashboardShareService $shares    The service `GET /api/dashboard/{id}/shares` calls.
	 */
	public function __construct(
		private readonly McpDashboardGate $gate,
		private readonly McpArgumentValidator $validator,
		private readonly DashboardShareService $shares,
	) {
	}//end __construct()

	/**
	 * The shares of one of the caller's own dashboards.
	 *
	 * @param array<string, mixed> $args `dashboardId`.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public function listShares(array $args): array {
		$refusal = ($this->validator->id(args: $args, name: 'dashboardId') ?? $this->gate->requireSignedIn());
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			$shares = $this->shares->listShares(dashboardId: (int)$args['dashboardId'], userId: $this->gate->currentUserId());
		} catch (DoesNotExistException) {
			return McpArgumentValidator::error(code: 'not_found', message: 'Dashboard not found.');
		} catch (ForbiddenException) {
			return McpArgumentValidator::error(code: 'forbidden', message: 'Only the owner of a dashboard may list its shares.');
		}

		return ['items' => array_map(static fn ($share): array => $share->jsonSerialize(), $shares)];
	}//end listShares()
}//end class
