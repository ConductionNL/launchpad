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

use OCA\LaunchPad\Activity\Extension;
use OCA\LaunchPad\Activity\ActivityPublisher;
use OCA\LaunchPad\Db\Acknowledgement;
use OCA\LaunchPad\Db\AcknowledgementMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use DateTimeImmutable;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception as DbException;
use OCP\IGroupManager;

/**
 * Service for mandatory-read acknowledgements and read receipts.
 *
 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
 */
class AcknowledgementService
{
    /**
     * Constructor
     *
     * @param AcknowledgementMapper $acknowledgementMapper The receipt mapper.
     * @param WidgetPlacementMapper $placementMapper       Placement lookups by
     *                                                     announcement key.
     * @param DashboardMapper       $dashboardMapper       Dashboard lookups
     *                                                     (template routing,
     *                                                     recipient dashboards).
     * @param IGroupManager         $groupManager          Live audience
     *                                                     resolution (design D5).
     * @param ActivityPublisher     $activityPublisher     Activity emission on
     *                                                     first acknowledgement.
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
     * @param string $userId          The acknowledging user ID.
     * @param int    $contentVersion  The content version being acknowledged.
     *
     * @return Acknowledgement The stored (or pre-existing) receipt.
     *
     * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
     */
    public function acknowledge(
        string $announcementKey,
        string $userId,
        int $contentVersion
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

        $this->emitAcknowledgedActivity(
            announcementKey: $announcementKey,
            userId: $userId
        );

        return $receipt;
    }//end acknowledge()

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
     * @param string          $userId    The user ID.
     *
     * @return bool True when the item is outstanding for the user.
     *
     * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
     */
    public function isOutstanding(WidgetPlacement $placement, string $userId): bool
    {
        if ($placement->getRequiresAcknowledgement() !== 1) {
            return false;
        }

        $key = (string) $placement->getAnnouncementKey();
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
     * own dashboards. REQ-ACK-002 (outstanding-count indicator).
     *
     * @param string $userId The current user ID.
     *
     * @return array{count: int, items: array<int, array<string, mixed>>}
     *
     * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
     */
    public function getPending(string $userId): array
    {
        $dashboards = $this->dashboardMapper->findByUserId(userId: $userId);
        $items      = [];

        foreach ($dashboards as $dashboard) {
            $placements = $this->placementMapper->findByDashboardId(
                dashboardId: (int) $dashboard->getId()
            );
            foreach ($placements as $placement) {
                if ($this->isOutstanding(placement: $placement, userId: $userId) === false) {
                    continue;
                }

                $items[] = [
                    'placementId'     => $placement->getId(),
                    'dashboardUuid'   => $dashboard->getUuid(),
                    'announcementKey' => $placement->getAnnouncementKey(),
                    'prompt'          => $placement->getAcknowledgementPrompt(),
                    'deadline'        => $placement->getAcknowledgementDeadline(),
                    'contentVersion'  => $placement->getAcknowledgementContentVersion(),
                ];
            }
        }

        return [
            'count' => count($items),
            'items' => $items,
        ];
    }//end getPending()

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
    public function report(string $announcementKey): array
    {
        $placements = $this->placementMapper->findByAnnouncementKey(
            announcementKey: $announcementKey
        );
        if (empty($placements) === true) {
            throw new DoesNotExistException(
                msg: 'Unknown announcement key'
            );
        }

        $blueprint      = $this->resolveBlueprintPlacement(placements: $placements);
        $contentVersion = $blueprint->getAcknowledgementContentVersion();
        $deadline       = $blueprint->getAcknowledgementDeadline();

        $audience = $this->resolveAudience(placements: $placements);

        // Map userId => acknowledgedAt for the current version.
        $ackMap   = [];
        $receipts = $this->acknowledgementMapper->findByAnnouncement(
            announcementKey: $announcementKey,
            contentVersion: $contentVersion
        );
        foreach ($receipts as $receipt) {
            $rid          = (string) $receipt->getUserId();
            $ackMap[$rid] = $receipt->getAcknowledgedAtFormatted();
        }

        $acknowledged = [];
        $pending      = [];
        $rows         = [];
        foreach ($audience as $uid) {
            if (array_key_exists($uid, $ackMap) === true) {
                $acknowledged[] = [
                    'userId'         => $uid,
                    'acknowledgedAt' => $ackMap[$uid],
                ];
                $rows[]         = [
                    'userId'         => $uid,
                    'status'         => 'acknowledged',
                    'acknowledgedAt' => $ackMap[$uid],
                ];
                continue;
            }

            $pending[] = $uid;
            $rows[]    = [
                'userId'         => $uid,
                'status'         => 'pending',
                'acknowledgedAt' => null,
            ];
        }//end foreach

        return [
            'announcementKey'   => $announcementKey,
            'contentVersion'    => $contentVersion,
            'deadline'          => $deadline,
            'overdue'           => $this->isOverdue(deadline: $deadline),
            'acknowledgedCount' => count($acknowledged),
            'pendingCount'      => count($pending),
            'pending'           => $pending,
            'acknowledged'      => $acknowledged,
            'rows'              => $rows,
        ];
    }//end report()

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
    public function resolveOwnerUserId(string $announcementKey): ?string
    {
        $placements = $this->placementMapper->findByAnnouncementKey(
            announcementKey: $announcementKey
        );
        if (empty($placements) === true) {
            return null;
        }

        $blueprint = $this->resolveBlueprintPlacement(placements: $placements);
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
     * Resolve the dashboard that a placement belongs to, or null when the
     * dashboard row has gone missing.
     *
     * @param WidgetPlacement $placement The placement.
     *
     * @return Dashboard|null The owning dashboard or null.
     */
    private function resolveDashboard(WidgetPlacement $placement): ?Dashboard
    {
        try {
            return $this->dashboardMapper->find(
                id: (int) $placement->getDashboardId()
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
    private function resolveBlueprintPlacement(array $placements): WidgetPlacement
    {
        foreach ($placements as $placement) {
            $dashboard = $this->resolveDashboard(placement: $placement);
            if ($dashboard !== null
                && $dashboard->getType() === Dashboard::TYPE_ADMIN_TEMPLATE
            ) {
                return $placement;
            }
        }

        return $placements[0];
    }//end resolveBlueprintPlacement()

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
    private function resolveAudience(array $placements): array
    {
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

        $ids = array_keys($audience);
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
    private function audienceFromGroups(array $groups): array
    {
        $audience = [];
        foreach ($groups as $gid) {
            $group = $this->groupManager->get(gid: (string) $gid);
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
    private function audienceFromRecipients(array $placements): array
    {
        $audience = [];
        foreach ($placements as $placement) {
            $dashboard = $this->resolveDashboard(placement: $placement);
            if ($dashboard === null
                || $dashboard->getType() === Dashboard::TYPE_ADMIN_TEMPLATE
            ) {
                continue;
            }

            $owner = (string) $dashboard->getUserId();
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
    private function isOverdue(?string $deadline): bool
    {
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
     * @param string $announcementKey The announcement identity.
     * @param string $userId          The acknowledging user ID.
     *
     * @return void
     */
    private function emitAcknowledgedActivity(
        string $announcementKey,
        string $userId
    ): void {
        $dashboardUuid = $announcementKey;
        $dashboardName = $announcementKey;

        $placements = $this->placementMapper->findByAnnouncementKey(
            announcementKey: $announcementKey
        );
        if (empty($placements) === false) {
            $blueprint = $this->resolveBlueprintPlacement(placements: $placements);
            $dashboard = $this->resolveDashboard(placement: $blueprint);
            if ($dashboard !== null) {
                $dashboardUuid = (string) $dashboard->getUuid();
                $dashboardName = (string) ($dashboard->getName() ?? $announcementKey);
            }
        }

        $this->activityPublisher->publish(
            type: Extension::EVENT_ACKNOWLEDGED,
            actorUserId: $userId,
            recipientUserId: $userId,
            dashboardUuid: $dashboardUuid,
            dashboardName: $dashboardName,
            dashboardLink: '',
            extraParams: ['self' => true]
        );
    }//end emitAcknowledgedActivity()
}//end class
