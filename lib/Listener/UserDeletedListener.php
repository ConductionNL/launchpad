<?php

/**
 * UserDeletedListener
 *
 * Listens to OCP\User\Events\UserDeletedEvent and applies the admin-retention
 * cascade: cleans up shares granted to the deleted user, then for every
 * dashboard the user owned either transfers ownership to the first admin-pool
 * member or deletes the dashboard when the pool is empty. REQ-SHARE-012.
 *
 * @category  Listener
 * @package   OCA\LaunchPad\Listener
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Listener;

use DateTimeImmutable;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardShare;
use OCA\LaunchPad\Db\DashboardShareMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use OCA\LaunchPad\Service\DashboardShareService;
use OCA\LaunchPad\Service\RoleService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Handles user deletion: recipient cleanup + ownership transfer / cascade.
 *
 * @implements IEventListener<UserDeletedEvent>
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) The retention cascade
 *                                                  legitimately spans share,
 *                                                  dashboard, placement,
 *                                                  group and user services
 *                                                  in one orchestrating
 *                                                  listener.
 * @spec                                           openspec/specs/dashboard-cascade-events/spec.md
 */
class UserDeletedListener implements IEventListener {
	/**
	 * Constructor
	 *
	 * @param DashboardShareMapper $shareMapper The share mapper.
	 * @param DashboardMapper $dashboardMapper The dashboard mapper.
	 * @param WidgetPlacementMapper $placementMapper The placement mapper.
	 * @param DashboardShareService $shareService The share service.
	 * @param IGroupManager $groupManager The group manager.
	 * @param IUserManager $userManager The user manager.
	 * @param IDBConnection $db The DB connection.
	 * @param LoggerInterface $logger PSR-3 logger (PHP_SAPI-safe;
	 *                                replaces deprecated
	 *                                `\OC::$server->getLogger()`).
	 * @param RoleService $roleService Role-assignment cascade
	 *                                 (REQ-ROLE-010).
	 * @param IEventDispatcher|null $eventDispatcher Event dispatcher for
	 *                                               DashboardDeletedEvent
	 *                                               (SB1 fix, REQ-CSC-001).
	 *                                               Nullable for backwards-
	 *                                               compat.
	 */
	public function __construct(
		private readonly DashboardShareMapper $shareMapper,
		private readonly DashboardMapper $dashboardMapper,
		private readonly WidgetPlacementMapper $placementMapper,
		private readonly DashboardShareService $shareService,
		private readonly IGroupManager $groupManager,
		private readonly IUserManager $userManager,
		private readonly IDBConnection $db,
		private readonly LoggerInterface $logger,
		private readonly RoleService $roleService,
		private readonly ?IEventDispatcher $eventDispatcher = null,
	) {
	}//end __construct()

	/**
	 * Handle the UserDeletedEvent.
	 *
	 * Step A: delete all user-type shares where share_with = deleted user.
	 * Step B: for each owned dashboard, compute the admin pool and either
	 *         transfer ownership or delete the dashboard.
	 *
	 * @param Event $event The event.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboard-cascade-events/spec.md
	 */
	public function handle(Event $event): void {
		if (($event instanceof UserDeletedEvent) === false) {
			return;
		}

		$userId = $event->getUser()->getUID();

		// REQ-ROLE-010: cascade role-assignment cleanup. Best-effort —
		// logged but never aborts the rest of the pipeline.
		try {
			$this->roleService->deleteByUserId(userId: $userId);
		} catch (Throwable $t) {
			$this->logger->error(
				message: sprintf(
					'launchpad UserDeletedListener: failed to cascade role assignment cleanup for user %s: %s',
					$userId,
					$t->getMessage()
				),
				context: ['app' => 'launchpad']
			);
		}

		// Step A: remove shares granted TO the deleted user.
		$this->shareMapper->deleteByRecipientUser(userId: $userId);

		// Step B: handle owned dashboards.
		$ownedDashboards = $this->dashboardMapper->findByUserId(
			userId: $userId
		);

		foreach ($ownedDashboards as $dashboard) {
			$this->handleOwnedDashboard(
				dashboard: $dashboard,
				deletedUserId: $userId
			);
		}
	}//end handle()

