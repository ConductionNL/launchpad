<?php

/**
 * DashboardApiController view-event Contract Test
 *
 * Wire-contract coverage for `dashboardApi#viewEvent`
 * (POST /api/dashboards/{uuid}/view-event) — REQ-ANLT-002.
 *
 * The endpoint is `#[NoAdminRequired]`, takes a UUID straight from the URL
 * and writes an analytics counter, so the H4 guard order is the contract:
 * the dashboard is resolved and the caller's view permission asserted
 * BEFORE any counter is touched. Both refusal paths therefore assert that
 * `recordViewEvent()` was never called — a status assertion alone would
 * still pass if the write happened first and the response were rewritten
 * afterwards.
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
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\AnalyticsService;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\DashboardVersionService;
use OCA\LaunchPad\Service\PermissionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Contract tests for the dashboard view-event recorder.
 */
class DashboardApiControllerViewEventTest extends TestCase {

	/**
	 * HTTP request mock.
	 *
	 * @var IRequest&MockObject
	 */
	private $request;

	/**
	 * Dashboard service mock.
	 *
	 * @var DashboardService&MockObject
	 */
	private $dashboardService;

	/**
	 * Permission service mock.
	 *
	 * @var PermissionService&MockObject
	 */
	private $permissionService;

	/**
	 * Analytics service mock.
	 *
	 * @var AnalyticsService&MockObject
	 */
	private $analyticsService;

	/**
	 * User session mock.
	 *
	 * @var IUserSession&MockObject
	 */
	private $userSession;

	/**
	 * Action authorization mock.
	 *
	 * @var ActionAuthService&MockObject
	 */
	private $actionAuth;

	/**
	 * Set up shared mocks.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->dashboardService = $this->createMock(DashboardService::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->analyticsService = $this->createMock(AnalyticsService::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->actionAuth = $this->createMock(ActionAuthService::class);

	}//end setUp()

	/**
	 * Build the controller for the supplied user (NULL = anonymous).
	 *
	 * @param string|null $userId The acting user ID.
	 *
	 * @return DashboardApiController
	 */
	private function makeController(?string $userId): DashboardApiController {
		$user = null;
		if ($userId !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($userId);
		}

		$this->userSession->method('getUser')->willReturn($user);

		return new DashboardApiController(
			request: $this->request,
			dashboardService: $this->dashboardService,
			permissionService: $this->permissionService,
			treeService: $this->createMock(DashboardTreeService::class),
			versionService: $this->createMock(DashboardVersionService::class),
			analyticsService: $this->analyticsService,
			logger: $this->createMock(LoggerInterface::class),
			userSession: $this->userSession,
			actionAuth: $this->actionAuth,
			userId: $userId,
		);

	}//end makeController()

	/**
	 * Build a persisted-looking dashboard fixture.
	 *
	 * @param integer $id The row id.
	 * @param string $uuid The dashboard UUID.
	 *
	 * @return Dashboard
	 */
	private function makeDashboard(int $id, string $uuid): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId($id);
		$dashboard->setUuid($uuid);

		return $dashboard;
	}//end makeDashboard()

	/**
	 * An anonymous caller MUST get 401 and record nothing.
	 *
	 * @return void
	 */
	public function testViewEventRejectsAnonymousWith401(): void {
		$this->analyticsService->expects($this->never())->method('recordViewEvent');

		$controller = $this->makeController(null);
		$response = $controller->viewEvent(uuid: 'uuid-a');

		$this->assertSame(
			expected: Http::STATUS_UNAUTHORIZED,
			actual: $response->getStatus()
		);

	}//end testViewEventRejectsAnonymousWith401()

	/**
	 * An unknown UUID is a 404 and records nothing.
	 *
	 * @return void
	 */
	public function testViewEventReturns404ForUnknownDashboard(): void {
		$this->dashboardService->method('findByUuid')
			->willThrowException(new DoesNotExistException(msg: 'nope'));

		$this->analyticsService->expects($this->never())->method('recordViewEvent');

		$controller = $this->makeController('alice');
		$response = $controller->viewEvent(uuid: 'uuid-missing');

		$this->assertSame(
			expected: Http::STATUS_NOT_FOUND,
			actual: $response->getStatus()
		);
		$this->assertSame(expected: 'not_found', actual: $response->getData()['error']);

	}//end testViewEventReturns404ForUnknownDashboard()

	/**
	 * H4: a caller who cannot view the dashboard is refused with 403 and
	 * MUST NOT increment anybody's counter.
	 *
	 * @return void
	 */
	public function testViewEventRefusesCallerWithoutViewPermission(): void {
		$this->dashboardService->method('findByUuid')
			->with(uuid: 'uuid-secret')
			->willReturn($this->makeDashboard(id: 42, uuid: 'uuid-secret'));

		$this->permissionService->expects($this->once())
			->method('canViewDashboard')
			->with(userId: 'mallory', dashboardId: 42)
			->willReturn(false);

		$this->analyticsService->expects($this->never())->method('recordViewEvent');

		$controller = $this->makeController('mallory');
		$response = $controller->viewEvent(uuid: 'uuid-secret');

		$this->assertSame(
			expected: Http::STATUS_FORBIDDEN,
			actual: $response->getStatus()
		);

	}//end testViewEventRefusesCallerWithoutViewPermission()

	/**
	 * REQ-ANLT-002 happy path: the counter is incremented for the acting
	 * user and the response is an empty 204.
	 *
	 * @return void
	 */
	public function testViewEventRecordsAndReturns204(): void {
		$this->dashboardService->method('findByUuid')
			->willReturn($this->makeDashboard(id: 7, uuid: 'uuid-a'));

		$this->permissionService->method('canViewDashboard')->willReturn(true);

		$this->analyticsService->expects($this->once())
			->method('recordViewEvent')
			->with(dashboardUuid: 'uuid-a', userId: 'alice');

		$controller = $this->makeController('alice');
		$response = $controller->viewEvent(uuid: 'uuid-a');

		$this->assertSame(
			expected: Http::STATUS_NO_CONTENT,
			actual: $response->getStatus()
		);
		$this->assertSame(expected: [], actual: $response->getData());

	}//end testViewEventRecordsAndReturns204()

	/**
	 * A dashboard that disappears between the lookup and the write (the
	 * service raises DoesNotExistException from `recordViewEvent`) maps to
	 * the same 404 envelope rather than a 500.
	 *
	 * @return void
	 */
	public function testViewEventMapsRecorderDoesNotExistTo404(): void {
		$this->dashboardService->method('findByUuid')
			->willReturn($this->makeDashboard(id: 7, uuid: 'uuid-a'));

		$this->permissionService->method('canViewDashboard')->willReturn(true);

		$this->analyticsService->method('recordViewEvent')
			->willThrowException(new DoesNotExistException(msg: 'vanished'));

		$controller = $this->makeController('alice');
		$response = $controller->viewEvent(uuid: 'uuid-a');

		$this->assertSame(
			expected: Http::STATUS_NOT_FOUND,
			actual: $response->getStatus()
		);

	}//end testViewEventMapsRecorderDoesNotExistTo404()

}//end class
