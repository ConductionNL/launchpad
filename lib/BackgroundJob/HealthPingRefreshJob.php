<?php

/**
 * HealthPingRefreshJob
 *
 * Periodic background job that refreshes due, ping-enabled placements'
 * cached health badge so viewers do not pay upstream ping latency on page
 * load (REQ-HPING-003 "Background refresh of due entries"). Delegates all
 * discovery/classification/caching to {@see HealthPingService}; this job
 * is a thin `TimedJob` shell, mirroring
 * {@see \OCA\LaunchPad\BackgroundJob\OrphanedDataCleanupJob}'s shape.
 *
 * Registered in `appinfo/info.xml` under `<background-jobs>` and
 * installed via `OCA\LaunchPad\Repair\RegisterBackgroundJobs`.
 *
 * @category  BackgroundJob
 * @package   OCA\LaunchPad\BackgroundJob
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

namespace OCA\LaunchPad\BackgroundJob;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\HealthPingService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Refreshes due, ping-enabled placements' cached health badge every tick.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter) — $argument required by TimedJob interface.
 * @spec                                          openspec/specs/service-health-ping/spec.md
 */
class HealthPingRefreshJob extends TimedJob
{

    /**
     * Run interval in seconds — matches the minimum permitted per-tile
     * interval ({@see HealthPingService::MIN_INTERVAL_SECONDS}) so no
     * tile's configured interval is ever starved by the job cadence
     * itself; `refreshDuePlacements()` still only touches entries whose
     * OWN interval has actually elapsed.
     *
     * @var integer
     */
    public const INTERVAL_SECONDS = 15;

    /**
     * Constructor.
     *
     * @param ITimeFactory      $time              Time factory (parent requirement).
     * @param HealthPingService $healthPingService The service performing the actual refresh.
     * @param LoggerInterface   $logger            PSR-3 logger.
     */
    public function __construct(
        ITimeFactory $time,
        private readonly HealthPingService $healthPingService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct(time: $time);
        $this->setInterval(seconds: self::INTERVAL_SECONDS);
        $this->setTimeSensitivity(sensitivity: IJob::TIME_INSENSITIVE);
    }//end __construct()

    /**
     * Run one refresh tick. Never throws — a single broken placement is
     * isolated inside {@see HealthPingService::refreshDuePlacements()};
     * this wrapper additionally guards against any unexpected failure in
     * the service call itself so the scheduler's job list is never
     * poisoned.
     *
     * @param mixed $argument Ignored — the job carries no arguments.
     *
     * @return void
     *
     * @spec openspec/specs/service-health-ping/spec.md
     */
    protected function run($argument): void
    {
        try {
            $refreshed = $this->healthPingService->refreshDuePlacements();
        } catch (Throwable $exception) {
            $this->logger->warning(
                message: 'launchpad.healthping.job_failed',
                context: ['app' => Application::APP_ID, 'exception' => $exception->getMessage()]
            );
            return;
        }

        $this->logger->debug(
            message: sprintf('launchpad.healthping.job_run refreshed=%d', $refreshed)
        );
    }//end run()
}//end class
