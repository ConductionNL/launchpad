<?php

/**
 * DashboardLockApiControllerGoldenTest
 *
 * Golden record of every dashboard lock endpoint: the sign-in and action
 * guards, the view check of the lock query, and the answer for each lock
 * outcome (granted, not found, forbidden, conflict, unknown dashboard).
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

use OCA\LaunchPad\Controller\DashboardLockApiController;
use OCA\LaunchPad\Controller\LockApiCaller;
use OCA\LaunchPad\Controller\LockSources;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardLock;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Exception\LockConflictException;
use OCA\LaunchPad\Exception\LockForbiddenException;
use OCA\LaunchPad\Exception\LockNotFoundException;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\DashboardLockService;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\RequestGuard;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * The lock endpoints, case by case.
 */
class DashboardLockApiControllerGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/dashboard-lock-api.json';

	/**
	 * Every endpoint × case answers as recorded.
	 *
	 * @return void
	 */
	public function testEndpointsMatchTheRecording(): void {
		$cases = ['anonymous', 'action denied', 'granted', 'not found', 'forbidden', 'conflict', 'unknown dashboard', 'cannot view', 'no lock'];
		$actual = [];
		foreach (['acquire', 'heartbeat', 'release', 'get', 'forceRelease'] as $endpoint) {
			foreach ($cases as $case) {
				try {
					$response = $this->controller(case: $case)->$endpoint('d-1');
					$actual[$endpoint.' / '.$case] = [$response->getStatus(), $response->getData()];
				} catch (\Throwable $e) {
					$actual[$endpoint.' / '.$case] = ['thrown' => get_class($e), 'message' => $e->getMessage()];
				}
			}
		}

		$actual = json_decode((string) json_encode($actual), true);
		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
		}

		$this->assertSame(json_decode((string) file_get_contents(self::FIXTURE), true), $actual);
	}//end testEndpointsMatchTheRecording()

	/**
	 * The controller for one case.
	 *
	 * @param string $case The situation.
	 *
	 * @return DashboardLockApiController
	 */
	private function controller(string $case): DashboardLockApiController {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn(($case === 'anonymous') ? null : $user);

		$auth = $this->createMock(ActionAuthService::class);
		if ($case === 'action denied') {
			$auth->method('requireAction')->willThrowException(new OCSForbiddenException('denied'));
		}

		$lock = new DashboardLock();
		$lock->setDashboardUuid('d-1');
		$lock->setUserId('alice');
		$lock->setDisplayName('Alice');

		$failure = match ($case) {
			'not found' => new LockNotFoundException('No active lock'),
			'forbidden' => new LockForbiddenException('Not yours'),
			'conflict' => new LockConflictException('Locked by Bob', $lock),
			'unknown dashboard' => new DoesNotExistException('gone'),
			default => null,
		};
		$service = $this->createMock(DashboardLockService::class);
		foreach (['acquireLock', 'heartbeat', 'releaseLock', 'forceRelease'] as $method) {
			$service->method($method)->willReturnCallback(
				static function () use ($failure, $lock, $method) {
					if ($failure !== null) {
						throw $failure;
					}
					return in_array($method, ['acquireLock', 'heartbeat'], true) ? $lock : null;
				}
			);
		}
		$service->method('getLockState')->willReturn(($case === 'no lock') ? null : $lock);

		$dashboards = $this->createMock(DashboardMapper::class);
		$dashboards->method('findByUuid')->willReturnCallback(
			static function () use ($case): Dashboard {
				if ($case === 'unknown dashboard') {
					throw new DoesNotExistException('gone');
				}
				$dashboard = new Dashboard();
				$dashboard->setId(3);
				return $dashboard;
			}
		);
		$permissions = $this->createMock(PermissionService::class);
		$permissions->method('canViewDashboard')->willReturn($case !== 'cannot view');

		return new DashboardLockApiController(
			request: $this->createMock(IRequest::class),
			caller: new LockApiCaller(
				sources: new LockSources(lockService: $service, dashboardMapper: $dashboards),
				guard: new RequestGuard(userSession: $session, groupManager: $this->createMock(IGroupManager::class), actionAuth: $auth)
			),
			permissionService: $permissions,
			userId: ($case === 'anonymous') ? null : 'alice'
		);
	}//end controller()
}//end class
