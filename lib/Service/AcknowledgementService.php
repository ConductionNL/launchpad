<?php

/**
 * AcknowledgementService
 *
 * Service that mediates every read and write against the local
 * `oc_launchpad_acknowledgements` receipt table and computes the
 * forced-delivery / read-receipt state for mandatory-read announcements.
 * Owns idempotent acknowledgement writes, outstanding-item resolution,
 * re-acknowledge-on-change handling, and the audience-scoped admin report.
 * REQ-ACK-002..006.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTimeImmutable;
use OCA\LaunchPad\Activity\ActivityPublisher;
use OCA\LaunchPad\Activity\ActivityRow;
use OCA\LaunchPad\Activity\ActivitySubject;
use OCA\LaunchPad\Activity\Extension;
use OCA\LaunchPad\Db\Acknowledgement;
use OCA\LaunchPad\Db\AcknowledgementMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception as DbException;
use OCP\IGroupManager;

/**
 * Service for mandatory-read acknowledgements and read receipts.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Receipts, placements, dashboards
 *                                                 and the activity row it writes.
 *
 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
 */
class AcknowledgementService {
	/**
	 * Constructor
	 *
	 * @param AcknowledgementMapper $acknowledgementMapper The receipt mapper.
	 * @param WidgetPlacementMapper $placementMapper Placement lookups by
	 *                                               announcement key.
	 * @param DashboardMapper $dashboardMapper Dashboard lookups
	 *                                         (template routing,
	 *                                         recipient dashboards).
	 * @param IGroupManager $groupManager Live audience
	 *                                    resolution (design D5).
	 * @param ActivityPublisher $activityPublisher Activity emission on
	 *                                             first acknowledgement.
	 */
	public function __construct(
		private readonly AcknowledgementMapper $acknowledgementMapper,
		private readonly WidgetPlacementMapper $placementMapper,
		private readonly DashboardMapper $dashboardMapper,
		private readonly IGroupManager $groupManager,
		private readonly ActivityPublisher $activityPublisher,
	) {
	}//end __construct()

