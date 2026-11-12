<?php

/**
 * UserDeletedListenerCascadeTest
 *
 * Pins the failure handling and the event hand-off of the user-deletion
 * cascade that UserDeletedListenerTest leaves open: a failing role cleanup
 * or a failing dashboard does not stop the rest, a deleted dashboard is
 * announced after its commit, and the admin-pool walk skips the deleted
 * user, vanished accounts and groups that no longer resolve.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Listener
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Listener;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardShare;
use OCA\LaunchPad\Db\DashboardShareMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use OCA\LaunchPad\Listener\UserDeletedListener;
use OCA\LaunchPad\Service\DashboardShareService;
use OCA\LaunchPad\Service\RoleService;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\User\Events\UserDeletedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Failure handling and event hand-off of UserDeletedListener.
 *
 * @covers \OCA\LaunchPad\Listener\UserDeletedListener
 * @uses   \OCA\LaunchPad\Db\Dashboard
 * @uses   \OCA\LaunchPad\Db\DashboardShare
 * @uses   \OCA\LaunchPad\Event\DashboardDeletedEvent
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class UserDeletedListenerCascadeTest extends TestCase {

	/** @var DashboardShareMapper&MockObject */
	private $shareMapper;
	/** @var DashboardMapper&MockObject */
	private $dashboardMapper;
	/** @var WidgetPlacementMapper&MockObject */
	private $placementMapper;
	/** @var DashboardShareService&MockObject */
	private $shareService;
	/** @var IGroupManager&MockObject */
	private $groupManager;
	/** @var IUserManager&MockObject */
	private $userManager;
	/** @var IDBConnection&MockObject */
	private $db;
	/** @var LoggerInterface&MockObject */
	private $logger;
	/** @var RoleService&MockObject */
	private $roleService;
	/** @var IEventDispatcher&MockObject */
	private $dispatcher;

	/**
	 * Ordered record of transaction, mapper and dispatch calls.
	 *
	 * @var list<string>
	 */
	private array $trail = [];

	/**
	 * Fresh mocks per test; transaction calls are recorded in the trail.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->shareMapper = $this->createMock(DashboardShareMapper::class);
		$this->dashboardMapper = $this->createMock(DashboardMapper::class);
		$this->placementMapper = $this->createMock(WidgetPlacementMapper::class);
		$this->shareService = $this->createMock(DashboardShareService::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->roleService = $this->createMock(RoleService::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);

		foreach (['beginTransaction', 'commit', 'rollBack'] as $method) {
			$this->db->method($method)->willReturnCallback(function () use ($method): bool {
				$this->trail[] = $method;
				return true;
			});
		}
	}//end setUp()

	/**
	 * Build the listener, with or without an event dispatcher.
	 *
	 * @param bool $withDispatcher Whether to inject the dispatcher.
	 *
	 * @return UserDeletedListener
	 */
	private function listener(bool $withDispatcher = true): UserDeletedListener {
		return new UserDeletedListener(
			shareMapper: $this->shareMapper,
			dashboardMapper: $this->dashboardMapper,
			placementMapper: $this->placementMapper,
			shareService: $this->shareService,
			groupManager: $this->groupManager,
			userManager: $this->userManager,
			db: $this->db,
			logger: $this->logger,
			roleService: $this->roleService,
			eventDispatcher: $withDispatcher === true ? $this->dispatcher : null,
		);
	}//end listener()

	/**
	 * A real dashboard entity.
	 *
	 * @param int $id The id.
	 * @param string|null $uuid The uuid.
	 * @param string|null $type The type.
	 *
	 * @return Dashboard
	 */
	private function dashboard(int $id, ?string $uuid, ?string $type = Dashboard::TYPE_USER): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId($id);
		$dashboard->setUuid($uuid);
		$dashboard->setType($type);
		$dashboard->setUserId('alice');
		$dashboard->setName('Board ' . $id);
		return $dashboard;
	}//end dashboard()

	/**
	 * A real full-permission share entity.
	 *
	 * @param string $type The share type.
	 * @param string $with The recipient.
	 *
	 * @return DashboardShare
	 */
	private function share(string $type, string $with): DashboardShare {
		$share = new DashboardShare();
		$share->setShareType($type);
		$share->setShareWith($with);
		$share->setPermissionLevel(Dashboard::PERMISSION_FULL);
		return $share;
	}//end share()

	/**
	 * A user mock.
	 *
	 * @param string $uid The uid.
	 *
	 * @return IUser&MockObject
	 */
	private function user(string $uid): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		return $user;
	}//end user()

	/**
	 * The event for alice's deletion.
	 *
	 * @return UserDeletedEvent
	 */
	private function aliceDeleted(): UserDeletedEvent {
		return new UserDeletedEvent($this->user('alice'));
	}//end aliceDeleted()

	/**
	 * A failing role cleanup is logged, and recipient cleanup and the
	 * owned-dashboard walk still run.
	 *
	 * @return void
	 */
	public function testAFailingRoleCleanupIsLoggedAndTheCascadeContinues(): void {
		$this->roleService->method('deleteByUserId')
			->with('alice')
			->willThrowException(new RuntimeException('roles down'));
		$this->logger->expects($this->once())
			->method('error')
			->with(
				'launchpad UserDeletedListener: failed to cascade role assignment cleanup for user alice: roles down',
				['app' => 'launchpad']
			);
		$this->shareMapper->expects($this->once())->method('deleteByRecipientUser')->with('alice');
		$this->dashboardMapper->expects($this->once())->method('findByUserId')->with('alice')->willReturn([]);

		$this->listener()->handle($this->aliceDeleted());
	}//end testAFailingRoleCleanupIsLoggedAndTheCascadeContinues()

	/**
	 * With no admin pool the dashboard is deleted and its DashboardDeletedEvent
	 * is dispatched after the commit, carrying the dashboard's identity.
	 *
	 * @return void
	 */
	public function testADeletedDashboardIsAnnouncedAfterTheCommit(): void {
		$this->dashboardMapper->method('findByUserId')->willReturn(
			[$this->dashboard(id: 42, uuid: 'uuid-42', type: Dashboard::TYPE_GROUP_SHARED)]
		);
		$this->shareMapper->method('findByDashboardAndLevel')->with(42, Dashboard::PERMISSION_FULL)->willReturn([]);
		$this->placementMapper->expects($this->once())->method('deleteByDashboardId')->with(42);
		$this->shareMapper->expects($this->once())->method('deleteByDashboardId')->with(42);
		$this->dashboardMapper->expects($this->once())->method('delete');

		$dispatched = [];
		$this->dispatcher->expects($this->once())
			->method('dispatchTyped')
			->willReturnCallback(function (DashboardDeletedEvent $event) use (&$dispatched): void {
				$this->trail[] = 'dispatch';
				$dispatched[] = $event;
			});

		$this->listener()->handle($this->aliceDeleted());

		$this->assertSame(['beginTransaction', 'commit', 'dispatch'], $this->trail);
		$this->assertSame('uuid-42', $dispatched[0]->getDashboardUuid());
		$this->assertSame('alice', $dispatched[0]->getOwnerUserId());
		$this->assertSame(Dashboard::TYPE_GROUP_SHARED, $dispatched[0]->getType());
		$this->assertSame(42, $dispatched[0]->getDashboardId());
	}//end testADeletedDashboardIsAnnouncedAfterTheCommit()

	/**
	 * A dashboard without a type is announced as a user dashboard.
	 *
	 * @return void
	 */
	public function testAnUntypedDashboardIsAnnouncedAsAUserDashboard(): void {
		$this->dashboardMapper->method('findByUserId')->willReturn([$this->dashboard(id: 5, uuid: 'u5', type: null)]);
		$this->shareMapper->method('findByDashboardAndLevel')->willReturn([]);

		$types = [];
		$this->dispatcher->method('dispatchTyped')
			->willReturnCallback(static function (DashboardDeletedEvent $event) use (&$types): void {
				$types[] = $event->getType();
			});

		$this->listener()->handle($this->aliceDeleted());

		$this->assertSame([Dashboard::TYPE_USER], $types);
	}//end testAnUntypedDashboardIsAnnouncedAsAUserDashboard()

	/**
	 * A dashboard without a uuid is deleted but not announced.
	 *
	 * @return void
	 */
	public function testADashboardWithoutUuidIsNotAnnounced(): void {
		$this->dashboardMapper->method('findByUserId')->willReturn([$this->dashboard(id: 6, uuid: null)]);
		$this->shareMapper->method('findByDashboardAndLevel')->willReturn([]);
		$this->dashboardMapper->expects($this->once())->method('delete');
		$this->dispatcher->expects($this->never())->method('dispatchTyped');

		$this->listener()->handle($this->aliceDeleted());

		$this->assertSame(['beginTransaction', 'commit'], $this->trail);
	}//end testADashboardWithoutUuidIsNotAnnounced()

	/**
	 * Without a dispatcher the delete path still commits and does not fail.
	 *
	 * @return void
	 */
	public function testWithoutADispatcherTheDeleteStillCommits(): void {
		$this->dashboardMapper->method('findByUserId')->willReturn([$this->dashboard(id: 7, uuid: 'u7')]);
		$this->shareMapper->method('findByDashboardAndLevel')->willReturn([]);
		$this->dashboardMapper->expects($this->once())->method('delete');
		$this->logger->expects($this->never())->method('error');

		$this->listener(withDispatcher: false)->handle($this->aliceDeleted());

		$this->assertSame(['beginTransaction', 'commit'], $this->trail);
	}//end testWithoutADispatcherTheDeleteStillCommits()

	/**
	 * A failing dashboard is rolled back and logged; the next one is still handled.
	 *
	 * @return void
	 */
	public function testAFailingDashboardIsRolledBackAndTheNextIsStillHandled(): void {
		$this->dashboardMapper->method('findByUserId')->willReturn(
			[$this->dashboard(id: 1, uuid: 'u1'), $this->dashboard(id: 2, uuid: 'u2')]
		);
		$this->shareMapper->method('findByDashboardAndLevel')->willReturnCallback(
			static function (int $dashboardId): array {
				if ($dashboardId === 1) {
					throw new RuntimeException('shares unreadable');
				}

				return [];
			}
		);
		$this->logger->expects($this->once())
			->method('error')
			->with(
				'launchpad UserDeletedListener: failed to handle dashboard 1: shares unreadable',
				['app' => 'launchpad']
			);
		$this->dashboardMapper->expects($this->once())->method('delete');

		$this->listener(withDispatcher: false)->handle($this->aliceDeleted());

		$this->assertSame(['beginTransaction', 'rollBack', 'beginTransaction', 'commit'], $this->trail);
	}//end testAFailingDashboardIsRolledBackAndTheNextIsStillHandled()

	/**
	 * The pool skips the deleted user and vanished accounts among user shares,
	 * skips a group that no longer resolves, and takes the first existing
	 * member of the next group; ownership moves and the new owner is notified
	 * after the commit.
	 *
	 * @return void
	 */
	public function testThePoolSkipsDeletedVanishedAndUnresolvableCandidates(): void {
		$this->dashboardMapper->method('findByUserId')->willReturn([$this->dashboard(id: 9, uuid: 'u9')]);
		$this->shareMapper->method('findByDashboardAndLevel')->willReturn([
			$this->share(DashboardShare::SHARE_TYPE_USER, 'alice'),
			$this->share(DashboardShare::SHARE_TYPE_USER, 'ghost'),
			$this->share(DashboardShare::SHARE_TYPE_GROUP, 'zeta'),
			$this->share(DashboardShare::SHARE_TYPE_GROUP, 'alpha'),
		]);
		$zeta = $this->createMock(IGroup::class);
		$zeta->method('getUsers')->willReturn([$this->user('zed'), $this->user('alice'), $this->user('bob')]);
		$this->groupManager->method('get')->willReturnMap([['alpha', null], ['zeta', $zeta]]);
		$existing = ['bob' => $this->user('bob'), 'zed' => $this->user('zed')];
		$this->userManager->method('get')->willReturnCallback(
			static fn (string $uid): ?IUser => $existing[$uid] ?? null
		);

		$this->shareService->expects($this->once())
			->method('transferOwnership')
			->with(9, 'bob')
			->willReturnCallback(function (): void {
				$this->trail[] = 'transfer';
			});
		$this->shareService->expects($this->once())
			->method('notifyOwnershipTransferred')
			->with('bob', 9, 'Board 9')
			->willReturnCallback(function (): void {
				$this->trail[] = 'notify';
			});
		$this->dashboardMapper->expects($this->never())->method('delete');
		$this->dispatcher->expects($this->never())->method('dispatchTyped');

		$this->listener()->handle($this->aliceDeleted());

		$this->assertSame(['beginTransaction', 'transfer', 'commit', 'notify'], $this->trail);
	}//end testThePoolSkipsDeletedVanishedAndUnresolvableCandidates()
}//end class