	/**
	 * Handle a single dashboard owned by the deleted user.
	 *
	 * @param Dashboard $dashboard The dashboard.
	 * @param string $deletedUserId The deleted user's ID.
	 *
	 * @return void
	 */
	private function handleOwnedDashboard(
		Dashboard $dashboard,
		string $deletedUserId,
	): void {
		$dashboardId = (int)$dashboard->getId();

		$this->db->beginTransaction();
		try {
			$newOwner = $this->pickNewOwner(
				dashboardId: $dashboardId,
				deletedUserId: $deletedUserId
			);

			if ($newOwner !== null) {
				// Transfer ownership.
				$this->shareService->transferOwnership(
					dashboardId: $dashboardId,
					newUserId: $newOwner
				);

				$this->db->commit();

				// Notify outside the transaction.
				$this->shareService->notifyOwnershipTransferred(
					newOwnerId: $newOwner,
					dashboardId: $dashboardId,
					dashboardName: (string)$dashboard->getName()
				);
			}

			if ($newOwner === null) {
				// Admin pool empty — delete dashboard, placements, and shares.
				$this->placementMapper->deleteByDashboardId(
					dashboardId: $dashboardId
				);
				$this->shareMapper->deleteByDashboardId(
					dashboardId: $dashboardId
				);
				$this->dashboardMapper->delete(entity: $dashboard);

				$this->db->commit();

				// SB1 fix: dispatch cascade event outside the transaction
				// so listeners see a committed row (REQ-CSC-001).
				$deletedUuid = (string)$dashboard->getUuid();
				if ($this->eventDispatcher !== null && $deletedUuid !== '') {
					$this->eventDispatcher->dispatchTyped(
						new DashboardDeletedEvent(
							dashboardUuid: $deletedUuid,
							ownerUserId:   $deletedUserId,
							type:          (string)($dashboard->getType() ?? Dashboard::TYPE_USER),
							deletedAt:     new DateTimeImmutable()
						)
					);
				}
			}//end if
		} catch (Throwable $t) {
			$this->db->rollBack();
			// Log but do not rethrow — we want to continue processing
			// the other dashboards.
			$this->logger->error(
				message: sprintf(
					'launchpad UserDeletedListener: failed to handle dashboard %d: %s',
					$dashboardId,
					$t->getMessage()
				),
				context: ['app' => 'launchpad']
			);
		}//end try
	}//end handleOwnedDashboard()

	/**
	 * Compute the admin pool and pick the new owner per REQ-SHARE-013.
	 *
	 * Selection rule:
	 * 1. User-type shares with `permission_level='full'`, ordered by
	 *    created_at ASC — pick the first still-existing user.
	 * 2. If none, take the alphabetically-first group-type share with
	 *    `permission_level='full'` and from its members pick the
	 *    alphabetically-first uid that still exists.
	 * 3. If both fail, return null (delete path).
	 *
	 * @param int $dashboardId The dashboard ID.
	 * @param string $deletedUserId The deleted user (excluded from pool).
	 *
	 * @return string|null The new owner uid or null.
	 */
	private function pickNewOwner(
		int $dashboardId,
		string $deletedUserId,
	): ?string {
		$fullShares = $this->shareMapper->findByDashboardAndLevel(
			dashboardId: $dashboardId,
			permissionLevel: Dashboard::PERMISSION_FULL
		);

		$userOwner = $this->pickUserShareOwner(
			fullShares: $fullShares,
			deletedUserId: $deletedUserId
		);
		if ($userOwner !== null) {
			return $userOwner;
		}

		return $this->pickGroupShareOwner(
			fullShares: $fullShares,
			deletedUserId: $deletedUserId
		);
	}//end pickNewOwner()

