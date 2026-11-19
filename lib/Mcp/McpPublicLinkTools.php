<?php

/**
 * LaunchPad McpPublicLinkTools
 *
 * The tools that mint and revoke a dashboard's anonymous public link. Each
 * applies the check of its `publicShare` route and calls the service that
 * route calls (mcp-tool-surface REQ-MCP-003, reach external).
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

use OCA\LaunchPad\Service\PublicShareService;
use Throwable;

/**
 * The public link tools.
 *
 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-11
 */
class McpPublicLinkTools {

	/**
	 * Constructor.
	 *
	 * @param McpDashboardGate     $gate      The caller.
	 * @param McpArgumentValidator $validator The argument checks.
	 * @param PublicShareService   $links     The service the `publicShare` routes call.
	 */
	public function __construct(
		private readonly McpDashboardGate $gate,
		private readonly McpArgumentValidator $validator,
		private readonly PublicShareService $links,
	) {
	}//end __construct()

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
			?? $this->validator->string(args: $args, name: 'password', min: 1, max: 255, required: false)
			?? $this->validator->string(args: $args, name: 'expiresAt', min: 10, max: 32, required: false)
			?? $this->gate->requireUser());
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			return $this->links->createPublicShare(
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
			?? $this->validator->id(args: $args, name: 'shareId')
			?? $this->gate->requireUser());
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			$this->links->revokeShare(
				dashboardUuid: (string)$args['dashboardUuid'],
				shareId: (int)$args['shareId'],
				callerId: $this->gate->currentUserId()
			);
		} catch (Throwable $e) {
			return $this->validator->failure(exception: $e);
		}

		return ['revoked' => true];
	}//end revokePublicShareLink()
}//end class
