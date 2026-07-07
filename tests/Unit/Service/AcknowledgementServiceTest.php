<?php

/**
 * AcknowledgementServiceTest
 *
 * Unit tests for AcknowledgementService covering the dashboard-acknowledgements
 * capability — idempotent receipt writes (REQ-ACK-003), outstanding /
 * re-acknowledge-on-change resolution (REQ-ACK-002 / REQ-ACK-005), the
 * audience-scoped read-receipt report resolved live via IGroupManager
 * (REQ-ACK-004), and single-shot activity emission (REQ-ACK-006).
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

use OCA\LaunchPad\Activity\ActivityPublisher;
use OCA\LaunchPad\Activity\Extension;
use OCA\LaunchPad\Db\Acknowledgement;
use OCA\LaunchPad\Db\AcknowledgementMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AcknowledgementService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception as DbException;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AcknowledgementService.
 */
class AcknowledgementServiceTest extends TestCase
{
    private AcknowledgementMapper&MockObject $ackMapper;
    private WidgetPlacementMapper&MockObject $placementMapper;
    private DashboardMapper&MockObject $dashboardMapper;
    private IGroupManager&MockObject $groupManager;
    private ActivityPublisher&MockObject $activityPublisher;
    private AcknowledgementService $service;

    protected function setUp(): void
    {
        $this->ackMapper         = $this->createMock(originalClassName: AcknowledgementMapper::class);
        $this->placementMapper   = $this->createMock(originalClassName: WidgetPlacementMapper::class);
        $this->dashboardMapper   = $this->createMock(originalClassName: DashboardMapper::class);
        $this->groupManager      = $this->createMock(originalClassName: IGroupManager::class);
        $this->activityPublisher = $this->createMock(originalClassName: ActivityPublisher::class);

        $this->service = new AcknowledgementService(
            acknowledgementMapper: $this->ackMapper,
            placementMapper: $this->placementMapper,
            dashboardMapper: $this->dashboardMapper,
            groupManager: $this->groupManager,
            activityPublisher: $this->activityPublisher,
        );
    }

    private function makeReceipt(string $key, string $userId, int $version, string $at='2026-07-07 09:00:00'): Acknowledgement
    {
        $receipt = new Acknowledgement();
        // phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
        $receipt->setAnnouncementKey($key);
        $receipt->setUserId($userId);
        $receipt->setContentVersion($version);
        $receipt->setAcknowledgedAt(new \DateTime($at));
        // phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
        return $receipt;
    }

    private function makePlacement(string $key, int $dashboardId, int $version=1, int $reack=0): WidgetPlacement
    {
        $placement = new WidgetPlacement();
        // phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
        $placement->setId($dashboardId * 10);
        $placement->setDashboardId($dashboardId);
        $placement->setWidgetId('launchpad_header');
        $placement->setRequiresAcknowledgement(1);
        $placement->setAnnouncementKey($key);
        $placement->setAcknowledgementContentVersion($version);
        $placement->setReacknowledgeOnChange($reack);
        // phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
        return $placement;
    }

    private function makeUser(string $uid): IUser&MockObject
    {
        $user = $this->createMock(originalClassName: IUser::class);
        $user->method('getUID')->willReturn($uid);
        return $user;
    }

    /**
     * REQ-ACK-003: the first acknowledgement writes exactly one receipt and
     * emits exactly one activity event.
     *
     * @return void
     */
    public function testFirstAcknowledgeRecordsAndEmitsActivity(): void
    {
        $this->ackMapper->method('findOneFor')->willReturn(null);
        $this->ackMapper->expects($this->once())
            ->method('record')
            ->with('ak-1', 'alice', 1)
            ->willReturn($this->makeReceipt('ak-1', 'alice', 1));

        // Activity emission resolves the announcement's dashboard.
        $this->placementMapper->method('findByAnnouncementKey')->willReturn([]);
        $this->activityPublisher->expects($this->once())
            ->method('publish')
            ->with(Extension::EVENT_ACKNOWLEDGED, 'alice', 'alice');

        $receipt = $this->service->acknowledge(
            announcementKey: 'ak-1',
            userId: 'alice',
            contentVersion: 1
        );

        $this->assertSame(expected: 'alice', actual: $receipt->getUserId());
    }

    /**
     * REQ-ACK-003: a repeated acknowledgement is idempotent — no second
     * insert, no second activity event, original timestamp preserved.
     *
     * @return void
     */
    public function testRepeatedAcknowledgeIsIdempotent(): void
    {
        $existing = $this->makeReceipt('ak-1', 'alice', 1, '2026-07-01 08:00:00');
        $this->ackMapper->method('findOneFor')->willReturn($existing);
        $this->ackMapper->expects($this->never())->method('record');
        $this->activityPublisher->expects($this->never())->method('publish');

        $receipt = $this->service->acknowledge(
            announcementKey: 'ak-1',
            userId: 'alice',
            contentVersion: 1
        );

        $this->assertSame(
            expected: '2026-07-01 08:00:00',
            actual: $receipt->getAcknowledgedAtFormatted()
        );
    }

