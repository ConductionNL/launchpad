<?php

/**
 * LaunchPad McpDashboardGate
 *
 * The one place MCP tools authorise: the signed-in caller, the ADR-023 action
 * the matching HTTP route requires, and the admin check for instance-reach
 * tools. It delegates to ActionAuthService and IGroupManager and never
 * re-implements them. Every failure denies: an exception from a guard is a
 * refusal, never an allow (mcp-tool-surface REQ-MCP-004).
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

use OCA\LaunchPad\Service\ActionAuthService;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use Throwable;

/**
 * Authorisation for the MCP tools.
 *
 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
 */
class McpDashboardGate {

	/**
	 * Constructor.
	 *
	 * @param IUserSession      $userSession  The session the tool call runs in.
	 * @param ActionAuthService $actionAuth   ADR-023 action authorisation.
	 * @param IGroupManager     $groupManager The admin check.
	 */
	public function __construct(
		private readonly IUserSession $userSession,
		private readonly ActionAuthService $actionAuth,
		private readonly IGroupManager $groupManager,
	) {
	}//end __construct()

	/**
	 * The signed-in caller's uid, or an empty string without one.
	 *
	 * @return string The uid.
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public function currentUserId(): string {
		return (string)($this->userSession->getUser()?->getUID() ?? '');
	}//end currentUserId()

	/**
	 * Refuse unless a user is signed in and the ADR-023 action allows them, as the HTTP route does.
	 *
	 * @param string $action The action the matching route requires, e.g. `dashboard.visible`.
	 *
	 * @return array<string, mixed>|null The refusal, or null when the caller may proceed.
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public function requireAction(string $action): ?array {
		$user = $this->userSession->getUser();
		if ($user instanceof IUser === false) {
			return McpArgumentValidator::error(code: 'unauthenticated', message: 'This tool needs a signed-in user.');
		}

		try {
			$this->actionAuth->requireAction($user, $action);
		} catch (Throwable) {
			return McpArgumentValidator::error(code: 'forbidden', message: "You may not perform {$action}.");
		}

		return null;
	}//end requireAction()

	/**
	 * Refuse unless the signed-in caller is an administrator, for instance-reach tools.
	 *
	 * @return array<string, mixed>|null The refusal, or null for an administrator.
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public function requireAdmin(): ?array {
		$userId = $this->currentUserId();
		if ($userId === '') {
			return McpArgumentValidator::error(code: 'unauthenticated', message: 'This tool needs a signed-in user.');
		}

		try {
			$isAdmin = ($this->groupManager->isAdmin($userId) === true);
		} catch (Throwable) {
			$isAdmin = false;
		}

		if ($isAdmin === false) {
			return McpArgumentValidator::error(code: 'forbidden', message: 'Only an administrator may use this tool.');
		}

		return null;
	}//end requireAdmin()
}//end class
