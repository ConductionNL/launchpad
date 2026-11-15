<?php

/**
 * DashboardApiController update access test
 *
 * Traces the guard chain of `update()`: who is asked, for which
 * dashboard, and what a refusal answers, with permission doubles that say
 * yes only for the acting user.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\DashboardApiController;
use OCA\LaunchPad\Controller\DashboardApiHistory;
use OCA\LaunchPad\Controller\DashboardApiServices;
use OCA\LaunchPad\Controller\RequestActor;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\AnalyticsService;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\DashboardVersionService;
use OCA\LaunchPad\Service\PermissionService;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * The update() guards, case by case.
 *
 * @covers \OCA\LaunchPad\Controller\DashboardApiController
 * @uses   \OCA\LaunchPad\Controller\ResponseHelper
 * @uses   \OCA\LaunchPad\Controller\RespondsWithErrors
 * @uses   \OCA\LaunchPad\Controller\DashboardApiHistory
 * @uses   \OCA\LaunchPad\Controller\DashboardApiServices
 * @uses   \OCA\LaunchPad\Controller\RequestActor
 * @uses   \OCA\LaunchPad\Db\Dashboard
 */
class DashboardApiControllerUpdateAccessTest extends TestCase {

	/**
	 * Permission calls seen, as [method, userId, dashboardId].
	 *
	 * @var list<array{0: string, 1: string, 2: int}>
	 */
	private array $asked = [];

	/**
	 * Saves seen, as [dashboardId, userId].
	 *
	 * @var list<array{0: int, 1: string}>
	 */
	private array $saved = [];

	/**
	 * Action checks seen, as [uid, action].
	 *
	 * @var list<array{0: string, 1: string}>
	 */
	private array $actions = [];

	/**
	 * Build the controller.
	 *
	 * @param string|null $sessionUid The logged-in user, or null for none.
	 * @param string|null $userId     The injected session user id.
	 * @param string      $allowed    The only user the permission doubles allow.
	 * @param bool        $denyAction Whether the action check refuses.
	 *
	 * @return DashboardApiController
	 */
	private function controller(?string $sessionUid, ?string $userId, string $allowed='alice', bool $denyAction=false): DashboardApiController {
		$user = null;
		if ($sessionUid !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($sessionUid);
		}

		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		$actionAuth = $this->createMock(ActionAuthService::class);
		$actionAuth->method('requireAction')->willReturnCallback(
			function (IUser $actor, string $action) use ($denyAction): void {
				$this->actions[] = [$actor->getUID(), $action];
				if ($denyAction === true) {
					throw new RuntimeException('action refused');
				}
			}
		);

		$permissions = $this->createMock(PermissionService::class);
		foreach (['canEditDashboard', 'canEditDashboardMetadata'] as $method) {
			$permissions->method($method)->willReturnCallback(
				function (string $userId, int $dashboardId) use ($method, $allowed): bool {
					$this->asked[] = [$method, $userId, $dashboardId];
					return $userId === $allowed;
				}
			);
		}

		$service = $this->createMock(DashboardService::class);
		$service->method('updateDashboard')->willReturnCallback(
			function (int $dashboardId, string $userId, array $data): Dashboard {
				$this->saved[] = [$dashboardId, $userId];
				return new Dashboard();
			}
		);

		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(static fn (string $key) => $key === 'userId' ? 'alice' : null);

		return new DashboardApiController(
			request: $request,
			services: new DashboardApiServices(dashboardService: $service),
			permissionService: $permissions,
			history: new DashboardApiHistory(
				treeService: $this->createMock(DashboardTreeService::class),
				versionService: $this->createMock(DashboardVersionService::class),
				analyticsService: $this->createMock(AnalyticsService::class),
				logger: $this->createMock(LoggerInterface::class)
			),
			actor: new RequestActor(userSession: $session, actionAuth: $actionAuth),
			userId: $userId
		);
	}//end controller()

