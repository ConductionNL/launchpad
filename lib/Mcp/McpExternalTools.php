<?php

/**
 * LaunchPad McpExternalTools
 *
 * The tools over anonymous access: kiosk playlists and a dashboard's public
 * links. Each applies the check of its `kiosk` or `publicShare` route and calls
 * the service that route calls (mcp-tool-surface REQ-MCP-002, REQ-MCP-003).
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

use OCA\LaunchPad\Service\KioskPlaylistDraft;
use OCA\LaunchPad\Service\KioskService;
use Throwable;

/**
 * The kiosk and public link tools.
 *
 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-11
 */
class McpExternalTools {

	/**
	 * Constructor.
	 *
	 * @param McpDashboardGate     $gate      The caller.
	 * @param McpArgumentValidator $validator The argument checks.
	 * @param McpExternalSources   $external  The kiosk and public-link services the routes call.
	 */
	public function __construct(
		private readonly McpDashboardGate $gate,
		private readonly McpArgumentValidator $validator,
		private readonly McpExternalSources $external,
	) {
	}//end __construct()

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

		$playlists = $this->external->kiosk->listPlaylists(callerId: $this->gate->currentUserId());
		return ['items' => array_map(callback: static fn ($playlist): array => $playlist->jsonSerialize(), array: $playlists)];
	}//end listKioskPlaylists()

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

		return $this->validator->attempt(
			operation: fn (): array => $this->external->kiosk->createPlaylist(
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
		$refusal = ($this->validator->positiveId(args: $args, name: 'playlistId') ?? $this->playlistRefusal(args: $args) ?? $this->gate->requireUser());
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->validator->attempt(
			operation: fn (): array => $this->external->kiosk->updatePlaylist(
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
		$refusal = ($this->validator->positiveId(args: $args, name: 'playlistId') ?? $this->gate->requireUser());
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->validator->attempt(
			operation: function () use ($args): array {
				$this->external->kiosk->revokePlaylist(id: (int)$args['playlistId'], callerId: $this->gate->currentUserId());
				return ['revoked' => true];
			}
		);
	}//end deleteKioskPlaylist()

	/**
	 * Mint an anonymous public link to a dashboard the caller owns, optionally
	 * with a password and an expiry date.
	 *
	 * @param array<string, mixed> $args The tool arguments: dashboardUuid, password, expiresAt.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-11
	 */
	public function createPublicShareLink(array $args): array {
		$refusal = ($this->validator->uuid(args: $args, name: 'dashboardUuid')
			?? $this->validator->optional(args: $args, name: 'password', refusal: $this->validator->string(args: $args, name: 'password', min: 1, max: 255))
			?? $this->validator->optional(args: $args, name: 'expiresAt', refusal: $this->validator->string(args: $args, name: 'expiresAt', min: 10, max: 32))
			?? $this->gate->requireUser());
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			return $this->external->links->createPublicShare(
				dashboardUuid: (string)$args['dashboardUuid'],
				callerId: $this->gate->currentUserId(),
				password: ($args['password'] ?? null),
				expiresAt: ($args['expiresAt'] ?? null)
			)->jsonSerialize();
		} catch (Throwable $e) {
			return $this->validator->failure(exception: $e);
		}
	}//end createPublicShareLink()

	/**
	 * Revoke one public link of a dashboard the caller owns.
	 *
	 * @param array<string, mixed> $args The tool arguments: dashboardUuid, shareId.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-11
	 */
	public function revokePublicShareLink(array $args): array {
		$refusal = ($this->validator->uuid(args: $args, name: 'dashboardUuid')
			?? $this->validator->positiveId(args: $args, name: 'shareId')
			?? $this->gate->requireUser());
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			$this->external->links->revokeShare(
				dashboardUuid: (string)$args['dashboardUuid'],
				shareId: (int)$args['shareId'],
				callerId: $this->gate->currentUserId()
			);
		} catch (Throwable $e) {
			return $this->validator->failure(exception: $e);
		}

		return ['revoked' => true];
	}//end revokePublicShareLink()

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
}//end class
