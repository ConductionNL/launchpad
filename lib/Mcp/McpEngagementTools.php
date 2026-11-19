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

use Closure;
use OCA\LaunchPad\Service\AcknowledgementService;
use OCA\LaunchPad\Service\PermissionDeniedException;
use OCA\LaunchPad\Service\ReactionService;
use OCA\LaunchPad\Service\ReactionsDisabledException;
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
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) The provider passes every tool its arguments.
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

	/**
	 * Add the caller's reaction to a dashboard and answer the new summary.
	 *
	 * @param array<string, mixed> $args The tool arguments: dashboardUuid, emoji.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
	 */
	public function addReaction(array $args): array {
		$refusal = ($this->reactionArgumentsRefusal(args: $args) ?? $this->gate->requireAction(action: 'dashboard-reaction.add-reaction'));
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->react(
			operation: fn (string $userId): array => $this->reactions->addReaction(
				dashboardUuid: (string)$args['dashboardUuid'],
				userId: $userId,
				emoji: (string)$args['emoji']
			)
		);
	}//end addReaction()

	/**
	 * Remove the caller's reaction from a dashboard.
	 *
	 * @param array<string, mixed> $args The tool arguments: dashboardUuid, emoji.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
	 */
	public function removeReaction(array $args): array {
		$refusal = ($this->reactionArgumentsRefusal(args: $args) ?? $this->gate->requireAction(action: 'dashboard-reaction.remove-reaction'));
		if ($refusal !== null) {
			return $refusal;
		}

		return $this->react(
			operation: fn (string $userId): array => [
				'removed' => $this->reactions->removeReaction(
					dashboardUuid: (string)$args['dashboardUuid'],
					userId: $userId,
					emoji: (string)$args['emoji']
				),
			]
		);
	}//end removeReaction()

	/**
	 * A refusal for a bad dashboard UUID or emoji, or NULL to proceed.
	 *
	 * @param array<string, mixed> $args The tool arguments.
	 *
	 * @return array<string, mixed>|null
	 */
	private function reactionArgumentsRefusal(array $args): ?array {
		return ($this->validator->uuid(args: $args, name: 'dashboardUuid')
			?? $this->validator->string(args: $args, name: 'emoji', min: 1, max: 32));
	}//end reactionArgumentsRefusal()

	/**
	 * Run a reaction change for the caller, mapping the reaction service's
	 * refusals as the routes do.
	 *
	 * @param Closure(string): array<mixed> $operation The service call, given the caller.
	 *
	 * @return array<string, mixed>
	 */
	private function react(Closure $operation): array {
		try {
			return $operation($this->gate->currentUserId());
		} catch (PermissionDeniedException | ReactionsDisabledException $e) {
			return $this->validator->refuse(code: 'forbidden', message: $e->getMessage());
		} catch (Throwable $e) {
			return $this->validator->failure(exception: $e);
		}
	}//end react()
}//end class
