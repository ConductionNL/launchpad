<?php

/**
 * DashboardShareService
 *
 * Service for managing dashboard shares. Covers REQ-SHARE-001..013:
 * per-row add/remove, bulk replace, revoke-all-for-recipient, ownership
 * transfer, and notification publishing.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
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

namespace OCA\LaunchPad\Service;

use DateTime;
use InvalidArgumentException;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardShare;
use OCA\LaunchPad\Db\DashboardShareMapper;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;

/**
 * Service for creating and managing dashboard shares.
 *
 * @spec openspec/specs/dashboard-sharing/spec.md
 */
class DashboardShareService {

	/**
	 * Permission level ordering for upgrade detection (higher index = more).
	 *
	 * @var array<string, int>
	 */
	private const LEVEL_ORDER = [
		Dashboard::PERMISSION_VIEW_ONLY => 0,
		Dashboard::PERMISSION_ADD_ONLY => 1,
		Dashboard::PERMISSION_FULL => 2,
	];

	/**
	 * Constructor
	 *
	 * @param DashboardShareMapper $shareMapper The share mapper.
	 * @param DashboardMapper $dashboardMapper The dashboard mapper.
	 * @param IDBConnection $db The DB connection.
	 * @param ShareNotifier $notifier Announces written shares to their recipients.
	 */
	public function __construct(
		private readonly DashboardShareMapper $shareMapper,
		private readonly DashboardMapper $dashboardMapper,
		private readonly IDBConnection $db,
		private readonly ShareNotifier $notifier,
	) {
	}//end __construct()

	/**
	 * List all shares for a dashboard.
	 *
	 * @param int $dashboardId The dashboard ID.
	 * @param string $userId The calling user ID (must own the dashboard).
	 *
	 * @return DashboardShare[] The shares.
	 *
	 * @throws ForbiddenException When the user is not the dashboard owner.
	 *
	 * @spec openspec/specs/dashboard-sharing/spec.md
	 */
	public function listShares(int $dashboardId, string $userId): array {
		$this->assertOwner(dashboardId: $dashboardId, userId: $userId);
		return $this->shareMapper->findByDashboardId(dashboardId: $dashboardId);
	}//end listShares()

	/**
	 * Add or upsert a single share. Publishes a notification when the
	 * entry is new or the permission level is upgraded. REQ-SHARE-008.
	 *
	 * @param int        $dashboardId The dashboard ID.
	 * @param ShareGrant $grant       Share type, recipient and permission level.
	 * @param string     $callerId    The calling user (must be dashboard owner).
	 *
	 * @return DashboardShare The persisted share.
	 *
	 * @throws ForbiddenException When the caller is not the owner.
	 * @throws InvalidArgumentException When the input is invalid.
	 *
	 * @spec openspec/specs/dashboard-sharing/spec.md
	 */
	public function addShare(
		int $dashboardId,
		ShareGrant $grant,
		string $callerId,
	): DashboardShare {
		$dashboard = $this->assertOwner(
			dashboardId: $dashboardId,
			userId: $callerId
		);
		$this->validateInput(grant: $grant);

		$result = $this->persistShare(dashboardId: $dashboardId, grant: $grant);

		if ($result['isNew'] === true || $result['isUpgrade'] === true) {
			$this->notifier->shared(
				share: $result['share'],
				sharerUserId: $callerId,
				dashboard: $dashboard
			);
		}

		return $result['share'];
	}//end addShare()

	/**
	 * Remove a share by ID. Silent — no notification is published.
	 * REQ-SHARE-008 (revocations are silent).
	 *
	 * @param int $shareId The share ID.
	 * @param string $callerId The calling user (must be dashboard owner).
	 *
	 * @return void
	 *
	 * @throws ForbiddenException When the caller is not the owner, or the share does not exist.
	 *
	 * @spec openspec/specs/dashboard-sharing/spec.md
	 */
	public function removeShare(int $shareId, string $callerId): void {
		try {
			$share     = $this->shareMapper->find(id: $shareId);
			$dashboard = $this->dashboardMapper->find(
				id: (int)$share->getDashboardId()
			);
		} catch (DoesNotExistException) {
			// A missing share answers like someone else's, so share ids cannot be probed.
			throw new ForbiddenException(message: 'Access denied');
		}

		if ($dashboard->getUserId() !== $callerId) {
			throw new ForbiddenException(message: 'Access denied');
		}

		$this->shareMapper->delete(entity: $share);
	}//end removeShare()

