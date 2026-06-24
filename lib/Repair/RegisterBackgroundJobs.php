<?php

/**
 * RegisterBackgroundJobs Repair Step
 *
 * Ensures the LaunchPad background jobs (dashboard view-analytics purge, salt
 * rotation and external-feed refresh) are registered in `oc_jobs`. Runs once on
 * install and on every upgrade — NOT on every request — so the app boot path no
 * longer issues a `JobList::has()` SELECT against `oc_jobs` (and an INSERT on a
 * cold table) on each web request. Registering jobs per-request both churned
 * `oc_jobs` and triggered Nextcloud's "dirty table reads" replica-consistency
 * diagnostic (a write-then-read of `oc_jobs` in the same request).
 *
 * `IJobList::add()` is idempotent — a no-op when the job is already registered —
 * so re-running this step is safe.
 *
 * @category Repair
 * @package  OCA\LaunchPad\Repair
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Repair;

use OCA\LaunchPad\BackgroundJob\PurgeViewsJob;
use OCA\LaunchPad\BackgroundJob\SaltRotationJob;
use OCA\LaunchPad\Job\FeedRefreshJob;
use OCP\BackgroundJob\IJobList;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

/**
 * Register the LaunchPad background jobs once on install/upgrade.
 *
 * @spec openspec/specs/dashboard-view-analytics/spec.md
 */
class RegisterBackgroundJobs implements IRepairStep
{
    /**
     * Constructor.
     *
     * @param IJobList        $jobList The Nextcloud background job list.
     * @param LoggerInterface $logger  PSR-3 logger.
     */
    public function __construct(
        private readonly IJobList $jobList,
        private readonly LoggerInterface $logger,
    ) {
    }//end __construct()

    /**
     * Return the human-readable name of this repair step.
     *
     * @return string
     *
     * @spec openspec/specs/dashboard-view-analytics/spec.md
     */
    public function getName(): string
    {
        return 'Register LaunchPad background jobs';
    }//end getName()

    /**
     * Register each LaunchPad background job (idempotent).
     *
     * @param IOutput $output Migration output stream.
     *
     * @return void
     *
     * @spec openspec/specs/dashboard-view-analytics/spec.md
     */
    public function run(IOutput $output): void
    {
        $jobs = [
            PurgeViewsJob::class,
            SaltRotationJob::class,
            FeedRefreshJob::class,
        ];

        foreach ($jobs as $job) {
            $this->jobList->add(job: $job);
            $output->info(message: 'Ensured LaunchPad background job is registered: '.$job);
        }

        $this->logger->info(
            message: 'RegisterBackgroundJobs: ensured LaunchPad background jobs are registered.'
        );
    }//end run()
}//end class
