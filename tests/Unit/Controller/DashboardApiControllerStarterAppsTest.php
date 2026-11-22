<?php

/**
 * A first dashboard with the starter widgets is created with the user's app
 * menu, which picks its app tiles (app-tile-widget REQ-APPTILE-003).
 *
 * @category Test
 * @package  Unit\Controller
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\DashboardApiServices;
use OCA\LaunchPad\Controller\DashboardApiHistory;
use OCA\LaunchPad\Controller\RequestActor;
use OCA\LaunchPad\Controller\DashboardApiController;
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
use OCA\LaunchPad\Controller\ResponseHelper;
use Psr\Log\NullLogger;
use OCA\LaunchPad\Controller\ActorResponses;
use OCA\LaunchPad\Controller\DashboardApiAccess;
use OCA\LaunchPad\Kernel\Contracts\ServiceDiscoveryServiceInterface;
use ReflectionMethod;
use OCA\LaunchPad\Controller\UserResponses;

/**
 * The starter widgets carry the user's app menu (app-tile-widget REQ-APPTILE-003).
 */
class DashboardApiControllerStarterAppsTest extends TestCase {
	/**
	 * A controller whose access holds the given app menu.
	 *
	 * @param ServiceDiscoveryServiceInterface|null $appMenu The app menu.
	 *
	 * @return DashboardApiController
	 */
	private function controller(?ServiceDiscoveryServiceInterface $appMenu): DashboardApiController {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('pieter');
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user);

		return new DashboardApiController(
			request: $this->createMock(originalClassName: IRequest::class),
			services: new DashboardApiServices(dashboardService: $this->createMock(originalClassName: DashboardService::class)),
			access: new DashboardApiAccess(
				permissionService: $this->createMock(originalClassName: PermissionService::class),
				history: new DashboardApiHistory(
					treeService: $this->createMock(originalClassName: DashboardTreeService::class),
					versionService: $this->createMock(originalClassName: DashboardVersionService::class),
					analyticsService: $this->createMock(originalClassName: AnalyticsService::class),
					logger: $this->createMock(originalClassName: LoggerInterface::class)
				),
				appMenu: $appMenu
			),
			context: new ActorResponses(actor: new RequestActor(
				userSession: $session,
				actionAuth: $this->createMock(originalClassName: ActionAuthService::class)
			), responses: new UserResponses(userId: 'pieter', responseHelper: new ResponseHelper(logger: new NullLogger())))
		);
	}

	/**
	 * The seed options of a create's `widgets` choice.
	 *
	 * @param DashboardApiController $controller The controller.
	 * @param mixed                  $widgets    The choice.
	 *
	 * @return array<string, mixed>
	 */
	private static function seedOptions(DashboardApiController $controller, mixed $widgets): array {
		$method = new ReflectionMethod(objectOrMethod: DashboardApiController::class, method: 'seedOptions');
		return $method->invoke($controller, $widgets);
	}//end seedOptions()

	/**
	 * The starters carry the app ids of the user's app menu; the other choices do not ask for it.
	 *
	 * @return void
	 */
	public function testTheStartersCarryTheAppMenu(): void {
		$appMenu = $this->createMock(originalClassName: ServiceDiscoveryServiceInterface::class);
		$appMenu->expects($this->once())->method('discover')->willReturn([
			['appId' => 'files', 'name' => 'Bestanden', 'href' => '/apps/files/', 'icon' => ''],
			['appId' => 'mail', 'name' => 'Mail', 'href' => '/apps/mail/', 'icon' => ''],
		]);
		$controller = $this->controller(appMenu: $appMenu);

		$this->assertSame(
			expected: ['seedDefaults' => true, 'seedSet' => DashboardService::SEED_SET_STARTERS, 'menuAppIds' => ['files', 'mail']],
			actual: self::seedOptions(controller: $controller, widgets: 'starters')
		);
		$this->assertSame(
			expected: ['seedDefaults' => false, 'seedSet' => DashboardService::SEED_SET_DEFAULT],
			actual: self::seedOptions(controller: $controller, widgets: 'none')
		);
	}//end testTheStartersCarryTheAppMenu()

	/**
	 * Without an app menu the starters carry none, and the starter set keeps the app grid.
	 *
	 * @return void
	 */
	public function testWithoutAnAppMenuTheStartersCarryNone(): void {
		$this->assertSame(expected: [], actual: self::seedOptions(controller: $this->controller(appMenu: null), widgets: 'starters')['menuAppIds']);
	}//end testWithoutAnAppMenuTheStartersCarryNone()
}//end class