	/**
	 * Atomically replace all shares for a dashboard. REQ-SHARE-009.
	 *
	 * Deletes every existing share not in the payload, upserts matching
	 * ones. Publishes one notification per newly-added or upgraded
	 * recipient only. All DB writes run in a single transaction; a database
	 * error is rethrown after the rollback.
	 *
	 * @param int $dashboardId The dashboard ID.
	 * @param array $shares Array of {shareType, shareWith, permissionLevel}.
	 * @param string $userId The calling user (must be dashboard owner).
	 *
	 * @return DashboardShare[] The new full share list.
	 *
	 * @throws ForbiddenException When the caller is not the owner.
	 * @throws InvalidArgumentException When the input is invalid.
	 *
	 * @spec openspec/specs/dashboard-sharing/spec.md
	 */
	public function replaceShares(
		int $dashboardId,
		array $shares,
		string $userId,
	): array {
		$dashboard = $this->assertOwner(
			dashboardId: $dashboardId,
			userId: $userId
		);

		// Validate all entries up-front before touching the DB.
		$grants = $this->validatedGrants(shares: $shares);

		$notifyQueue = $this->replaceInTransaction(dashboardId: $dashboardId, grants: $grants);

		// Publish notifications after the transaction commits.
		foreach ($notifyQueue as $share) {
			$this->notifier->shared(
				share: $share,
				sharerUserId: $userId,
				dashboard: $dashboard
			);
		}

		return $this->shareMapper->findByDashboardId(dashboardId: $dashboardId);
	}//end replaceShares()

	/**
	 * Turn every payload entry into a validated grant.
	 *
	 * @param array $shares The payload entries.
	 *
	 * @return ShareGrant[] The grants, in payload order.
	 *
	 * @throws InvalidArgumentException When any entry is invalid.
	 */
	private function validatedGrants(array $shares): array {
		$grants = [];
		foreach ($shares as $entry) {
			$grant = $this->grantFrom(entry: $entry);
			$this->validateInput(grant: $grant);
			$grants[] = $grant;
		}

		return $grants;
	}//end validatedGrants()

	/**
	 * Replace the shares of a dashboard with the grants in one transaction.
	 *
	 * Shares whose `type:with` key is not granted are deleted first; every
	 * grant is then upserted. Rolls back when any step throws.
	 *
	 * @param int          $dashboardId The dashboard ID.
	 * @param ShareGrant[] $grants      The validated grants.
	 *
	 * @return DashboardShare[] The new and upgraded shares to notify.
	 */
	private function replaceInTransaction(int $dashboardId, array $grants): array {
		$keepKeys = [];
		foreach ($grants as $grant) {
			$keepKeys[] = $grant->shareType . ':' . $grant->shareWith;
		}

		$notifyQueue = [];

		$this->db->beginTransaction();
		$committed = false;
		try {
			// Remove shares not in payload.
			$this->shareMapper->deleteNotIn(
				dashboardId: $dashboardId,
				keepKeys: $keepKeys
			);

			foreach ($grants as $grant) {
				$result = $this->persistShare(dashboardId: $dashboardId, grant: $grant);
				if ($result['isNew'] === true || $result['isUpgrade'] === true) {
					$notifyQueue[] = $result['share'];
				}
			}

			$this->db->commit();
			$committed = true;
		} finally {
			if ($committed === false) {
				$this->db->rollBack();
			}
		}//end try

		return $notifyQueue;
	}//end replaceInTransaction()

	/**
	 * Remove every share where the caller is the owner AND the share targets
	 * the named recipient. REQ-SHARE-010.
	 *
	 * Only touches dashboards owned by $callerId — shares on dashboards
	 * owned by others are not affected even if $callerId holds a `full`
	 * share on them.
	 *
	 * @param string $shareType The share type.
	 * @param string $shareWith The recipient user/group ID.
	 * @param string $callerId The calling user (owner restriction).
	 *
	 * @return int The number of share rows deleted.
	 *
	 * @throws InvalidArgumentException When shareType is invalid.
	 *
	 * @spec openspec/specs/dashboard-sharing/spec.md
	 */
	public function revokeAllForRecipient(
		string $shareType,
		string $shareWith,
		string $callerId,
	): int {
		$validType = in_array(
			needle: $shareType,
			haystack: DashboardShare::VALID_SHARE_TYPES,
			strict: true
		);
		if ($validType === false) {
			throw new InvalidArgumentException(
				message: 'Invalid shareType: ' . $shareType
			);
		}

		return $this->shareMapper->deleteByOwnerAndRecipient(
			shareType: $shareType,
			shareWith: $shareWith,
			ownerId: $callerId
		);
	}//end revokeAllForRecipient()

