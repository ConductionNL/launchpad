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

use Closure;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\PermissionService;
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
	 * @param PermissionService $permissions  The per-dashboard access the HTTP routes apply.
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-4
	 */
	public function __construct(
		private readonly IUserSession $userSession,
		private readonly ActionAuthService $actionAuth,
		private readonly IGroupManager $groupManager,
		private readonly PermissionService $permissions,
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

	/**
	 * A refusal when no user is signed in, or NULL to proceed. For the tools
	 * whose route asks no ADR-023 action, only a session.
	 *
	 * @return array<string, mixed>|null
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-4
	 */
	public function requireUser(): ?array {
		if ($this->currentUserId() === '') {
			return (new McpArgumentValidator())->refuse(code: 'unauthenticated', message: 'This tool needs a signed-in user.');
		}

		return null;
	}//end requireUser()

	/**
	 * A refusal when the caller may not see the dashboard, or NULL to proceed.
	 * Any failure of the permission check denies.
	 *
	 * @param int $dashboardId The dashboard.
	 *
	 * @return array<string, mixed>|null
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-4
	 */
	public function requireView(int $dashboardId): ?array {
		return $this->refuseUnless(
			check: fn (string $userId): bool => $this->permissions->canViewDashboard(userId: $userId, dashboardId: $dashboardId),
			message: 'You may not see this dashboard.'
		);
	}//end requireView()

	/**
	 * A refusal when the caller may not edit the dashboard's metadata (the
	 * owner of a personal dashboard, an administrator for a template), or
	 * NULL to proceed. Any failure of the permission check denies.
	 *
	 * @param int $dashboardId The dashboard.
	 *
	 * @return array<string, mixed>|null
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
	 */
	public function requireMetadataEdit(int $dashboardId): ?array {
		return $this->refuseUnless(
			check: fn (string $userId): bool => $this->permissions->canEditDashboardMetadata(userId: $userId, dashboardId: $dashboardId),
			message: 'You may not edit the metadata of this dashboard.'
		);
	}//end requireMetadataEdit()

	/**
	 * A refusal when the caller may not do this to a widget, or NULL to
	 * proceed: `add` a widget to the dashboard, or `style` (change) or
	 * `remove` the placement. Any failure of the permission check denies.
	 *
	 * @param string $operation The operation: add, style or remove.
	 * @param int    $id        The dashboard for `add`, the placement otherwise.
	 *
	 * @return array<string, mixed>|null
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
	 */
	public function requireWidgetPermission(string $operation, int $id): ?array {
		return $this->refuseUnless(
			check: fn (string $userId): bool => match ($operation) {
				'add' => $this->permissions->canAddWidget(userId: $userId, dashboardId: $id),
				'style' => $this->permissions->canStyleWidget(userId: $userId, placementId: $id),
				'remove' => $this->permissions->canRemoveWidget(userId: $userId, placementId: $id),
				default => false,
			},
			message: 'You may not change the widgets here.'
		);
	}//end requireWidgetPermission()

	/**
	 * A refusal when the caller may not create a dashboard, or may not have one
	 * more, or NULL to proceed. Any failure of the permission check denies.
	 *
	 * @param bool $hasDashboards Whether the caller already has a dashboard.
	 *
	 * @return array<string, mixed>|null
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
	 */
	public function requireCreateDashboard(bool $hasDashboards): ?array {
		return $this->refuseUnless(
			check: fn (string $userId): bool => $this->permissions->canCreateDashboard(userId: $userId)
				&& ($hasDashboards === false || $this->permissions->canHaveMultipleDashboards()),
			message: 'You may not create a dashboard, or not another one.'
		);
	}//end requireCreateDashboard()

	/**
	 * A forbidden refusal unless the check allows the caller. A check that
	 * throws denies.
	 *
	 * @param Closure(string): bool $check   The permission check for a uid.
	 * @param string                $message The refusal sentence.
	 *
	 * @return array<string, mixed>|null
	 */
	private function refuseUnless(Closure $check, string $message): ?array {
		try {
			$allowed = ($check($this->currentUserId()) === true);
		} catch (Throwable) {
			$allowed = false;
		}

		if ($allowed === false) {
			return (new McpArgumentValidator())->refuse(code: 'forbidden', message: $message);
		}

		return null;
	}//end refuseUnless()
}//end class