	/**
	 * Step 1 of REQ-SHARE-013 — the first still-existing user-type sharee.
	 *
	 * The mapper already returns rows in `created_at ASC` order, so the
	 * first match in iteration order is the oldest full-permission share.
	 * The deleted user is skipped, as is any uid whose account has since
	 * disappeared.
	 *
	 * @param DashboardShare[] $fullShares Full-permission shares on the dashboard.
	 * @param string $deletedUserId The deleted user (excluded from pool).
	 *
	 * @return string|null The new owner uid, or null when no candidate exists.
	 */
	private function pickUserShareOwner(
		array $fullShares,
		string $deletedUserId,
	): ?string {
		foreach ($fullShares as $share) {
			if ($share->getShareType() !== DashboardShare::SHARE_TYPE_USER) {
				continue;
			}

			$uid = (string)$share->getShareWith();
			if ($uid === $deletedUserId) {
				continue;
			}

			if ($this->userManager->get(uid: $uid) !== null) {
				return $uid;
			}
		}

		return null;
	}//end pickUserShareOwner()

	/**
	 * Step 2 of REQ-SHARE-013 — a member of the alphabetically-first group.
	 *
	 * Group shares are walked in alphabetical order; a group that no
	 * longer resolves, or that yields no usable member, falls through to
	 * the next one.
	 *
	 * @param DashboardShare[] $fullShares Full-permission shares on the dashboard.
	 * @param string $deletedUserId The deleted user (excluded from pool).
	 *
	 * @return string|null The new owner uid, or null when no candidate exists.
	 */
	private function pickGroupShareOwner(
		array $fullShares,
		string $deletedUserId,
	): ?string {
		foreach ($this->sortedGroupShares(fullShares: $fullShares) as $groupShare) {
			$groupId = (string)$groupShare->getShareWith();
			$group = $this->groupManager->get(gid: $groupId);
			if ($group === null) {
				continue;
			}

			$uid = $this->pickExistingMember(
				users: $group->getUsers(),
				deletedUserId: $deletedUserId
			);
			if ($uid !== null) {
				return $uid;
			}
		}//end foreach

		return null;
	}//end pickGroupShareOwner()

	/**
	 * Filter the share list down to group-type rows, alphabetically sorted.
	 *
	 * @param DashboardShare[] $fullShares Full-permission shares on the dashboard.
	 *
	 * @return DashboardShare[] The group-type shares, sorted by group name.
	 */
	private function sortedGroupShares(array $fullShares): array {
		$groupShares = [];
		foreach ($fullShares as $share) {
			if ($share->getShareType() === DashboardShare::SHARE_TYPE_GROUP) {
				$groupShares[] = $share;
			}
		}

		// Sort groups alphabetically.
		usort(
			array: $groupShares,
			callback: static fn ($a, $b) => strcmp(
				string1: (string)$a->getShareWith(),
				string2: (string)$b->getShareWith()
			)
		);

		return $groupShares;
	}//end sortedGroupShares()

	/**
	 * Pick the alphabetically-first still-existing member of a group.
	 *
	 * @param array $users The group's members (IUser instances).
	 * @param string $deletedUserId The deleted user (excluded from pool).
	 *
	 * @return string|null The member uid, or null when none qualifies.
	 */
	private function pickExistingMember(
		array $users,
		string $deletedUserId,
	): ?string {
		// Get members and sort alphabetically.
		$members = [];
		foreach ($users as $user) {
			$members[] = $user->getUID();
		}

		sort(array: $members);

		foreach ($members as $uid) {
			if ($uid === $deletedUserId) {
				continue;
			}

			if ($this->userManager->get(uid: $uid) !== null) {
				return $uid;
			}
		}//end foreach

		return null;
	}//end pickExistingMember()
}//end class