	/**
	 * Resolve every share that grants the given user access to a dashboard,
	 * keyed by dashboard id. When the user is reached via multiple shares
	 * (e.g. direct + group), the most permissive level wins.
	 *
	 * Used by PermissionService to compute effective access on dashboards
	 * the user does not own. REQ-SHARE-002, REQ-SHARE-004.
	 *
	 * @param string $userId The recipient user id.
	 * @param string[] $groupIds The recipient's group ids.
	 *
	 * @return array<int,string> Map of dashboardId => permission level.
	 *
	 * @spec openspec/specs/dashboard-sharing/spec.md
	 */
	public function resolveSharedDashboards(string $userId, array $groupIds): array {
		$shares = $this->shareMapper->findForRecipient(
			userId: $userId,
			groupIds: $groupIds
		);

		$result = [];
		foreach ($shares as $share) {
			$dashboardId = (int)$share->getDashboardId();
			$currentLevel = (string)$share->getPermissionLevel();
			$currentRank = (self::LEVEL_ORDER[$currentLevel] ?? 0);
			$existingRank = (self::LEVEL_ORDER[$result[$dashboardId] ?? ''] ?? -1);
			if ($currentRank > $existingRank) {
				$result[$dashboardId] = $currentLevel;
			}
		}

		return $result;
	}//end resolveSharedDashboards()

	/**
	 * Transfer dashboard ownership to a new user.
	 *
	 * Updates the dashboard's user_id, removes the share row that
	 * previously gave the new owner access, and stamps updated_at.
	 * REQ-SHARE-013 (internal helper called by UserDeletedListener).
	 *
	 * @param int $dashboardId The dashboard ID.
	 * @param string $newUserId The new owner's user ID.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboard-sharing/spec.md
	 */
	public function transferOwnership(int $dashboardId, string $newUserId): void {
		$dashboard = $this->dashboardMapper->find(id: $dashboardId);

		// Update ownership.
		$dashboard->setUserId($newUserId);
		$dashboard->setUpdatedAt(
			(new DateTime())->format(format: 'Y-m-d H:i:s')
		);
		$this->dashboardMapper->update(entity: $dashboard);

		// Remove the share row that gave newUserId access (they now own it).
		$existingShare = $this->shareMapper->findShare(
			dashboardId: $dashboardId,
			shareType: DashboardShare::SHARE_TYPE_USER,
			shareWith: $newUserId
		);
		if ($existingShare !== null) {
			$this->shareMapper->delete(entity: $existingShare);
		}
	}//end transferOwnership()

	/**
	 * Publish a `dashboard_ownership_transferred` notification.
	 *
	 * @param string $newOwnerId The new owner's user ID.
	 * @param int $dashboardId The dashboard ID.
	 * @param string $dashboardName The dashboard name.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboard-sharing/spec.md
	 */
	public function notifyOwnershipTransferred(
		string $newOwnerId,
		int $dashboardId,
		string $dashboardName,
	): void {
		$this->notifier->ownershipTransferred(
			newOwnerId: $newOwnerId,
			dashboardId: $dashboardId,
			dashboardName: $dashboardName
		);
	}//end notifyOwnershipTransferred()

	/**
	 * Persist a single share (insert or update). Returns metadata about
	 * whether the row is new or the level was upgraded.
	 *
	 * @param int        $dashboardId The dashboard ID.
	 * @param ShareGrant $grant       Share type, recipient and permission level.
	 *
	 * @return array{share: DashboardShare, isNew: bool, isUpgrade: bool}
	 */
	private function persistShare(int $dashboardId, ShareGrant $grant): array {
		$existing = $this->shareMapper->findShare(
			dashboardId: $dashboardId,
			shareType: $grant->shareType,
			shareWith: $grant->shareWith
		);

		if ($existing === null) {
			return [
				'share' => $this->insertShare(dashboardId: $dashboardId, grant: $grant),
				'isNew' => true,
				'isUpgrade' => false,
			];
		}

		return $this->changeLevel(existing: $existing, permissionLevel: $grant->permissionLevel);
	}//end persistShare()