	/**
	 * Without a session the update answers 401 and asks nothing.
	 *
	 * @return void
	 */
	public function testAnonymousIsUnauthorised(): void {
		$response = $this->controller(sessionUid: null, userId: null)->update(7, name: 'x');

		$this->assertSame(401, $response->getStatus());
		$this->assertSame([[], [], []], [$this->actions, $this->asked, $this->saved]);
	}//end testAnonymousIsUnauthorised()

	/**
	 * A session without an injected user id answers 401 after the action check.
	 *
	 * @return void
	 */
	public function testSessionWithoutUserIdIsUnauthorised(): void {
		$response = $this->controller(sessionUid: 'alice', userId: null)->update(7, name: 'x');

		$this->assertSame(401, $response->getStatus());
		$this->assertSame([['alice', 'dashboard.update']], $this->actions);
		$this->assertSame([[], []], [$this->asked, $this->saved]);
	}//end testSessionWithoutUserIdIsUnauthorised()

	/**
	 * A refused action check stops the update before any permission check.
	 *
	 * @return void
	 */
	public function testRefusedActionStopsTheUpdate(): void {
		try {
			$this->controller(sessionUid: 'alice', userId: 'alice', denyAction: true)->update(7, name: 'x');
			$this->fail('the action refusal must propagate');
		} catch (RuntimeException $e) {
			$this->assertSame('action refused', $e->getMessage());
		}

		$this->assertSame([[], []], [$this->asked, $this->saved]);
	}//end testRefusedActionStopsTheUpdate()

	/**
	 * A metadata-only update asks the metadata permission for the acting user.
	 *
	 * @return void
	 */
	public function testMetadataUpdateAsksTheMetadataPermission(): void {
		$response = $this->controller(sessionUid: 'alice', userId: 'alice')->update(7, name: 'x');

		$this->assertSame(200, $response->getStatus());
		$this->assertSame([['canEditDashboardMetadata', 'alice', 7]], $this->asked);
		$this->assertSame([[7, 'alice']], $this->saved);
	}//end testMetadataUpdateAsksTheMetadataPermission()

	/**
	 * A placement update asks the full edit permission for the acting user.
	 *
	 * @return void
	 */
	public function testPlacementUpdateAsksTheEditPermission(): void {
		$response = $this->controller(sessionUid: 'alice', userId: 'alice')->update(7, placements: []);

		$this->assertSame(200, $response->getStatus());
		$this->assertSame([['canEditDashboard', 'alice', 7]], $this->asked);
		$this->assertSame([[7, 'alice']], $this->saved);
	}//end testPlacementUpdateAsksTheEditPermission()

	/**
	 * Another user is refused 403 for metadata and placements, and nothing is saved,
	 * even when the request names the allowed user.
	 *
	 * @return void
	 */
	public function testOtherUserIsForbidden(): void {
		$metadata  = $this->controller(sessionUid: 'bob', userId: 'bob')->update(7, name: 'x');
		$placement = $this->controller(sessionUid: 'bob', userId: 'bob')->update(7, placements: []);

		$this->assertSame([403, 403], [$metadata->getStatus(), $placement->getStatus()]);
		$this->assertSame([['canEditDashboardMetadata', 'bob', 7], ['canEditDashboard', 'bob', 7]], $this->asked);
		$this->assertSame([], $this->saved);
	}//end testOtherUserIsForbidden()

	/**
	 * The permission check runs for the injected user id, not the session's UID.
	 *
	 * @return void
	 */
	public function testPermissionUsesTheInjectedUserId(): void {
		$response = $this->controller(sessionUid: 'alice', userId: 'bob')->update(7, name: 'x');

		$this->assertSame(403, $response->getStatus());
		$this->assertSame([['canEditDashboardMetadata', 'bob', 7]], $this->asked);
	}//end testPermissionUsesTheInjectedUserId()
}//end class
