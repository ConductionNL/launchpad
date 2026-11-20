<?php

/**
 * ScheduledExportJanitor
 *
 * Hourly retention of scheduled exports (tasks.md task 9, REQ-SCH-007):
 * runs {@see RetentionJanitor::sweep()}.
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

use OCA\LaunchPad\Service\ScheduledExport\RetentionJanitor;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Purges expired export artefacts and run-rows.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter) — $argument required by TimedJob interface.
 * @spec                                          openspec/changes/scheduled-exports/tasks.md#task-9
 */
class ScheduledExportJanitor extends TimedJob {

	/**
	 * Constructor.
	 *
	 * @param ITimeFactory     $time    The parent's next-run gate.
	 * @param RetentionJanitor $janitor The retention sweep.
	 * @param LoggerInterface  $logger  PSR logger.
	 */
	public function __construct(
		ITimeFactory $time,
		private readonly RetentionJanitor $janitor,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(time: $time);
		$this->setInterval(seconds: 3600);
	}//end __construct()

	/**
	 * One retention sweep.
	 *
	 * @param mixed $argument Required by the base class; unused.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-007-retention-policy-enforced-by-janitor
	 */
	protected function run($argument): void {
		$result = $this->janitor->sweep();
		$this->logger->info(
			message: 'launchpad scheduled export retention: purged artefacts of ' . $result['artefactsPurged']
				. ' runs, deleted ' . $result['runsDeleted'] . ' runs',
			context: ['app' => 'launchpad'] + $result
		);
	}//end run()
}//end class
