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
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\AnalyticsService;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\DashboardVersionService;
use OCA\LaunchPad\Service\PermissionService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use OCA\LaunchPad\Controller\ResponseHelper;
use Psr\Log\NullLogger;
use OCA\LaunchPad\Controller\ActorResponses;
use OCA\LaunchPad\Controller\DashboardApiAccess;
use OCA\LaunchPad\Controller\UserResponses;

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
 * @uses   \OCA\LaunchPad\Controller\ActorResponses
 * @uses   \OCA\LaunchPad\Controller\DashboardApiAccess
 * @uses   \OCA\LaunchPad\Controller\UserResponses
 * @uses   \OCA\LaunchPad\Exception\ForbiddenException
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
	 * @param array       $body       Extra request parameters by name.
	 * @param DashboardVersionService|null $versions The version service, a plain double when null.
	 *
	 * @return DashboardApiController
	 */
	private function controller(
		?string $sessionUid,
		?string $userId,
		string $allowed='alice',
		bool $denyAction=false,
		array $body=[],
		?DashboardVersionService $versions=null,
		?\Exception $serviceRefusal=null,
		?string $metadataAllowed=null,
		bool $mayDelete=true
	): DashboardApiController {
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
		$allowedFor  = ['canEditDashboard' => $allowed, 'canEditDashboardMetadata' => ($metadataAllowed ?? $allowed)];
		foreach ($allowedFor as $method => $who) {
			$permissions->method($method)->willReturnCallback(
				function (string $userId, int $dashboardId) use ($method, $who): bool {
					$this->asked[] = [$method, $userId, $dashboardId];
					return $userId === $who;
				}
			);
		}
		$permissions->method('canDeleteDashboards')->willReturn($mayDelete);

		$service = $this->createMock(DashboardService::class);
		// The dashboard exists: a refused update stays 403 (a deleted one is 404, REQ-LOCK-008).
		$service->method('dashboardExists')->willReturn(true);
		$service->method('updateDashboard')->willReturnCallback(
			function (int $dashboardId, string $userId, array $data) use ($serviceRefusal): Dashboard {
				if ($serviceRefusal !== null) {
					throw $serviceRefusal;
				}

				$this->saved[] = [$dashboardId, $userId];
				return new Dashboard();
			}
		);
		if ($serviceRefusal !== null) {
			$service->method('deleteDashboard')->willThrowException($serviceRefusal);
		}
		if ($mayDelete === false) {
			$service->expects($this->never())->method('deleteDashboard');
		}

		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(static fn (string $key) => $key === 'userId' ? 'alice' : ($body[$key] ?? null));

		return new DashboardApiController(
			request: $request,
			services: new DashboardApiServices(dashboardService: $service),
			access: new DashboardApiAccess(permissionService: $permissions, history: new DashboardApiHistory(
				treeService: $this->createMock(DashboardTreeService::class),
				versionService: ($versions ?? $this->createMock(originalClassName: DashboardVersionService::class)),
				analyticsService: $this->createMock(AnalyticsService::class),
				logger: $this->createMock(LoggerInterface::class)
			)),
			context: new ActorResponses(actor: new RequestActor(
				userSession: $session,
				actionAuth: $actionAuth
			), responses: new UserResponses(userId: $userId, responseHelper: new ResponseHelper(
				logger: new NullLogger()
			)))
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
	 * Changing how tiles open is a dashboard edit: it asks the full edit
	 * permission, and a user who only may edit metadata is refused.
	 *
	 * @return void
	 */
	public function testLinkTargetUpdateAsksTheFullEditPermission(): void {
		$response = $this->controller(sessionUid: 'alice', userId: 'alice', body: ['defaultLinkTarget' => 'side-panel'])->update(id: 7);

		$this->assertSame(200, $response->getStatus());
		$this->assertSame([['canEditDashboard', 'alice', 7]], $this->asked);
		$this->assertSame([[7, 'alice']], $this->saved);
	}//end testLinkTargetUpdateAsksTheFullEditPermission()

	/**
	 * A link target change by a user without edit permission answers 403 and saves nothing.
	 *
	 * @return void
	 */
	public function testLinkTargetUpdateWithoutEditPermissionIsForbidden(): void {
		$response = $this->controller(sessionUid: 'alice', userId: 'alice', allowed: 'bob', body: ['defaultLinkTarget' => ''])->update(id: 7, name: 'x');

		$this->assertSame(403, $response->getStatus());
		$this->assertSame([['canEditDashboard', 'alice', 7]], $this->asked);
		$this->assertSame([], $this->saved);
	}//end testLinkTargetUpdateWithoutEditPermissionIsForbidden()

	/**
	 * A non-string link target is ignored, so the update stays metadata-only.
	 *
	 * @return void
	 */
	public function testNonStringLinkTargetKeepsTheUpdateMetadataOnly(): void {
		$response = $this->controller(sessionUid: 'alice', userId: 'alice', body: ['defaultLinkTarget' => ['side-panel']])->update(id: 7, name: 'x');

		$this->assertSame(200, $response->getStatus());
		$this->assertSame([['canEditDashboardMetadata', 'alice', 7]], $this->asked);
	}//end testNonStringLinkTargetKeepsTheUpdateMetadataOnly()

	/**
	 * Changing the hierarchies is a dashboard edit: it asks the full edit
	 * permission and hands them to the service.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
	 */
	public function testHierarchiesUpdateAsksTheFullEditPermission(): void {
		$response = $this->controller(sessionUid: 'alice', userId: 'alice', body: ['hierarchies' => []])->update(id: 7);

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: [['canEditDashboard', 'alice', 7]], actual: $this->asked);
		$this->assertSame(expected: [[7, 'alice']], actual: $this->saved);
	}//end testHierarchiesUpdateAsksTheFullEditPermission()

	/**
	 * A viewer who sends hierarchies is refused with 403 and nothing is saved.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
	 */
	public function testHierarchiesUpdateWithoutEditPermissionIsForbidden(): void {
		$body     = ['hierarchies' => [['id' => 'x', 'levels' => [['dimension' => 'a']]]]];
		$response = $this->controller(sessionUid: 'alice', userId: 'alice', allowed: 'bob', body: $body)->update(id: 7);

		$this->assertSame(expected: 403, actual: $response->getStatus());
		$this->assertSame(expected: [], actual: $this->saved);
	}//end testHierarchiesUpdateWithoutEditPermissionIsForbidden()

	/**
	 * A malformed hierarchies value refused by the service answers 400.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
	 */
	public function testAMalformedHierarchiesValueIsABadRequest(): void {
		$response = $this->controller(
			sessionUid: 'alice',
			userId: 'alice',
			body: ['hierarchies' => 'nl-administrative'],
			serviceRefusal: new \InvalidArgumentException('hierarchies must be a list')
		)->update(id: 7);

		$this->assertSame(expected: 400, actual: $response->getStatus());
		$this->assertSame(expected: [['canEditDashboard', 'alice', 7]], actual: $this->asked);
	}//end testAMalformedHierarchiesValueIsABadRequest()

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
	 * A layout update that also renames needs both permissions: a group's
	 * editor may change the layout but not rename, and nothing is saved.
	 *
	 * @return void
	 */
	public function testALayoutUpdateWithARenameNeedsTheMetadataPermissionToo(): void {
		$response = $this->controller(sessionUid: 'alice', userId: 'alice', metadataAllowed: 'bob')->update(7, name: 'x', placements: []);

		$this->assertSame(403, $response->getStatus());
		$this->assertSame([['canEditDashboard', 'alice', 7], ['canEditDashboardMetadata', 'alice', 7]], $this->asked);
		$this->assertSame([], $this->saved);
	}//end testALayoutUpdateWithARenameNeedsTheMetadataPermissionToo()

	/**
	 * A layout-only update by a user who may edit the layout but not rename is saved.
	 *
	 * @return void
	 */
	public function testALayoutOnlyUpdateDoesNotAskTheMetadataPermission(): void {
		$response = $this->controller(sessionUid: 'alice', userId: 'alice', metadataAllowed: 'bob')->update(7, placements: []);

		$this->assertSame(200, $response->getStatus());
		$this->assertSame([['canEditDashboard', 'alice', 7]], $this->asked);
		$this->assertSame([[7, 'alice']], $this->saved);
	}//end testALayoutOnlyUpdateDoesNotAskTheMetadataPermission()

	/**
	 * REQ-ROLE-008: a Viewer's delete answers 403 before the delete runs,
	 * even for a dashboard they own.
	 *
	 * @return void
	 */
	public function testAViewersDeleteIsForbidden(): void {
		$response = $this->controller(sessionUid: 'alice', userId: 'alice', mayDelete: false)->delete(7);

		$this->assertSame(expected: Http::STATUS_FORBIDDEN, actual: $response->getStatus());
	}//end testAViewersDeleteIsForbidden()

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
	/**
	 * REQ-VERS-001: the automatic snapshot holds the dashboard as it was before
	 * the PUT, taken before the save and written once the save succeeded.
	 *
	 * @return void
	 */
	public function testAnUpdateSnapshotsTheStateItReplaces(): void {
		$before = new Dashboard();
		$before->setName('Before');
		$versions = $this->createMock(originalClassName: DashboardVersionService::class);
		$versions->expects($this->once())->method('stateBeforeUpdate')->with(7)
			->willReturn(['dashboard' => $before, 'snapshotJson' => '{"before":true}']);
		$versions->expects($this->once())->method('captureSnapshot')
			->with($this->identicalTo(value: $before), 'alice', ['snapshotJson' => '{"before":true}']);

		$response = $this->controller(sessionUid: 'alice', userId: 'alice', versions: $versions)->update(7, name: 'After');

		$this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
	}//end testAnUpdateSnapshotsTheStateItReplaces()

	/**
	 * A refused update records no snapshot.
	 *
	 * @return void
	 */
	public function testARefusedUpdateRecordsNoSnapshot(): void {
		$versions = $this->createMock(originalClassName: DashboardVersionService::class);
		$versions->expects($this->never())->method('captureSnapshot');

		$response = $this->controller(sessionUid: 'bob', userId: 'bob', versions: $versions)->update(7, name: 'x');

		$this->assertSame(expected: Http::STATUS_FORBIDDEN, actual: $response->getStatus());
	}//end testARefusedUpdateRecordsNoSnapshot()

	/**
	 * The service refusing a non-owner's save answers 403, not 400.
	 *
	 * @return void
	 */
	public function testAServiceRefusalOfAnUpdateIsForbidden(): void {
		$response = $this->controller(sessionUid: 'alice', userId: 'alice', serviceRefusal: new ForbiddenException(message: 'Access denied'))
			->update(7, name: 'x');

		$this->assertSame(expected: Http::STATUS_FORBIDDEN, actual: $response->getStatus());
	}//end testAServiceRefusalOfAnUpdateIsForbidden()

	/**
	 * The service refusing a non-owner's delete answers 403, not 400.
	 *
	 * @return void
	 */
	public function testAServiceRefusalOfADeleteIsForbidden(): void {
		$response = $this->controller(sessionUid: 'alice', userId: 'alice', serviceRefusal: new ForbiddenException(message: 'Access denied'))
			->delete(7);

		$this->assertSame(expected: Http::STATUS_FORBIDDEN, actual: $response->getStatus());
	}//end testAServiceRefusalOfADeleteIsForbidden()
}//end class
