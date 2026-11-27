<?php

/**
 * Reading a dashboard the caller cannot see (REQ-SHARE-003): one that
 * exists is refused with 403, one that does not is 404.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\ActorResponses;
use OCA\LaunchPad\Controller\DashboardApiAccess;
use OCA\LaunchPad\Controller\DashboardApiController;
use OCA\LaunchPad\Controller\DashboardApiHistory;
use OCA\LaunchPad\Controller\DashboardApiServices;
use OCA\LaunchPad\Controller\DashboardApiWidgets;
use OCA\LaunchPad\Controller\RequestActor;
use OCA\LaunchPad\Controller\ResponseHelper;
use OCA\LaunchPad\Controller\UserResponses;
use OCA\LaunchPad\Kernel\Contracts\ConditionalServiceInterface;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\AnalyticsService;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\DashboardVersionService;
use OCA\LaunchPad\Service\PermissionService;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * The status of a read that finds nothing for the caller.
 *
 * @covers \OCA\LaunchPad\Controller\DashboardApiController
 * @uses \OCA\LaunchPad\Controller\ActorResponses
 * @uses \OCA\LaunchPad\Controller\DashboardApiAccess
 * @uses \OCA\LaunchPad\Controller\DashboardApiHistory
 * @uses \OCA\LaunchPad\Controller\DashboardApiServices
 * @uses \OCA\LaunchPad\Controller\RequestActor
 * @uses \OCA\LaunchPad\Controller\ResponseHelper
 * @uses \OCA\LaunchPad\Controller\UserResponses
 */
class DashboardApiControllerShowAccessTest extends TestCase {

	/**
	 * The dashboard service double.
	 *
	 * @var DashboardService&MockObject
	 */
	private DashboardService $dashboards;

	/**
	 * The visibility double.
	 *
	 * @var ConditionalServiceInterface&MockObject
	 */
	private ConditionalServiceInterface $visibility;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->dashboards = $this->createMock(originalClassName: DashboardService::class);
		$this->visibility = $this->createMock(originalClassName: ConditionalServiceInterface::class);
		$this->dashboards->method('getDashboardForUser')->willReturn(null);
	}//end setUp()

	/**
	 * The controller with alice signed in.
	 *
	 * @return DashboardApiController The controller.
	 */
	private function controller(): DashboardApiController {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('alice');
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user);

		return new DashboardApiController(
			request: $this->createMock(originalClassName: IRequest::class),
			services: new DashboardApiServices(
				dashboardService: $this->dashboards,
				widgets: new DashboardApiWidgets(visibility: $this->visibility)
			),
			access: new DashboardApiAccess(
				permissionService: $this->createMock(originalClassName: PermissionService::class),
				history: new DashboardApiHistory(
					treeService: $this->createMock(originalClassName: DashboardTreeService::class),
					versionService: $this->createMock(originalClassName: DashboardVersionService::class),
					analyticsService: $this->createMock(originalClassName: AnalyticsService::class),
					logger: $this->createMock(originalClassName: LoggerInterface::class)
				)
			),
			context: new ActorResponses(
				actor: new RequestActor(userSession: $session, actionAuth: $this->createMock(originalClassName: ActionAuthService::class)),
				responses: new UserResponses(userId: 'alice', responseHelper: new ResponseHelper(logger: new NullLogger()))
			)
		);
	}//end controller()

	/**
	 * Someone else's dashboard, not shared with alice, is refused with 403
	 * and the refusal does not name it.
	 *
	 * @return void
	 */
	public function testAnExistingDashboardNotSharedIsForbidden(): void {
		$this->dashboards->method('dashboardExists')->with(7)->willReturn(true);

		$response = $this->controller()->show(id: 7);

		$this->assertSame(expected: 403, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'Access denied'], actual: $response->getData());
	}//end testAnExistingDashboardNotSharedIsForbidden()

	/**
	 * A dashboard id that matches nothing stays 404.
	 *
	 * @return void
	 */
	public function testAnUnknownDashboardIsNotFound(): void {
		$this->dashboards->method('dashboardExists')->with(7)->willReturn(false);

		$response = $this->controller()->show(id: 7);

		$this->assertSame(expected: 404, actual: $response->getStatus());
	}//end testAnUnknownDashboardIsNotFound()
}//end class
