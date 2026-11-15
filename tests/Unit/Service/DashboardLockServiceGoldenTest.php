<?php

/**
 * DashboardLockServiceGoldenTest
 *
 * Records the calls and outcome of acquiring and extending a dashboard
 * lock on every allowed and refused path: missing dashboard, no view
 * access, fresh, re-entrant, held by someone else, missing lock, foreign
 * lock. The access check must come before any lock row is touched.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardLock;
use OCA\LaunchPad\Db\DashboardLockMapper;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Service\DashboardLockService;
use OCA\LaunchPad\Service\LockDirectory;
use OCA\LaunchPad\Service\LockStore;
use OCA\LaunchPad\Service\PermissionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IGroupManager;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DashboardLockServiceGoldenTest extends TestCase {

	/** @var list<string> */
	private array $log = [];

	/** The holder of the current lock, or null for none. */
	private ?string $holder = null;

	private DashboardLockService $service;

	protected function setUp(): void {
		$dashboards = $this->createMock(DashboardMapper::class);
		$dashboards->method('findByUuid')->willReturnCallback(function (string $uuid): Dashboard {
			$this->log[] = 'findDashboard ' . $uuid;
			if ($uuid === 'gone') {
				throw new DoesNotExistException('gone');
			}

			$dashboard = new Dashboard();
			$dashboard->setId(($uuid === 'd1') ? 1 : 2);
			return $dashboard;
		});

		$permissions = $this->createMock(PermissionService::class);
		$permissions->method('canViewDashboard')->willReturnCallback(function (string $userId, int $dashboardId): bool {
			$this->log[] = 'canView ' . $userId . ' ' . $dashboardId;
			return $userId !== 'mallory';
		});

		$locks = $this->createMock(DashboardLockMapper::class);
		$locks->method('deleteExpiredForDashboard')->willReturnCallback(function (string $dashboardUuid): int {
			$this->log[] = 'deleteExpired ' . $dashboardUuid;
			return 0;
		});
		$locks->method('findByDashboardUuid')->willReturnCallback(function (string $dashboardUuid): DashboardLock {
			$this->log[] = 'findLock ' . $dashboardUuid;
			if ($this->holder === null) {
				throw new DoesNotExistException('none');
			}

			return $this->lock();
		});
		$locks->method('findActive')->willReturnCallback(function (string $dashboardUuid): ?DashboardLock {
			$this->log[] = 'findActive ' . $dashboardUuid;
			return ($this->holder === null) ? null : $this->lock();
		});
		foreach (['insert', 'update'] as $write) {
			$locks->method($write)->willReturnCallback(function (DashboardLock $lock) use ($write): DashboardLock {
				$this->log[] = $write . ' ' . $lock->getDashboardUuid() . ' ' . $lock->getUserId() . ' ' . $lock->getDisplayName()
					. ' ' . (($lock->getUpdatedAt() !== null) ? 'stamped' : 'unstamped');
				return $lock;
			});
		}

		$users = $this->createMock(IUserManager::class);
		$users->method('get')->willReturn(null);

		$this->service = new DashboardLockService(
			locks: new LockStore(lockMapper: $locks, logger: $this->createMock(LoggerInterface::class)),
			dashboardMapper: $dashboards,
			directory: new LockDirectory(userManager: $users, groupManager: $this->createMock(IGroupManager::class)),
			permissionService: $permissions
		);
	}

	private function lock(): DashboardLock {
		$lock = new DashboardLock();
		$lock->setDashboardUuid('d1');
		$lock->setUserId((string)$this->holder);
		$lock->setDisplayName('Holder');
		return $lock;
	}

	/**
	 * @return array{0: string, 1: list<string>}
	 */
	private function record(callable $call): array {
		$this->log = [];
		try {
			$call();
			$outcome = 'ok';
		} catch (\Throwable $e) {
			$outcome = (new \ReflectionClass($e))->getShortName() . ': ' . $e->getMessage();
		}

		return [$outcome, $this->log];
	}

	public function testAcquire(): void {
		$acquire = fn (string $uuid, string $user) => $this->record(fn () => $this->service->acquireLock(dashboardUuid: $uuid, userId: $user));

		$this->assertSame(['DoesNotExistException: gone', ['findDashboard gone']], $acquire('gone', 'alice'));
		$this->assertSame(
			['LockForbiddenException: You do not have access to this dashboard', ['findDashboard d1', 'canView mallory 1']],
			$acquire('d1', 'mallory')
		);
		$this->assertSame(
			['ok', ['findDashboard d1', 'canView alice 1', 'deleteExpired d1', 'findLock d1', 'insert d1 alice alice stamped']],
			$acquire('d1', 'alice')
		);

		$this->holder = 'alice';
		$this->assertSame(
			['ok', ['findDashboard d1', 'canView alice 1', 'deleteExpired d1', 'findLock d1', 'update d1 alice Holder stamped']],
			$acquire('d1', 'alice')
		);
		$this->assertSame(
			['LockConflictException: Lock held by another user', ['findDashboard d1', 'canView bob 1', 'deleteExpired d1', 'findLock d1']],
			$acquire('d1', 'bob')
		);
	}

	public function testHeartbeat(): void {
		$beat = fn (string $uuid, string $user) => $this->record(fn () => $this->service->heartbeat(dashboardUuid: $uuid, userId: $user));

		$this->assertSame(['LockNotFoundException: Lock not found; call acquire first', ['findDashboard gone']], $beat('gone', 'alice'));
		$this->assertSame(
			['LockForbiddenException: You do not have access to this dashboard', ['findDashboard d2', 'canView mallory 2']],
			$beat('d2', 'mallory')
		);
		$this->assertSame(
			['LockNotFoundException: Lock not found; call acquire first', ['findDashboard d1', 'canView alice 1', 'findActive d1']],
			$beat('d1', 'alice')
		);

		$this->holder = 'alice';
		$this->assertSame(
			['LockForbiddenException: Only the lock owner can extend the lease', ['findDashboard d1', 'canView bob 1', 'findActive d1']],
			$beat('d1', 'bob')
		);
		$this->assertSame(
			['ok', ['findDashboard d1', 'canView alice 1', 'findActive d1', 'update d1 alice Holder stamped']],
			$beat('d1', 'alice')
		);
	}
}