	/**
	 * Record an acknowledgement receipt for `$userId` at `$contentVersion`.
	 * Idempotent (REQ-ACK-003): a repeated acknowledgement of the same
	 * `(announcementKey, userId, contentVersion)` never inserts a second
	 * row and returns the original receipt. An activity event is emitted
	 * for the first insert only (REQ-ACK-006).
	 *
	 * The caller (controller) is responsible for enforcing that `$userId`
	 * is the authenticated user — this service never derives the acting
	 * user itself (ADR-005, no IDOR).
	 *
	 * @param string $announcementKey The announcement identity.
	 * @param string $userId The acknowledging user ID.
	 * @param int $contentVersion The content version being acknowledged.
	 *
	 * @return Acknowledgement The stored (or pre-existing) receipt.
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	public function acknowledge(
		string $announcementKey,
		string $userId,
		int $contentVersion,
	): Acknowledgement {
		$existing = $this->acknowledgementMapper->findOneFor(
			announcementKey: $announcementKey,
			userId: $userId,
			contentVersion: $contentVersion
		);
		if ($existing !== null) {
			return $existing;
		}

		try {
			$receipt = $this->acknowledgementMapper->record(
				announcementKey: $announcementKey,
				userId: $userId,
				contentVersion: $contentVersion
			);
		} catch (DbException $exception) {
			// Race with a concurrent identical write — the unique index
			// enforced idempotency. Return the row that won the race and
			// do NOT emit a second activity event.
			if ($exception->getReason() !== DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $exception;
			}

			$winner = $this->acknowledgementMapper->findOneFor(
				announcementKey: $announcementKey,
				userId: $userId,
				contentVersion: $contentVersion
			);
			if ($winner !== null) {
				return $winner;
			}

			// Extremely unlikely: the row vanished between the failed
			// insert and this read. Re-raise the original exception.
			throw $exception;
		}//end try

		$this->emitAcknowledgedActivity(receipt: $receipt);

		return $receipt;
	}//end acknowledge()

	/**
	 * Whether a user may acknowledge an announcement: only its live
	 * audience may, that is the members of the groups its template routes
	 * to, or, without group routing, the recipients holding a copy. An
	 * unknown announcement has no audience. Backs the acknowledge guard
	 * (REQ-ACK-003, ADR-005).
	 *
	 * @param string $announcementKey The announcement identity.
	 * @param string $userId          The user to look for.
	 *
	 * @return bool True when the user is in the audience.
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	public function isAllowedToAcknowledge(string $announcementKey, string $userId): bool {
		$placements = $this->placementMapper->findByAnnouncementKey(
			announcementKey: $announcementKey
		);
		if (empty($placements) === true) {
			return false;
		}

		return in_array(needle: $userId, haystack: $this->resolveAudience(placements: $placements), strict: true);
	}//end isAllowedToAcknowledge()

	/**
	 * Determine whether the given placement is an outstanding
	 * (unacknowledged) mandatory item for `$userId`. Backs the
	 * forced-delivery gate (REQ-ACK-002) and the re-acknowledge-on-change
	 * branch split (REQ-ACK-005).
	 *
	 * When `reacknowledgeOnChange = 1` the user must hold a receipt for the
	 * exact current content version; when `0`, any prior receipt for the
	 * announcement satisfies the requirement.
	 *
	 * @param WidgetPlacement $placement The placement.
	 * @param string $userId The user ID.
	 *
	 * @return bool True when the item is outstanding for the user.
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	public function isOutstanding(WidgetPlacement $placement, string $userId): bool {
		if ($placement->getRequiresAcknowledgement() !== 1) {
			return false;
		}

		$key = (string)$placement->getAnnouncementKey();
		if ($key === '') {
			return false;
		}

		$version = $placement->getAcknowledgementContentVersion();

		if ($placement->getReacknowledgeOnChange() === 1) {
			return $this->acknowledgementMapper->existsFor(
				announcementKey: $key,
				userId: $userId,
				contentVersion: $version
			) === false;
		}

		// With reacknowledgeOnChange = 0 a receipt at ANY version satisfies.
		$receipts = $this->acknowledgementMapper->findByUserForAnnouncement(
			announcementKey: $key,
			userId: $userId
		);

		return empty($receipts);
	}//end isOutstanding()

	/**
	 * Return the current user's outstanding mandatory items across their
	 * own dashboards, limited to announcements still addressed to them: a
	 * copy kept after leaving the audience cannot be acknowledged, so it is
	 * not pending. REQ-ACK-002 (outstanding-count indicator).
	 *
	 * @param string $userId The current user ID.
	 *
	 * @return array{count: int, items: array<int, array<string, mixed>>}
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	public function getPending(string $userId): array {
		$dashboards = $this->dashboardMapper->findByUserId(userId: $userId);
		$items = [];
		$addressed = [];

		foreach ($dashboards as $dashboard) {
			$placements = $this->placementMapper->findByDashboardId(
				dashboardId: (int)$dashboard->getId()
			);
			foreach ($placements as $placement) {
				if ($this->isOutstanding(placement: $placement, userId: $userId) === false) {
					continue;
				}

				$key = (string)$placement->getAnnouncementKey();
				if (array_key_exists($key, $addressed) === false) {
					$addressed[$key] = $this->isAllowedToAcknowledge(announcementKey: $key, userId: $userId);
				}

				if ($addressed[$key] === true) {
					$items[] = $this->pendingItem(dashboard: $dashboard, placement: $placement);
				}
			}
		}

		return [
			'count' => count($items),
			'items' => $items,
		];
	}//end getPending()

	/**
	 * The current content version of an announcement for a user: the
	 * version on its template placement, or, when the announcement has no
	 * template (any more), the version on the user's own copy. Null when no
	 * placement carries the key.
	 *
	 * @param string $announcementKey The announcement identity.
	 * @param string $userId          The user whose copy counts without a template.
	 *
	 * @return int|null The current content version, or null.
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	public function currentContentVersion(string $announcementKey, string $userId): ?int {
		$placements = $this->placementMapper->findByAnnouncementKey(announcementKey: $announcementKey);
		$placement = $this->templatePlacement(placements: $placements)
			?? $this->ownCopy(placements: $placements, userId: $userId)
			?? ($placements[0] ?? null);
		if ($placement === null) {
			return null;
		}

		return $placement->getAcknowledgementContentVersion();
	}//end currentContentVersion()

	/**
	 * Build the pending-list entry for an outstanding placement.
	 *
	 * @param Dashboard       $dashboard The dashboard holding the placement.
	 * @param WidgetPlacement $placement The outstanding placement.
	 *
	 * @return array<string, mixed> The pending item.
	 */
	private function pendingItem(Dashboard $dashboard, WidgetPlacement $placement): array {
		return [
			'placementId' => $placement->getId(),
			'dashboardUuid' => $dashboard->getUuid(),
			'announcementKey' => $placement->getAnnouncementKey(),
			'prompt' => $placement->getAcknowledgementPrompt(),
			'deadline' => $placement->getAcknowledgementDeadline(),
			'contentVersion' => $placement->getAcknowledgementContentVersion(),
		];
	}//end pendingItem()

