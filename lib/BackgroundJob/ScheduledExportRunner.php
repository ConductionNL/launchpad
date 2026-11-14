<?php

/**
 * ScheduledExportRunner
 *
 * The scheduled-exports background runner (tasks.md task 8, REQ-SCH-002):
 * every minute it fires each enabled export whose `nextRunAt` has passed,
 * independently of any browser session, and then attempts the deliveries
 * whose retry is due (REQ-SCH-006). Fires run one after the other.
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
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\BackgroundJob;

use OCA\LaunchPad\Service\ScheduledExport\ExportFire;
use OCA\LaunchPad\Service\ScheduledExport\FireTrigger;
use OCA\LaunchPad\Service\ScheduledExport\ScheduledExportService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Fires due scheduled exports and due delivery retries.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter) — $argument required by TimedJob interface.
 * @spec                                          openspec/changes/scheduled-exports/tasks.md#task-8
 */
class ScheduledExportRunner extends TimedJob {

	/**
	 * Constructor.
	 *
	 * @param ITimeFactory           $time    The clock, also the parent's next-run gate.
	 * @param ScheduledExportService $exports Finds the due exports.
	 * @param ExportFire             $fire    Fires one export.
	 * @param LoggerInterface        $logger  PSR logger.
	 */
	public function __construct(
		ITimeFactory $time,
		private readonly ScheduledExportService $exports,
		private readonly ExportFire $fire,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(time: $time);
		$this->setInterval(seconds: 60);
	}//end __construct()

	/**
	 * Fire every due export, then the due retries; one export's failure does not stop the others.
	 *
	 * @param mixed $argument Required by the base class; unused.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-002-background-runner-fires-schedules-without-an-active-browser-session
	 */
	protected function run($argument): void {
		foreach ($this->exports->due(now: $this->time->now()) as $export) {
			try {
				$this->fire->fire(export: $export, by: FireTrigger::schedule());
			} catch (Throwable $e) {
				$this->logger->error(
					message: 'launchpad scheduled export ' . (string)($export['id'] ?? '') . ' failed: ' . $e->getMessage(),
					context: ['app' => 'launchpad', 'exception' => $e]
				);
			}
		}

		try {
			$this->fire->retryDue();
		} catch (Throwable $e) {
			$this->logger->error(
				message: 'launchpad scheduled export retries failed: ' . $e->getMessage(),
				context: ['app' => 'launchpad', 'exception' => $e]
			);
		}
	}//end run()
}//end class
