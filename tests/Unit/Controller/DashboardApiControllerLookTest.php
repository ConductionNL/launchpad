<?php

/**
 * DashboardApiController look test
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-each-dashboard-can-have-its-own-look-req-thm-001
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\DashboardApiController;
use OCA\LaunchPad\Controller\DashboardApiHistory;
use OCA\LaunchPad\Controller\DashboardApiServices;
use OCA\LaunchPad\Controller\RequestActor;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Exception\ContrastTooLowException;
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

/**
 * The look on the update route: it needs the layout permission, and a pale accent answers 400 with the ratio.
 *
 * @covers \OCA\LaunchPad\Controller\DashboardApiController
 * @uses   \OCA\LaunchPad\Controller\ResponseHelper
 * @uses   \OCA\LaunchPad\Controller\RespondsWithErrors
 * @uses   \OCA\LaunchPad\Controller\DashboardApiHistory
 * @uses   \OCA\LaunchPad\Controller\DashboardApiServices
 * @uses   \OCA\LaunchPad\Controller\RequestActor
 * @uses   \OCA\LaunchPad\Db\Dashboard
 * @uses   \OCA\LaunchPad\Exception\ContrastTooLowException
 * @uses   \OCA\LaunchPad\Service\DashboardLook
 */
class DashboardApiControllerLookTest extends TestCase {

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
				$this->saved[] = [$dashboardId, $userId, $data];
				if (($data['themeAccent'] ?? '') === '#f5f5dc') {
					throw new ContrastTooLowException(ratio: 1.1);
				}

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

	public function testALookNeedsTheLayoutPermissionNotJustMetadata(): void {
		$response = $this->controller(sessionUid: 'alice', userId: 'alice')->update(id: 7, themeAccent: '#1f5c3a');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([['canEditDashboard', 'alice', 7]], $this->asked);
		$this->assertSame(['themeAccent' => '#1f5c3a'], $this->saved[0][2], 'only the field sent is patched');
	}

	public function testARenameStaysAMetadataEdit(): void {
		$this->controller(sessionUid: 'alice', userId: 'alice')->update(id: 7, name: 'Team Groen');

		$this->assertSame([['canEditDashboardMetadata', 'alice', 7]], $this->asked);
	}

	public function testSomeoneWithoutTheLayoutPermissionCannotChangeTheLook(): void {
		$response = $this->controller(sessionUid: 'bob', userId: 'bob')->update(id: 7, themeBackgroundColor: '#12301f');

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame([], $this->saved);
	}

	public function testAPaleAccentAnswers400WithTheRatio(): void {
		$response = $this->controller(sessionUid: 'alice', userId: 'alice')->update(id: 7, themeAccent: '#f5f5dc');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(
			['status' => 'error', 'error' => 'contrast_too_low', 'ratio' => 1.1, 'message' => 'Buttons in this colour are hard to see (contrast 1.1 to 1, needs 3 to 1)'],
			$response->getData()
		);
	}

	public function testAnEmptyValueIsSentOnToClearTheLook(): void {
		$this->controller(sessionUid: 'alice', userId: 'alice')->update(id: 7, themeBackgroundResource: '');

		$this->assertSame(['themeBackgroundResource' => ''], $this->saved[0][2]);
		$this->assertSame([['canEditDashboard', 'alice', 7]], $this->asked);
	}
}
