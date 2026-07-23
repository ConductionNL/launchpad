<?php

/**
 * PurgeViewsJob Test
 *
 * Unit tests for the shared analytics retention-purge job
 * (REQ-ANLT-009, REQ-TANLT-005). Verifies the job purges BOTH the
 * dashboard-views table and the tile-clicks table using the SAME
 * cutoff date in one run — the tile usage-analytics capability
 * extends this job rather than introducing a second one.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\BackgroundJob
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Unit\BackgroundJob;

use OCA\LaunchPad\BackgroundJob\PurgeViewsJob;
use OCA\LaunchPad\Db\DashboardViewMapper;
use OCA\LaunchPad\Db\TileClickMapper;
use OCA\LaunchPad\Service\AnalyticsService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

class PurgeViewsJobTest extends TestCase
{
    private ITimeFactory $time;
    private AnalyticsService $analyticsService;
    private DashboardViewMapper $viewMapper;
    private TileClickMapper $tileClickMapper;
    private LoggerInterface $logger;
    private PurgeViewsJob $job;

    protected function setUp(): void
    {
        $this->time = $this->createMock(originalClassName: ITimeFactory::class);
        $this->time->method('getTime')->willReturn(time());

        $this->analyticsService = $this->createMock(
            originalClassName: AnalyticsService::class
        );
        $this->viewMapper       = $this->createMock(
            originalClassName: DashboardViewMapper::class
        );
        $this->tileClickMapper  = $this->createMock(
            originalClassName: TileClickMapper::class
        );
        $this->logger           = $this->createMock(
            originalClassName: LoggerInterface::class
        );

        $this->job = new PurgeViewsJob(
            time: $this->time,
            analyticsService: $this->analyticsService,
            viewMapper: $this->viewMapper,
            tileClickMapper: $this->tileClickMapper,
            logger: $this->logger,
        );
    }

    /**
     * Invoke the protected TimedJob::run() via reflection.
     *
     * @return void
     */
    private function runJob(): void
    {
        $method = new ReflectionMethod(PurgeViewsJob::class, 'run');
        $method->setAccessible(true);
        $method->invoke($this->job, null);
    }

    /**
     * REQ-TANLT-005: the purge job deletes tile rows older than the
     * configured window using the SAME cutoff as the dashboard-views
     * purge, and preserves in-window rows (delegated to the mapper's
     * `deleteOlderThan()` which the test asserts is called with the
     * cutoff date).
     */
    public function testRunPurgesBothTablesWithSameCutoff(): void
    {
        $this->analyticsService->method('getPurgeCutoffDate')
            ->willReturn('2026-04-01');
        $this->analyticsService->method('getRetentionDays')
            ->willReturn(90);

        $this->viewMapper->expects($this->once())
            ->method('deleteOlderThan')
            ->with($this->equalTo('2026-04-01'))
            ->willReturn(5);

        $this->tileClickMapper->expects($this->once())
            ->method('deleteOlderThan')
            ->with($this->equalTo('2026-04-01'))
            ->willReturn(12);

        $this->runJob();
    }

    /**
     * Idempotent: a second run with nothing left to delete raises no
     * error.
     */
    public function testRunIsIdempotentWhenNothingToDelete(): void
    {
        $this->analyticsService->method('getPurgeCutoffDate')
            ->willReturn('2026-04-01');
        $this->analyticsService->method('getRetentionDays')
            ->willReturn(90);

        $this->viewMapper->method('deleteOlderThan')->willReturn(0);
        $this->tileClickMapper->method('deleteOlderThan')->willReturn(0);

        $this->runJob();
        $this->runJob();

        $this->addToAssertionCount(1);
    }

    /**
     * Logs never expose row-level or user-attributable data — only
     * the aggregate counts and the cutoff date (REQ-ANLT-009 "Purge
     * logs execution").
     */
    public function testRunLogsAggregateCountsOnly(): void
    {
        $this->analyticsService->method('getPurgeCutoffDate')
            ->willReturn('2026-04-01');
        $this->analyticsService->method('getRetentionDays')
            ->willReturn(90);
        $this->viewMapper->method('deleteOlderThan')->willReturn(3);
        $this->tileClickMapper->method('deleteOlderThan')->willReturn(7);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('3'),
                $this->callback(static function (array $context): bool {
                    return $context['viewRows'] === 3
                        && $context['tileRows'] === 7
                        && $context['cutoff'] === '2026-04-01';
                })
            );

        $this->runJob();
    }
}