	/**
	 * Build the audience-scoped read-receipt report for an announcement.
	 * The audience is resolved LIVE from the source template's group
	 * routing via {@see IGroupManager} (design D5) so newly added members
	 * automatically appear as pending. Pending is computed as
	 * `audience − acknowledged(currentVersion)`. REQ-ACK-004.
	 *
	 * @param string $announcementKey The announcement identity.
	 *
	 * @return array{
	 *   announcementKey: string,
	 *   contentVersion: int,
	 *   deadline: string|null,
	 *   overdue: bool,
	 *   acknowledgedCount: int,
	 *   pendingCount: int,
	 *   pending: array<int, string>,
	 *   acknowledged: array<int, array{userId: string, acknowledgedAt: string|null}>,
	 *   rows: array<int, array{userId: string, status: string, acknowledgedAt: string|null}>
	 * }
	 *
	 * @throws DoesNotExistException When no placement carries the key.
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	public function report(string $announcementKey): array {
		$placements = $this->placementMapper->findByAnnouncementKey(
			announcementKey: $announcementKey
		);
		if (empty($placements) === true) {
			throw new DoesNotExistException(
				msg: 'Unknown announcement key'
			);
		}

		$blueprint = $this->resolveBlueprintPlacement(placements: $placements);
		$contentVersion = $blueprint->getAcknowledgementContentVersion();
		$deadline = $blueprint->getAcknowledgementDeadline();

		$audience = $this->resolveAudience(placements: $placements);

		$ackMap = $this->acknowledgedAtByUser(announcementKey: $announcementKey, contentVersion: $contentVersion);
		$rows = $this->reportRows(audience: $audience, ackMap: $ackMap);
		$acknowledged = $this->acknowledgedEntries(rows: $rows);
		$pending = $this->pendingUserIds(rows: $rows);

		return [
			'announcementKey' => $announcementKey,
			'contentVersion' => $contentVersion,
			'deadline' => $deadline,
			'overdue' => $this->isOverdue(deadline: $deadline),
			'acknowledgedCount' => count($acknowledged),
			'pendingCount' => count($pending),
			'pending' => $pending,
			'acknowledged' => $acknowledged,
			'rows' => $rows,
		];
	}//end report()

	/**
	 * Map userId => acknowledgedAt for the current version.
	 *
	 * @param string $announcementKey The announcement key.
	 * @param int    $contentVersion  The current content version.
	 *
	 * @return array<string, string|null>
	 */
	private function acknowledgedAtByUser(string $announcementKey, int $contentVersion): array {
		$ackMap = [];
		$receipts = $this->acknowledgementMapper->findByAnnouncement(
			announcementKey: $announcementKey,
			contentVersion: $contentVersion
		);
		foreach ($receipts as $receipt) {
			$rid = (string)$receipt->getUserId();
			$ackMap[$rid] = $receipt->getAcknowledgedAtFormatted();
		}

		return $ackMap;
	}//end acknowledgedAtByUser()

	/**
	 * One report row per audience member, in audience order.
	 *
	 * @param string[]                   $audience The audience user IDs.
	 * @param array<string, string|null> $ackMap   userId => acknowledgedAt.
	 *
	 * @return array<int, array{userId: string, status: string, acknowledgedAt: string|null}>
	 */
	private function reportRows(array $audience, array $ackMap): array {
		$rows = [];
		foreach ($audience as $uid) {
			if (array_key_exists($uid, $ackMap) === true) {
				$rows[] = [
					'userId' => $uid,
					'status' => 'acknowledged',
					'acknowledgedAt' => $ackMap[$uid],
				];
				continue;
			}

			$rows[] = [
				'userId' => $uid,
				'status' => 'pending',
				'acknowledgedAt' => null,
			];
		}

		return $rows;
	}//end reportRows()

	/**
	 * The acknowledged rows as `{userId, acknowledgedAt}`.
	 *
	 * @param array<int, array{userId: string, status: string, acknowledgedAt: string|null}> $rows The report rows.
	 *
	 * @return array<int, array{userId: string, acknowledgedAt: string|null}>
	 */
	private function acknowledgedEntries(array $rows): array {
		$acknowledged = [];
		foreach ($rows as $row) {
			if ($row['status'] === 'acknowledged') {
				$acknowledged[] = [
					'userId' => $row['userId'],
					'acknowledgedAt' => $row['acknowledgedAt'],
				];
			}
		}

		return $acknowledged;
	}//end acknowledgedEntries()

