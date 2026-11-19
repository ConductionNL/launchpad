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
use OCA\LaunchPad\Service\KioskPlaylistDraft;
use OCA\LaunchPad\Service\KioskService;
use OCA\LaunchPad\Service\ShareGrant;
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
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) The provider passes every tool its arguments.
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

	/**
	 * Share a dashboard the caller owns with a user or a group.
	 *
	 * @param array<string, mixed> $args The tool arguments: dashboardId, shareType, shareWith, permissionLevel.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-10
	 */
	public function shareDashboard(array $args): array {
		$refusal = ($this->validator->id(args: $args, name: 'dashboardId')
			?? $this->validator->oneOf(args: $args, name: 'shareType', allowed: ['user', 'group'])
			?? $this->validator->string(args: $args, name: 'shareWith', min: 1, max: 255)
			?? $this->validator->oneOf(args: $args, name: 'permissionLevel', allowed: ['view_only', 'add_only', 'full'])
			?? $this->gate->requireUser());
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->run(
			operation: fn (): array => $this->shares->addShare(
				dashboardId: (int)$args['dashboardId'],
				grant: new ShareGrant(
					shareType: (string)$args['shareType'],
					shareWith: (string)$args['shareWith'],
					permissionLevel: (string)$args['permissionLevel']
				),
				callerId: $this->gate->currentUserId()
			)->jsonSerialize()
		);
	}//end shareDashboard()

	/**
	 * Replace every share of a dashboard the caller owns with the given list.
	 *
	 * @param array<string, mixed> $args The tool arguments: dashboardId, shares.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-10
	 */
	public function replaceShares(array $args): array {
		$refusal = ($this->validator->id(args: $args, name: 'dashboardId') ?? $this->gate->requireUser());
		if ($refusal === null && is_array($args['shares'] ?? null) === false) {
			$refusal = $this->validator->refuse(code: 'invalid_arguments', message: 'Argument shares must be a list of shares.');
		}

		if ($refusal !== null) {
			return $refusal;
		}

		return $this->run(
			operation: fn (): array => ['items' => array_map(
				callback: static fn ($share): array => $share->jsonSerialize(),
				array: $this->shares->replaceShares(
					dashboardId: (int)$args['dashboardId'],
					shares: $args['shares'],
					userId: $this->gate->currentUserId()
				)
			)]
		);
	}//end replaceShares()

	/**
	 * Remove one share of a dashboard the caller owns.
	 *
	 * @param array<string, mixed> $args The tool arguments: shareId.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-10
	 */
	public function unshareDashboard(array $args): array {
		$refusal = ($this->validator->id(args: $args, name: 'shareId') ?? $this->gate->requireUser());
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->run(
			operation: function () use ($args): array {
				$this->shares->removeShare(shareId: (int)$args['shareId'], callerId: $this->gate->currentUserId());
				return ['removed' => true];
			}
		);
	}//end unshareDashboard()

	/**
	 * Create a kiosk playlist: an anonymous full-screen rotation of dashboards.
	 *
	 * @param array<string, mixed> $args The tool arguments: name, entries, refreshSeconds.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-11
	 */
	public function createKioskPlaylist(array $args): array {
		$refusal = ($this->playlistRefusal(args: $args) ?? $this->gate->requireUser());
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->run(
			operation: fn (): array => $this->kiosk->createPlaylist(
				draft: $this->draft(args: $args),
				callerId: $this->gate->currentUserId()
			)->jsonSerialize()
		);
	}//end createKioskPlaylist()

	/**
	 * Change a kiosk playlist the caller owns.
	 *
	 * @param array<string, mixed> $args The tool arguments: playlistId, name, entries, refreshSeconds.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-11
	 */
	public function updateKioskPlaylist(array $args): array {
		$refusal = ($this->validator->id(args: $args, name: 'playlistId') ?? $this->playlistRefusal(args: $args) ?? $this->gate->requireUser());
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->run(
			operation: fn (): array => $this->kiosk->updatePlaylist(
				id: (int)$args['playlistId'],
				draft: $this->draft(args: $args),
				callerId: $this->gate->currentUserId()
			)->jsonSerialize()
		);
	}//end updateKioskPlaylist()

	/**
	 * Revoke a kiosk playlist the caller owns; its kiosk link stops working.
	 *
	 * @param array<string, mixed> $args The tool arguments: playlistId.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-11
	 */
	public function deleteKioskPlaylist(array $args): array {
		$refusal = ($this->validator->id(args: $args, name: 'playlistId') ?? $this->gate->requireUser());
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->run(
			operation: function () use ($args): array {
				$this->kiosk->revokePlaylist(id: (int)$args['playlistId'], callerId: $this->gate->currentUserId());
				return ['revoked' => true];
			}
		);
	}//end deleteKioskPlaylist()

	/**
	 * A refusal for a bad playlist name, entry list or refresh interval.
	 *
	 * @param array<string, mixed> $args The tool arguments.
	 *
	 * @return array<string, mixed>|null
	 */
	private function playlistRefusal(array $args): ?array {
		$refusal = $this->validator->string(args: $args, name: 'name', min: 1, max: 255);
		if ($refusal === null && is_array($args['entries'] ?? null) === false) {
			$refusal = $this->validator->refuse(code: 'invalid_arguments', message: 'Argument entries must be a list of playlist entries.');
		}

		if ($refusal === null && array_key_exists('refreshSeconds', $args) === true && is_int($args['refreshSeconds']) === false) {
			$refusal = $this->validator->refuse(code: 'invalid_arguments', message: 'Argument refreshSeconds must be an integer.');
		}

		return $refusal;
	}//end playlistRefusal()

	/**
	 * The playlist draft the kiosk routes build from the same fields.
	 *
	 * @param array<string, mixed> $args The tool arguments.
	 *
	 * @return KioskPlaylistDraft
	 */
	private function draft(array $args): KioskPlaylistDraft {
		return new KioskPlaylistDraft(
			name: (string)$args['name'],
			entries: $args['entries'],
			refresh: (int)($args['refreshSeconds'] ?? KioskService::REFRESH_DEFAULT)
		);
	}//end draft()

	/**
	 * Run a service call, mapping its failures to the tools' envelopes.
	 *
	 * @param \Closure(): array<string, mixed> $operation The service call.
	 *
	 * @return array<string, mixed>
	 */
	private function run(\Closure $operation): array {
		try {
			return $operation();
		} catch (Throwable $e) {
			return $this->validator->failure(exception: $e);
		}
	}//end run()
}//end class
