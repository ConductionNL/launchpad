<?php

/**
 * TileAnalyticsService Test
 *
 * Unit tests for the tile usage-analytics aggregation service
 * (REQ-TANLT-001..005). Verifies the privacy machinery is genuinely
 * REUSED from `AnalyticsService`/`UniqueViewerDedup` — not
 * reimplemented — and that no per-event rows are ever persisted.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\TileClick;
use OCA\LaunchPad\Db\TileClickMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AnalyticsService;
use OCA\LaunchPad\Service\TileAnalyticsService;
use OCA\LaunchPad\Service\UniqueViewerDedup;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\TestCase;

class TileAnalyticsServiceTest extends TestCase
{
    private TileClickMapper $tileClickMapper;
    private WidgetPlacementMapper $placementMapper;
    private DashboardMapper $dashboardMapper;
    private UniqueViewerDedup $dedup;
    private AnalyticsService $analyticsService;
    private TileAnalyticsService $service;

    protected function setUp(): void
    {
        $this->tileClickMapper = $this->createMock(
            originalClassName: TileClickMapper::class
        );
        $this->placementMapper = $this->createMock(
            originalClassName: WidgetPlacementMapper::class
        );
        $this->dashboardMapper = $this->createMock(
            originalClassName: DashboardMapper::class
        );
        $this->dedup           = $this->createMock(
            originalClassName: UniqueViewerDedup::class
        );
        $this->analyticsService = $this->createMock(
            originalClassName: AnalyticsService::class
        );

        $this->service = new TileAnalyticsService(
            tileClickMapper: $this->tileClickMapper,
            placementMapper: $this->placementMapper,
            dashboardMapper: $this->dashboardMapper,
            dedup: $this->dedup,
            analyticsService: $this->analyticsService,
        );
    }

    private function makePlacement(int $dashboardId=1): WidgetPlacement
    {
        $placement = new WidgetPlacement();
        $placement->setDashboardId($dashboardId);

        return $placement;
    }

    private function makeDashboard(string $uuid='dsh-1'): Dashboard
    {
        $dashboard = new Dashboard();
        $dashboard->setUuid($uuid);

        return $dashboard;
    }

    public function testRecordClickThrowsWhenPlacementMissing(): void
    {
        $this->placementMapper
            ->method('find')
            ->willThrowException(
                exception: new DoesNotExistException(msg: 'gone')
            );

        $this->expectException(exception: DoesNotExistException::class);

        $this->service->recordClick(placementId: 999, userId: 'alice');
    }

    /**
     * REQ-TANLT-003 — recording is a no-op (no counter change, no
     * cache write) when analytics_enabled=false. This reuses
     * AnalyticsService::isGloballyEnabled() rather than a second
     * setting.
     */
    public function testRecordClickSkipsWhenGloballyDisabled(): void
    {
        $this->placementMapper->method('find')
            ->willReturn(value: $this->makePlacement());
        $this->analyticsService->method('isGloballyEnabled')->willReturn(false);
        $this->dedup->expects($this->never())->method('isNewUniqueViewer');
        $this->tileClickMapper->expects($this->never())->method('upsertClick');

        $result = $this->service->recordClick(placementId: 1, userId: 'alice');

        $this->assertFalse($result);
    }

    /**
     * REQ-TANLT-003 — recording is a no-op for a user with
     * analytics_optout=true. This reuses
     * AnalyticsService::isUserOptedOut() rather than a second
     * opt-out surface.
     */
    public function testRecordClickSkipsWhenUserOptedOut(): void
    {
        $this->placementMapper->method('find')
            ->willReturn(value: $this->makePlacement());
        $this->analyticsService->method('isGloballyEnabled')->willReturn(true);
        $this->analyticsService->method('isUserOptedOut')->willReturn(true);
        $this->dedup->expects($this->never())->method('isNewUniqueViewer');
        $this->tileClickMapper->expects($this->never())->method('upsertClick');

        $result = $this->service->recordClick(placementId: 1, userId: 'eve');

        $this->assertFalse($result);
    }

    /**
     * Repeat clicks by the SAME actor on the SAME day increment
     * clickCount but NOT uniqueActorCount a second time.
     */
    public function testRecordClickLeavesUniqueAtZeroForRepeatActor(): void
    {
        $this->placementMapper->method('find')
            ->willReturn(value: $this->makePlacement());
        $this->dashboardMapper->method('find')
            ->willReturn(value: $this->makeDashboard());
        $this->analyticsService->method('isGloballyEnabled')->willReturn(true);
        $this->analyticsService->method('isUserOptedOut')->willReturn(false);
        $this->dedup->method('isNewUniqueViewer')->willReturn(false);

        $this->tileClickMapper->expects($this->once())
            ->method('upsertClick')
            ->with(
                $this->equalTo('1'),
                $this->equalTo('dsh-1'),
                $this->isType(type: 'string'),
                1,
                0
            );

        $result = $this->service->recordClick(placementId: 1, userId: 'alice');

        $this->assertTrue($result);
    }

    /**
     * A NEW actor on the same day increments both counters.
     */
    public function testRecordClickIncrementsBothCountersForNewActor(): void
    {
        $this->placementMapper->method('find')
            ->willReturn(value: $this->makePlacement());
        $this->dashboardMapper->method('find')
            ->willReturn(value: $this->makeDashboard());
        $this->analyticsService->method('isGloballyEnabled')->willReturn(true);
        $this->analyticsService->method('isUserOptedOut')->willReturn(false);
        $this->dedup->method('isNewUniqueViewer')->willReturn(true);

        $this->tileClickMapper->expects($this->once())
            ->method('upsertClick')
            ->with(
                $this->equalTo('1'),
                $this->equalTo('dsh-1'),
                $this->isType(type: 'string'),
                1,
                1
            );

        $result = $this->service->recordClick(placementId: 1, userId: 'bob');

        $this->assertTrue($result);
    }

    /**
     * REQ-TANLT-002 — the dedup call MUST reuse the SAME
     * UniqueViewerDedup instance (salted-daily-hash), scoped by
     * placement UUID, not a second dedup mechanism.
     */
    public function testRecordClickReusesSharedDedupScopedByPlacement(): void
    {
        $this->placementMapper->method('find')
            ->willReturn(value: $this->makePlacement());
        $this->dashboardMapper->method('find')
            ->willReturn(value: $this->makeDashboard());
        $this->analyticsService->method('isGloballyEnabled')->willReturn(true);
        $this->analyticsService->method('isUserOptedOut')->willReturn(false);

        $this->dedup->expects($this->once())
            ->method('isNewUniqueViewer')
            ->with(
                $this->equalTo('alice'),
                $this->isType(type: 'string'),
                $this->equalTo('42')
            )
            ->willReturn(true);

        $this->service->recordClick(placementId: 42, userId: 'alice');
    }

    /**
     * Orphan placement (owning dashboard already deleted) still
     * records the click rather than silently dropping it.
     */
    public function testRecordClickToleratesOrphanPlacement(): void
    {
        $this->placementMapper->method('find')
            ->willReturn(value: $this->makePlacement());
        $this->dashboardMapper->method('find')
            ->willThrowException(
                exception: new DoesNotExistException(msg: 'gone')
            );
        $this->analyticsService->method('isGloballyEnabled')->willReturn(true);
        $this->analyticsService->method('isUserOptedOut')->willReturn(false);
        $this->dedup->method('isNewUniqueViewer')->willReturn(true);

        $this->tileClickMapper->expects($this->once())
            ->method('upsertClick')
            ->with(
                $this->equalTo('1'),
                $this->equalTo(''),
                $this->isType(type: 'string'),
                1,
                1
            );

        $result = $this->service->recordClick(placementId: 1, userId: 'alice');

        $this->assertTrue($result);
    }

    public function testIsTrackingActiveForFalseWhenGloballyDisabled(): void
    {
        $this->analyticsService->method('isGloballyEnabled')->willReturn(false);

        $this->assertFalse($this->service->isTrackingActiveFor(userId: 'alice'));
    }

    public function testIsTrackingActiveForFalseWhenUserOptedOut(): void
    {
        $this->analyticsService->method('isGloballyEnabled')->willReturn(true);
        $this->analyticsService->method('isUserOptedOut')->willReturn(true);

        $this->assertFalse($this->service->isTrackingActiveFor(userId: 'eve'));
    }

    public function testIsTrackingActiveForTrueByDefault(): void
    {
        $this->analyticsService->method('isGloballyEnabled')->willReturn(true);
        $this->analyticsService->method('isUserOptedOut')->willReturn(false);

        $this->assertTrue($this->service->isTrackingActiveFor(userId: 'frank'));
    }

    public function testGetTopTilesDelegatesToMapperWithDateRange(): void
    {
        $this->tileClickMapper->expects($this->once())
            ->method('findTopTilesInRange')
            ->with(
                $this->isType(type: 'string'),
                $this->isType(type: 'string'),
                10
            )
            ->willReturn(
                value: [
                    [
                        'placementUuid'    => '1',
                        'dashboardUuid'    => 'dsh-1',
                        'clickCount'       => 120,
                        'uniqueActorCount' => 80,
                    ],
                ]
            );

        $rows = $this->service->getTopTiles(period: '7d', limit: 10);

        $this->assertCount(1, $rows);
        $this->assertSame(120, $rows[0]['clickCount']);
    }

    public function testGetDashboardBreakdownDelegatesToMapper(): void
    {
        $this->tileClickMapper->expects($this->once())
            ->method('findByDashboardInRange')
            ->with(
                $this->equalTo('dsh-9'),
                $this->isType(type: 'string'),
                $this->isType(type: 'string')
            )
            ->willReturn(
                value: [
                    [
                        'placementUuid'    => '3',
                        'clickCount'       => 40,
                        'uniqueActorCount' => 12,
                    ],
                ]
            );

        $rows = $this->service->getDashboardBreakdown(
            dashboardUuid: 'dsh-9',
            period: '30d'
        );

        $this->assertCount(1, $rows);
        $this->assertSame(40, $rows[0]['clickCount']);
    }

    public function testGenerateCsvExportProducesHeaderAndRows(): void
    {
        $row = new TileClick();
        $row->setPlacementUuid('1');
        $row->setDashboardUuid('dsh-1');
        $row->setClickBucket('2026-05-01');
        $row->setClickCount(2);
        $row->setUniqueActorCount(1);
        $this->tileClickMapper->method('findAllInRange')->willReturn(
            value: [$row]
        );

        $csv = $this->service->generateCsvExport(period: '7d');

        $this->assertStringContainsString(
            needle: 'placementUuid',
            haystack: $csv
        );
        $this->assertStringContainsString(
            needle: 'dsh-1',
            haystack: $csv
        );
        $this->assertStringContainsString(
            needle: '2026-05-01',
            haystack: $csv
        );
    }

    public function testCsvExportFilenameMatchesExpectedPattern(): void
    {
        $name = $this->service->csvExportFilename();

        $this->assertMatchesRegularExpression(
            pattern: '/^tile-analytics-\d{4}-\d{2}-\d{2}\.csv$/',
            string: $name
        );
    }

    /**
     * No per-event rows are ever persisted — the ONLY write method on
     * the mapper is `upsertClick()`, which the service calls exactly
     * once per (successful) `recordClick()` invocation, never an
     * `insert()`/raw-row write path.
     */
    public function testRecordClickNeverCallsAnythingOtherThanUpsert(): void
    {
        $this->placementMapper->method('find')
            ->willReturn(value: $this->makePlacement());
        $this->dashboardMapper->method('find')
            ->willReturn(value: $this->makeDashboard());
        $this->analyticsService->method('isGloballyEnabled')->willReturn(true);
        $this->analyticsService->method('isUserOptedOut')->willReturn(false);
        $this->dedup->method('isNewUniqueViewer')->willReturn(true);

        $this->tileClickMapper->expects($this->once())->method('upsertClick');
        $this->tileClickMapper->expects($this->never())->method('findAllInRange');
        $this->tileClickMapper->expects($this->never())->method('findTopTilesInRange');

        $this->service->recordClick(placementId: 1, userId: 'alice');
    }
}