	/**
	 * Insert a new share row for a grant.
	 *
	 * @param int        $dashboardId The dashboard ID.
	 * @param ShareGrant $grant       The validated grant.
	 *
	 * @return DashboardShare The inserted share.
	 */
	private function insertShare(int $dashboardId, ShareGrant $grant): DashboardShare {
		$now = (new DateTime())->format(format: 'Y-m-d H:i:s');

		$share = new DashboardShare();
		$share->setDashboardId($dashboardId);
		$share->setShareType($grant->shareType);
		$share->setShareWith($grant->shareWith);
		$share->setPermissionLevel($grant->permissionLevel);
		$share->setCreatedAt($now);
		$share->setUpdatedAt($now);

		return $this->shareMapper->insert(entity: $share);
	}//end insertShare()

	/**
	 * Move an existing share to a permission level.
	 *
	 * The same level is a no-op; a different level is written, and flagged
	 * as an upgrade only when it ranks higher than the old one.
	 *
	 * @param DashboardShare $existing        The existing share.
	 * @param string         $permissionLevel The requested level.
	 *
	 * @return array{share: DashboardShare, isNew: bool, isUpgrade: bool}
	 */
	private function changeLevel(DashboardShare $existing, string $permissionLevel): array {
		$oldLevel = (string)$existing->getPermissionLevel();
		if ($oldLevel === $permissionLevel) {
			return [
				'share' => $existing,
				'isNew' => false,
				'isUpgrade' => false,
			];
		}

		$isUpgrade = ((self::LEVEL_ORDER[$permissionLevel] ?? 0) > (self::LEVEL_ORDER[$oldLevel] ?? 0));

		$existing->setPermissionLevel($permissionLevel);
		$existing->setUpdatedAt((new DateTime())->format(format: 'Y-m-d H:i:s'));

		return [
			'share' => $this->shareMapper->update(entity: $existing),
			'isNew' => false,
			'isUpgrade' => $isUpgrade,
		];
	}//end changeLevel()

	/**
	 * Read one entry of a share replace as a grant: an object whose
	 * shareType, shareWith and permissionLevel, when present, are strings.
	 * A missing field reads as an empty string, which
	 * {@see self::validateInput()} then rejects.
	 *
	 * @param array|string|int|float|bool|null $entry The entry from the request.
	 *
	 * @return ShareGrant The grant.
	 *
	 * @throws InvalidArgumentException When the entry is not an object or a field is not a string.
	 */
	private function grantFrom(array|string|int|float|bool|null $entry): ShareGrant {
		if (is_array($entry) === false) {
			throw new InvalidArgumentException(message: 'Each share must be an object');
		}

		$fields = [];
		foreach (['shareType', 'shareWith', 'permissionLevel'] as $field) {
			$value = $entry[$field] ?? '';
			if (is_string($value) === false) {
				throw new InvalidArgumentException(message: $field . ' must be a string');
			}

			$fields[$field] = $value;
		}

		return new ShareGrant(
			shareType: $fields['shareType'],
			shareWith: $fields['shareWith'],
			permissionLevel: $fields['permissionLevel']
		);
	}//end grantFrom()

	/**
	 * Validate share type, shareWith, and permission level.
	 *
	 * @param ShareGrant $grant Share type, recipient and permission level.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException On invalid input.
	 */
	private function validateInput(ShareGrant $grant): void {
		$shareType = $grant->shareType;
		$permissionLevel = $grant->permissionLevel;
		$validType = in_array(
			needle: $shareType,
			haystack: DashboardShare::VALID_SHARE_TYPES,
			strict: true
		);
		if ($validType === false) {
			throw new InvalidArgumentException(
				message: 'Invalid shareType: ' . $shareType
			);
		}

		if ($grant->shareWith === '') {
			throw new InvalidArgumentException(message: 'shareWith is required');
		}

		$validLevel = in_array(
			needle: $permissionLevel,
			haystack: DashboardShare::VALID_PERMISSION_LEVELS,
			strict: true
		);
		if ($validLevel === false) {
			throw new InvalidArgumentException(
				message: 'Invalid permissionLevel: ' . $permissionLevel
			);
		}
	}//end validateInput()

	/**
	 * Assert the caller is the dashboard owner and return the dashboard.
	 *
	 * @param int $dashboardId The dashboard ID.
	 * @param string $userId The expected owner.
	 *
	 * @return Dashboard The dashboard.
	 *
	 * @throws ForbiddenException When the user is not the owner.
	 */
	private function assertOwner(int $dashboardId, string $userId): Dashboard {
		$dashboard = $this->dashboardMapper->find(id: $dashboardId);

		if ($dashboard->getUserId() !== $userId) {
			throw new ForbiddenException(message: 'Access denied');
		}

		return $dashboard;
	}//end assertOwner()
}//end class
