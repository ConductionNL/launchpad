<?php

/**
 * Unit tests for the MCP dashboard gate.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
 */

declare(strict_types=1);

namespace Unit\Mcp;

use OCA\LaunchPad\Mcp\McpDashboardGate;
use OCA\LaunchPad\Service\ActionAuthService;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use OCA\LaunchPad\Service\PermissionService;

/**
 * The gate allows only what the guards allow, and denies on any failure.
 *
 * @covers \OCA\LaunchPad\Mcp\McpDashboardGate
 * @uses   \OCA\LaunchPad\Mcp\McpArgumentValidator
 */
class McpDashboardGateTest extends TestCase {

	/**
	 * A gate over a session with the given user, or none.
	 *
	 * @param string|null       $uid    The signed-in uid.
	 * @param ActionAuthService $auth   The action guard.
	 * @param IGroupManager     $groups The admin check.
	 * @param PermissionService|null $permissions The per-dashboard access.
	 *
	 * @return McpDashboardGate
	 */
	private function gate(?string $uid, ActionAuthService $auth, IGroupManager $groups, ?PermissionService $permissions = null): McpDashboardGate {
		$session = $this->createMock(IUserSession::class);
		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session->method('getUser')->willReturn($user);

		return new McpDashboardGate(
			userSession: $session,
			actionAuth: $auth,
			groupManager: $groups,
			permissions: ($permissions ?? $this->createMock(originalClassName: PermissionService::class))
		);
	}//end gate()

	/**
	 * An allowed action passes, with the same action the route checks.
	 *
	 * @return void
	 */
	public function testAnAllowedActionPasses(): void {
		$auth = $this->createMock(ActionAuthService::class);
		$auth->expects($this->once())->method('requireAction')->with($this->isInstanceOf(IUser::class), 'dashboard.visible');

		$this->assertNull($this->gate('alice', $auth, $this->createMock(IGroupManager::class))->requireAction('dashboard.visible'));
	}//end testAnAllowedActionPasses()

	/**
	 * A refused action, and any exception from the guard, deny.
	 *
	 * @return void
	 */
	public function testAGuardFailureDenies(): void {
		foreach ([new OCSForbiddenException(), new RuntimeException('database gone')] as $failure) {
			$auth = $this->createMock(ActionAuthService::class);
			$auth->method('requireAction')->willThrowException($failure);

			$result = $this->gate('alice', $auth, $this->createMock(IGroupManager::class))->requireAction('dashboard.show');
			$this->assertSame('forbidden', $result['error'] ?? null, get_class($failure));
		}
	}//end testAGuardFailureDenies()

	/**
	 * Without a signed-in user nothing passes and the guard is not asked.
	 *
	 * @return void
	 */
	public function testNoUserIsUnauthenticated(): void {
		$auth = $this->createMock(ActionAuthService::class);
		$auth->expects($this->never())->method('requireAction');
		$gate = $this->gate(null, $auth, $this->createMock(IGroupManager::class));

		$this->assertSame('', $gate->currentUserId());
		$this->assertSame('unauthenticated', $gate->requireAction('dashboard.visible')['error']);
		$this->assertSame('unauthenticated', $gate->requireAdmin()['error']);
	}//end testNoUserIsUnauthenticated()

	/**
	 * Instance-reach tools pass only for an administrator; a failing admin check denies.
	 *
	 * @return void
	 */
	public function testOnlyAnAdministratorPassesTheAdminCheck(): void {
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(
			static function (string $uid): bool {
				if ($uid === 'broken') {
					throw new RuntimeException('ldap down');
				}

				return $uid === 'admin';
			}
		);
		$auth = $this->createMock(ActionAuthService::class);

		$this->assertNull($this->gate('admin', $auth, $groups)->requireAdmin());
		$this->assertSame('forbidden', $this->gate('alice', $auth, $groups)->requireAdmin()['error']);
		$this->assertSame('forbidden', $this->gate('broken', $auth, $groups)->requireAdmin()['error']);
	}//end testOnlyAnAdministratorPassesTheAdminCheck()

	/**
	 * Without a session the user check refuses; with one it passes.
	 *
	 * @return void
	 */
	public function testRequireUserNeedsASession(): void {
		$auth   = $this->createMock(originalClassName: ActionAuthService::class);
		$groups = $this->createMock(originalClassName: IGroupManager::class);

		$this->assertSame(expected: 'unauthenticated', actual: $this->gate(uid: null, auth: $auth, groups: $groups)->requireUser()['error']);
		$this->assertNull(actual: $this->gate(uid: 'alice', auth: $auth, groups: $groups)->requireUser());
	}//end testRequireUserNeedsASession()

	/**
	 * The view check asks PermissionService for the caller and the dashboard.
	 *
	 * @return void
	 */
	public function testRequireViewAsksPermissionService(): void {
		$permissions = $this->createMock(originalClassName: PermissionService::class);
		$permissions->method('canViewDashboard')->willReturnCallback(
			callback: static fn (string $userId, int $dashboardId): bool => ($userId === 'alice' && $dashboardId === 7)
		);
		$gate = $this->gate(
			uid: 'alice',
			auth: $this->createMock(originalClassName: ActionAuthService::class),
			groups: $this->createMock(originalClassName: IGroupManager::class),
			permissions: $permissions
		);

		$this->assertNull(actual: $gate->requireView(dashboardId: 7));
		$this->assertSame(expected: 'forbidden', actual: $gate->requireView(dashboardId: 8)['error']);
	}//end testRequireViewAsksPermissionService()

	/**
	 * A throwing permission check denies, never allows.
	 *
	 * @return void
	 */
	public function testAThrowingViewCheckDenies(): void {
		$permissions = $this->createMock(originalClassName: PermissionService::class);
		$permissions->method('canViewDashboard')->willThrowException(exception: new \RuntimeException('db down'));
		$gate = $this->gate(
			uid: 'alice',
			auth: $this->createMock(originalClassName: ActionAuthService::class),
			groups: $this->createMock(originalClassName: IGroupManager::class),
			permissions: $permissions
		);

		$this->assertSame(expected: 'forbidden', actual: $gate->requireView(dashboardId: 7)['error']);
	}//end testAThrowingViewCheckDenies()

	/**
	 * The metadata edit check asks PermissionService, and a throwing check denies.
	 *
	 * @return void
	 */
	public function testRequireMetadataEditAsksPermissionService(): void {
		$permissions = $this->createMock(originalClassName: PermissionService::class);
		$permissions->method('canEditDashboardMetadata')->willReturnCallback(
			callback: static fn (string $userId, int $dashboardId): bool => match ($dashboardId) {
				7 => $userId === 'alice',
				default => throw new \RuntimeException('db down'),
			}
		);
		$gate = $this->gate(
			uid: 'alice',
			auth: $this->createMock(originalClassName: ActionAuthService::class),
			groups: $this->createMock(originalClassName: IGroupManager::class),
			permissions: $permissions
		);

		$this->assertNull(actual: $gate->requireMetadataEdit(dashboardId: 7));
		$this->assertSame(expected: 'forbidden', actual: $gate->requireMetadataEdit(dashboardId: 8)['error']);
	}//end testRequireMetadataEditAsksPermissionService()
}//end class
