<?php

/**
 * LaunchPad McpSharingTools
 *
 * The tools over a dashboard's user and group shares. Each applies
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
use OCA\LaunchPad\Service\ShareGrant;
use Throwable;

/**
 * The share tools.
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
	 */
	public function __construct(
		private readonly McpDashboardGate $gate,
		private readonly McpArgumentValidator $validator,
		private readonly DashboardShareService $shares,
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
		$refusal = ($this->validator->positiveId(args: $args, name: 'dashboardId') ?? $this->gate->requireUser());
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
	 * Share a dashboard the caller owns with a user or a group.
	 *
	 * @param array<string, mixed> $args The tool arguments: dashboardId, shareType, shareWith, permissionLevel.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-10
	 */
	public function shareDashboard(array $args): array {
		$refusal = ($this->validator->positiveId(args: $args, name: 'dashboardId')
			?? $this->validator->oneOf(args: $args, name: 'shareType', allowed: ['user', 'group'])
			?? $this->validator->string(args: $args, name: 'shareWith', min: 1, max: 255)
			?? $this->validator->oneOf(args: $args, name: 'permissionLevel', allowed: ['view_only', 'add_only', 'full'])
			?? $this->gate->requireUser());
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->validator->attempt(
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
		$refusal = ($this->validator->positiveId(args: $args, name: 'dashboardId') ?? $this->gate->requireUser());
		if ($refusal === null && is_array($args['shares'] ?? null) === false) {
			$refusal = $this->validator->refuse(code: 'invalid_arguments', message: 'Argument shares must be a list of shares.');
		}

		if ($refusal !== null) {
			return $refusal;
		}

		return $this->validator->attempt(
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
		$refusal = ($this->validator->positiveId(args: $args, name: 'shareId') ?? $this->gate->requireUser());
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->validator->attempt(
			operation: function () use ($args): array {
				$this->shares->removeShare(shareId: (int)$args['shareId'], callerId: $this->gate->currentUserId());
				return ['removed' => true];
			}
		);
	}//end unshareDashboard()

}//end class
