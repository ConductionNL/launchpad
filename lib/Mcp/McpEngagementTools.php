<?php

/**
 * LaunchPad McpEngagementTools
 *
 * Read tools over a dashboard's reactions and the caller's pending
 * acknowledgements. Each applies the check of the matching HTTP route and calls
 * the service that route calls (mcp-tool-surface REQ-MCP-002).
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

use OCA\LaunchPad\Service\AcknowledgementService;
use OCA\LaunchPad\Service\ReactionService;
use Throwable;

/**
 * The reaction and acknowledgement read tools.
 *
 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-7
 */
class McpEngagementTools {

	/**
	 * Constructor.
	 *
	 * @param McpDashboardGate       $gate             The caller and the ADR-023 action check.
	 * @param McpArgumentValidator   $validator        The argument checks.
	 * @param ReactionService        $reactions        The service `GET /api/dashboards/{uuid}/reactions` calls.
	 * @param AcknowledgementService $acknowledgements The service `GET /api/acknowledgements/pending` calls.
	 */
	public function __construct(
		private readonly McpDashboardGate $gate,
		private readonly McpArgumentValidator $validator,
		private readonly ReactionService $reactions,
		private readonly AcknowledgementService $acknowledgements,
	) {
	}//end __construct()

	/**
	 * The reaction summary of a dashboard.
	 *
	 * @param array<string, mixed> $args The tool arguments: dashboardUuid.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-7
	 */
	public function getReactions(array $args): array {
		$refusal = ($this->validator->uuid(args: $args, name: 'dashboardUuid')
			?? $this->gate->requireAction(action: 'dashboard-reaction.get-reactions'));
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			return $this->reactions->getReactionsSummary(
				dashboardUuid: (string)$args['dashboardUuid'],
				userId: $this->gate->currentUserId()
			);
		} catch (Throwable $e) {
			return $this->validator->failure(exception: $e);
		}
	}//end getReactions()

	/**
	 * The acknowledgements the caller has still to give.
	 *
	 * @param array<string, mixed> $args The tool arguments (none).
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-7
	 */
	public function listPendingAcknowledgements(array $args): array {
		$refusal = $this->gate->requireUser();
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			return $this->acknowledgements->getPending(userId: $this->gate->currentUserId());
		} catch (Throwable $e) {
			return $this->validator->failure(exception: $e);
		}
	}//end listPendingAcknowledgements()
}//end class