	/**
	 * The user IDs of the pending rows.
	 *
	 * @param array<int, array{userId: string, status: string, acknowledgedAt: string|null}> $rows The report rows.
	 *
	 * @return string[]
	 */
	private function pendingUserIds(array $rows): array {
		$pending = [];
		foreach ($rows as $row) {
			if ($row['status'] === 'pending') {
				$pending[] = $row['userId'];
			}
		}

		return $pending;
	}//end pendingUserIds()

	/**
	 * Resolve the owning user id of the announcement's blueprint dashboard,
	 * used by the controller to authorize the requirement-setting and the
	 * read-receipt report to the template owner (REQ-ACK-004, ADR-005).
	 * Returns null when the announcement is unknown or its dashboard row
	 * is missing.
	 *
	 * @param string $announcementKey The announcement identity.
	 *
	 * @return string|null The owning user id, or null.
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	public function resolveOwnerUserId(string $announcementKey): ?string {
		$blueprint = $this->blueprintOf(announcementKey: $announcementKey);
		if ($blueprint === null) {
			return null;
		}

		$dashboard = $this->resolveDashboard(placement: $blueprint);
		if ($dashboard === null) {
			return null;
		}

		$owner = $dashboard->getUserId();
		if ($owner === null || $owner === '') {
			return null;
		}

		return $owner;
	}//end resolveOwnerUserId()

	/**
	 * The blueprint placement of an announcement, or null when no placement
	 * carries the key.
	 *
	 * @param string $announcementKey The announcement identity.
	 *
	 * @return WidgetPlacement|null The blueprint placement.
	 */
	private function blueprintOf(string $announcementKey): ?WidgetPlacement {
		$placements = $this->placementMapper->findByAnnouncementKey(
			announcementKey: $announcementKey
		);
		if (empty($placements) === true) {
			return null;
		}

		return $this->resolveBlueprintPlacement(placements: $placements);
	}//end blueprintOf()

	/**
	 * Resolve the dashboard that a placement belongs to, or null when the
	 * dashboard row has gone missing.
	 *
	 * @param WidgetPlacement $placement The placement.
	 *
	 * @return Dashboard|null The owning dashboard or null.
	 */
	private function resolveDashboard(WidgetPlacement $placement): ?Dashboard {
		try {
			return $this->dashboardMapper->find(
				id: (int)$placement->getDashboardId()
			);
		} catch (DoesNotExistException) {
			return null;
		}
	}//end resolveDashboard()

	/**
	 * Pick the blueprint placement — the one on the admin template — from
	 * the set sharing an announcement key. Falls back to the first
	 * placement when none sits on a template (e.g. a single-user
	 * dashboard that never came from a template).
	 *
	 * @param WidgetPlacement[] $placements The placements sharing the key.
	 *
	 * @return WidgetPlacement The blueprint placement.
	 */
	private function resolveBlueprintPlacement(array $placements): WidgetPlacement {
		return $this->templatePlacement(placements: $placements) ?? $placements[0];
	}//end resolveBlueprintPlacement()

	/**
	 * The placement that sits on an admin template, or null.
	 *
	 * @param WidgetPlacement[] $placements The placements sharing the key.
	 *
	 * @return WidgetPlacement|null The template placement.
	 */
	private function templatePlacement(array $placements): ?WidgetPlacement {
		foreach ($placements as $placement) {
			$dashboard = $this->resolveDashboard(placement: $placement);
			if ($dashboard !== null
				&& $dashboard->getType() === Dashboard::TYPE_ADMIN_TEMPLATE
			) {
				return $placement;
			}
		}

		return null;
	}//end templatePlacement()

	/**
	 * The placement on a dashboard the user owns, or null.
	 *
	 * @param WidgetPlacement[] $placements The placements sharing the key.
	 * @param string            $userId     The user.
	 *
	 * @return WidgetPlacement|null The user's copy.
	 */
	private function ownCopy(array $placements, string $userId): ?WidgetPlacement {
		foreach ($placements as $placement) {
			if ($this->resolveDashboard(placement: $placement)?->getUserId() === $userId) {
				return $placement;
			}
		}

		return null;
	}//end ownCopy()