    /**
     * REQ-ACK-003: a race that hits the unique index is swallowed — the
     * winning row is returned and no duplicate activity is emitted.
     *
     * @return void
     */
    public function testAcknowledgeRaceReturnsWinnerWithoutActivity(): void
    {
        $winner = $this->makeReceipt('ak-1', 'alice', 1);
        // First lookup: none; after the failed insert: the winner.
        $this->ackMapper->method('findOneFor')
            ->willReturnOnConsecutiveCalls(null, $winner);

        $dbException = $this->createMock(originalClassName: DbException::class);
        $dbException->method('getReason')
            ->willReturn(DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION);
        $this->ackMapper->method('record')->willThrowException($dbException);

        $this->activityPublisher->expects($this->never())->method('publish');

        $receipt = $this->service->acknowledge(
            announcementKey: 'ak-1',
            userId: 'alice',
            contentVersion: 1
        );

        $this->assertSame(expected: 'alice', actual: $receipt->getUserId());
    }

    /**
     * REQ-ACK-005: with reacknowledgeOnChange = 1, an item is outstanding
     * unless the user holds a receipt for the CURRENT version.
     *
     * @return void
     */
    public function testIsOutstandingReackOnChangeChecksCurrentVersion(): void
    {
        $placement = $this->makePlacement(key: 'ak-1', dashboardId: 5, version: 2, reack: 1);

        // No receipt at version 2 → outstanding.
        $this->ackMapper->method('existsFor')
            ->with('ak-1', 'alice', 2)
            ->willReturn(false);

        $this->assertTrue(
            condition: $this->service->isOutstanding(placement: $placement, userId: 'alice')
        );
    }

    /**
     * REQ-ACK-005: with reacknowledgeOnChange = 0, ANY prior receipt for the
     * announcement satisfies the requirement even after a version bump.
     *
     * @return void
     */
    public function testIsOutstandingNoReackAnyReceiptSatisfies(): void
    {
        $placement = $this->makePlacement(key: 'ak-1', dashboardId: 5, version: 2, reack: 0);

        $this->ackMapper->method('findByUserForAnnouncement')
            ->with('ak-1', 'alice')
            ->willReturn([$this->makeReceipt('ak-1', 'alice', 1)]);

        $this->assertFalse(
            condition: $this->service->isOutstanding(placement: $placement, userId: 'alice')
        );
    }

    /**
     * REQ-ACK-002: a placement with requiresAcknowledgement = 0 is never
     * outstanding (no regression to non-acknowledgement placements).
     *
     * @return void
     */
    public function testIsOutstandingFalseWhenRequirementOff(): void
    {
        $placement = new WidgetPlacement();
        // phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
        $placement->setRequiresAcknowledgement(0);
        // phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters

        $this->assertFalse(
            condition: $this->service->isOutstanding(placement: $placement, userId: 'alice')
        );
    }

    /**
     * REQ-ACK-004: the report separates acknowledged from pending against
     * the live group audience resolved via IGroupManager.
     *
     * @return void
     */
    public function testReportSeparatesAcknowledgedFromPending(): void
    {
        // Template dashboard (id 1) routing to group "sociaal-domein".
        $template = new Dashboard();
        // phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
        $template->setId(1);
        $template->setUuid('tmpl-uuid');
        $template->setName('HR 2026');
        $template->setType(Dashboard::TYPE_ADMIN_TEMPLATE);
        $template->setTargetGroups(json_encode(['sociaal-domein']));
        // phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters

        $blueprint = $this->makePlacement(key: 'ak-1', dashboardId: 1, version: 1);

        $this->placementMapper->method('findByAnnouncementKey')
            ->with('ak-1')->willReturn([$blueprint]);
        $this->dashboardMapper->method('find')->with(1)->willReturn($template);

        // Live audience {alice, bob, carol}.
        $group = $this->createMock(originalClassName: IGroup::class);
        $group->method('getUsers')->willReturn([
            $this->makeUser('alice'),
            $this->makeUser('bob'),
            $this->makeUser('carol'),
        ]);
        $this->groupManager->method('get')->with('sociaal-domein')->willReturn($group);

        // alice + carol acknowledged version 1.
        $this->ackMapper->method('findByAnnouncement')
            ->with('ak-1', 1)
            ->willReturn([
                $this->makeReceipt('ak-1', 'alice', 1),
                $this->makeReceipt('ak-1', 'carol', 1),
            ]);

        $report = $this->service->report(announcementKey: 'ak-1');

        $this->assertSame(expected: 2, actual: $report['acknowledgedCount']);
        $this->assertSame(expected: 1, actual: $report['pendingCount']);
        $this->assertSame(expected: ['bob'], actual: $report['pending']);
        $this->assertCount(expectedCount: 3, haystack: $report['rows']);
    }

    /**
     * REQ-ACK-004: report throws for an unknown announcement key.
     *
     * @return void
     */
    public function testReportThrowsForUnknownAnnouncement(): void
    {
        $this->placementMapper->method('findByAnnouncementKey')->willReturn([]);

        $this->expectException(exception: DoesNotExistException::class);
        $this->service->report(announcementKey: 'nope');
    }
}//end class
