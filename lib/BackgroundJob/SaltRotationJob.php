<?php

/**
 * SaltRotationJob
 *
 * Daily background job that rotates the unique-viewer dedup salt
 * (REQ-ANLT-003 design D2). Generates a fresh 32-byte random salt
 * and overwrites the previous value with no history kept, ensuring
 * that yesterday's viewer hashes can no longer be correlated with
 * any user identity from the analytics database alone.
 *
 * The job is registered via
 * {@see \OCA\LaunchPad\AppInfo\Application::register()} and runs every
 * 24 hours. The lazy fallback in
 * {@see \OCA\LaunchPad\Service\UniqueViewerDedup::getSaltForDate()}
 * also rotates the salt when its persisted date marker is stale —
 * this job exists primarily so the rotation timestamp itself
 * happens close to UTC midnight rather than on the first request
 * after midnight.
 *
 * @category  BackgroundJob
 * @package   OCA\LaunchPad\BackgroundJob
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\LaunchPad\BackgroundJob;

use OCA\LaunchPad\Service\UniqueViewerDedup;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Daily salt rotation for the unique-viewer dedup layer.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter) — $argument required by TimedJob interface.
 * @SuppressWarnings(PHPMD.StaticAccess)          — UniqueViewerDedup uses a static factory method.
 * @spec                                          openspec/specs/dashboard-view-analytics/spec.md
 */
class SaltRotationJob extends TimedJob
{
    /**
     * Constructor.
     *
     * @param ITimeFactory      $time   Time factory.
     * @param UniqueViewerDedup $dedup  Dedup service whose salt is
     *                                  rotated.
     * @param LoggerInterface   $logger PSR logger.
     */
    public function __construct(
        ITimeFactory $time,
        private readonly UniqueViewerDedup $dedup,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct(time: $time);
        $this->setInterval(seconds: 86400);
    }//end __construct()

    /**
     * Run the job — overwrite the persisted daily salt with a fresh
     * 32-byte random value (no history kept).
     *
     * @param mixed $argument Required by the base class; unused.
     *
     * @return void
     *
     * @spec openspec/specs/dashboard-view-analytics/spec.md
     */
    protected function run($argument): void
    {
        $today = UniqueViewerDedup::utcDateFor();
        $this->dedup->rotateSalt(viewBucketDate: $today);

        $this->logger->info(
            message: 'launchpad analytics salt rotated for '.$today,
            context: ['date' => $today]
        );
    }//end run()
}//end class