	/**
	 * Resolve the current audience for an announcement, live, from the
	 * source template's group routing. When the template routes to one or
	 * more groups the audience is the union of those groups' current
	 * members. When the template has no group routing (applies-to-all)
	 * the audience is the set of recipients that actually hold a cloned
	 * placement for the announcement. REQ-ACK-004 (design D5).
	 *
	 * @param WidgetPlacement[] $placements The placements sharing the key.
	 *
	 * @return array<int, string> Sorted, de-duplicated user ids.
	 */
	private function resolveAudience(array $placements): array {
		$groups = [];
		foreach ($placements as $placement) {
			$dashboard = $this->resolveDashboard(placement: $placement);
			if ($dashboard !== null
				&& $dashboard->getType() === Dashboard::TYPE_ADMIN_TEMPLATE
			) {
				$groups = $dashboard->getTargetGroupsArray();
				break;
			}
		}

		$audience = [];
		if (empty($groups) === false) {
			$audience = $this->audienceFromGroups(groups: $groups);
		}

		if (empty($groups) === true) {
			// Applies-to-all fallback — the recipients who hold a clone.
			$audience = $this->audienceFromRecipients(placements: $placements);
		}

		// Numeric user ids come back from array_keys() as int keys.
		$ids = array_map(callback: static fn (int|string $uid): string => (string)$uid, array: array_keys($audience));
		sort($ids);

		return $ids;
	}//end resolveAudience()

	/**
	 * Build the audience map (userId => true) from the union of the live
	 * members of the given groups. REQ-ACK-004 (design D5).
	 *
	 * @param array<int, mixed> $groups The target group ids.
	 *
	 * @return array<string, bool> The audience membership map.
	 */
	private function audienceFromGroups(array $groups): array {
		$audience = [];
		foreach ($groups as $gid) {
			$group = $this->groupManager->get(gid: (string)$gid);
			if ($group === null) {
				continue;
			}

			foreach ($group->getUsers() as $user) {
				$audience[$user->getUID()] = true;
			}
		}

		return $audience;
	}//end audienceFromGroups()

	/**
	 * Build the audience map (userId => true) from the recipients that hold
	 * a cloned (non-template) placement for the announcement — the
	 * applies-to-all fallback when the template has no group routing.
	 *
	 * @param WidgetPlacement[] $placements The placements sharing the key.
	 *
	 * @return array<string, bool> The audience membership map.
	 */
	private function audienceFromRecipients(array $placements): array {
		$audience = [];
		foreach ($placements as $placement) {
			$dashboard = $this->resolveDashboard(placement: $placement);
			if ($dashboard === null
				|| $dashboard->getType() === Dashboard::TYPE_ADMIN_TEMPLATE
			) {
				continue;
			}

			$owner = (string)$dashboard->getUserId();
			if ($owner !== '') {
				$audience[$owner] = true;
			}
		}

		return $audience;
	}//end audienceFromRecipients()

	/**
	 * Whether an ISO `Y-m-d` deadline lies strictly in the past. A null or
	 * empty deadline is never overdue. REQ-ACK-004 (overdue reporting).
	 *
	 * @param string|null $deadline The `Y-m-d` deadline or null.
	 *
	 * @return bool True when the deadline has passed.
	 */
	private function isOverdue(?string $deadline): bool {
		if ($deadline === null || $deadline === '') {
			return false;
		}

		$today = (new DateTimeImmutable('today'))->format('Y-m-d');

		return $deadline < $today;
	}//end isOverdue()

	/**
	 * Emit exactly one `dashboard_acknowledged` activity row for the first
	 * (non-idempotent) acknowledgement. Best-effort: the publisher swallows
	 * and logs any failure so a broken Activity backend never rolls the
	 * receipt back (REQ-ACK-006, mirrors the cross-capability contract).
	 *
	 * @param Acknowledgement $receipt The new receipt; its content version is
	 *                                 part of the activity line.
	 *
	 * @return void
	 */
	private function emitAcknowledgedActivity(Acknowledgement $receipt): void {
		$announcementKey = (string)$receipt->getAnnouncementKey();
		$userId = (string)$receipt->getUserId();
		$dashboardUuid = $announcementKey;
		$dashboardName = $announcementKey;

		$placements = $this->placementMapper->findByAnnouncementKey(
			announcementKey: $announcementKey
		);
		if (empty($placements) === false) {
			$blueprint = $this->resolveBlueprintPlacement(placements: $placements);
			$dashboard = $this->resolveDashboard(placement: $blueprint);
			if ($dashboard !== null) {
				$dashboardUuid = (string)$dashboard->getUuid();
				$dashboardName = (string)($dashboard->getName() ?? $announcementKey);
			}
		}

		$row = new ActivityRow(
			type: Extension::EVENT_ACKNOWLEDGED,
			actorUserId: $userId,
			dashboard: new ActivitySubject(uuid: $dashboardUuid, name: $dashboardName),
			extraParams: ['self' => true, 'version' => (int)$receipt->getContentVersion()]
		);
		$this->activityPublisher->publish(row: $row, recipientUserId: $userId);
	}//end emitAcknowledgedActivity()
}//end class
