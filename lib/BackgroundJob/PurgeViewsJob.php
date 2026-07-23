<?php

/**
 * PurgeViewsJob
 *
 * Daily background job that purges aggregate view-count rows older
 * than the configured retention window (REQ-ANLT-009). Default
 * window is 365 days; admin override via
 * `launchpad.analytics_retention_days` is clamped to `[30, 3650]`.
 *
 * Extended by the tile usage-analytics capability (REQ-TANLT-005) to
 * also purge `oc_launchpad_tile_clicks` rows older than the SAME
 * cutoff in the same run — no second purge job is introduced, per the
 * "reuse, don't reinvent" contract for that capability.
 *
 * Logging is intentionally aggregate-only: row count + cutoff date
 * — never any user-attributable identifiers (REQ-ANLT-009 scenario
 * "Purge logs execution"). The job is registered via
 * {@see \OCA\LaunchPad\AppInfo\Application::register()}.
 *
 * @category  BackgroundJob
 * @package   OCA\LaunchPad\BackgroundJob
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

namespace OCA\LaunchPad\BackgroundJob;

use OCA\LaunchPad\Db\DashboardViewMapper;
use OCA\LaunchPad\Db\TileClickMapper;
use OCA\LaunchPad\Service\AnalyticsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Daily retention purge for the analytics aggregate table.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter) — $argument required by TimedJob interface.
 * @spec                                          openspec/specs/dashboard-view-analytics/spec.md
 */
class PurgeViewsJob extends TimedJob
{
    /**
     * Constructor.
     *
     * @param ITimeFactory        $time             Time factory used by
     *                                              the parent
     *                                              `TimedJob` to gate
     *                                              the next-run
     *                                              decision.
     * @param AnalyticsService    $analyticsService Analytics service
     *                                              (cutoff date + log
     *                                              context).
     * @param DashboardViewMapper $viewMapper       Aggregate-row
     *                                              mapper.
     * @param TileClickMapper     $tileClickMapper  Tile-click
     *                                              aggregate-row
     *                                              mapper — reuses
     *                                              the same cutoff
     *                                              date (REQ-TANLT-005).
     * @param LoggerInterface     $logger           PSR logger.
     */
    public function __construct(
        ITimeFactory $time,
        private readonly AnalyticsService $analyticsService,
        private readonly DashboardViewMapper $viewMapper,
        private readonly TileClickMapper $tileClickMapper,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct(time: $time);
        $this->setInterval(seconds: 86400);
    }//end __construct()

    /**
     * Run the job — delete every aggregate row strictly older than
     * the cutoff date, in BOTH the dashboard-views table and the
     * tile-clicks table (REQ-TANLT-005 — same cutoff, same run, no
     * second job).
     *
     * @param mixed $argument Required by the base class; unused.
     *
     * @return void
     *
     * @spec openspec/specs/dashboard-view-analytics/spec.md
     * @spec openspec/changes/tile-usage-analytics/specs/dashboard-view-analytics/spec.md
     */
    protected function run($argument): void
    {
        $cutoff        = $this->analyticsService->getPurgeCutoffDate();
        $deletedViews  = $this->viewMapper->deleteOlderThan(beforeDate: $cutoff);
        $deletedClicks = $this->tileClickMapper->deleteOlderThan(beforeDate: $cutoff);

        $this->logger->info(
            message: 'launchpad analytics purge: deleted '.$deletedViews.' view rows and '
                .$deletedClicks.' tile-click rows older than '.$cutoff,
            context: [
                'viewRows'   => $deletedViews,
                'tileRows'   => $deletedClicks,
                'cutoff'     => $cutoff,
                'retention'  => $this->analyticsService->getRetentionDays(),
            ]
        );
    }//end run()
}//end class
