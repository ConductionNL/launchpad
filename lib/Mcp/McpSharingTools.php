<?php

/**
 * LaunchPad McpSharingTools
 *
 * Read tools over a dashboard's shares and the kiosk playlists. Each applies
 * the check of the matching HTTP route and calls the service that route calls
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

use OCA\LaunchPad\Service\DashboardShareService;
use OCA\LaunchPad\Service\KioskService;
use Throwable;

/**
 * The share and kiosk read tools.
 *
 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-7
 */
class McpSharingTools {

	/**
	 * Constructor.
	 *
	 * @param McpDashboardGate      $gate      The caller.
	 * @param McpArgumentValidator  $validator The argument checks.
	 * @param DashboardShareService $shares    The service `GET /api/dashboard/{id}/shares` calls.
	 * @param KioskService          $kiosk     The service `GET /api/kiosk/playlists` calls.
	 */
	public function __construct(
		private readonly McpDashboardGate $gate,
		private readonly McpArgumentValidator $validator,
		private readonly DashboardShareService $shares,
		private readonly KioskService $kiosk,
	) {
	}//end __construct()

	/**
	 * The shares of a dashboard the caller owns.
	 *
	 * @param array<string, mixed> $args The tool arguments: dashboardId.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-7
	 */
	public function listShares(array $args): array {
		$refusal = ($this->validator->id(args: $args, name: 'dashboardId') ?? $this->gate->requireUser());
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			$shares = $this->shares->listShares(dashboardId: (int)$args['dashboardId'], userId: $this->gate->currentUserId());
		} catch (Throwable $e) {
			return $this->validator->failure(exception: $e);
		}

		return ['items' => array_map(callback: static fn ($share): array => $share->jsonSerialize(), array: $shares)];
	}//end listShares()

	/**
	 * The kiosk playlists the caller may see: every active one for an
	 * administrator, their own otherwise.
	 *
	 * @param array<string, mixed> $args The tool arguments (none).
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-7
	 */
	public function listKioskPlaylists(array $args): array {
		$refusal = $this->gate->requireUser();
		if ($refusal !== null) {
			return $refusal;
		}

		$playlists = $this->kiosk->listPlaylists(callerId: $this->gate->currentUserId());
		return ['items' => array_map(callback: static fn ($playlist): array => $playlist->jsonSerialize(), array: $playlists)];
	}//end listKioskPlaylists()
}//end class
