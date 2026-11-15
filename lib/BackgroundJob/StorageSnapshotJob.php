<?php

/**
 * StorageSnapshotJob
 *
 * Keeps one storage snapshot a day for the storage report's series (REQ-DWMS-010).
 *
 * @category BackgroundJob
 * @package  OCA\LaunchPad\BackgroundJob
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\BackgroundJob;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\StorageReport\StorageReportService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Daily: take the storage figures and keep them as that day's snapshot.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-010-storage-use-per-workspace-and-service-over-time
 */
class StorageSnapshotJob extends TimedJob {

	/**
	 * Once a day.
	 */
	public const INTERVAL_SECONDS = 86400;

	/**
	 * Constructor.
	 *
	 * @param ITimeFactory         $time    The clock.
	 * @param StorageReportService $storage Takes and keeps the snapshot.
	 * @param LoggerInterface      $logger  Logs a failed run.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.2
	 */
	public function __construct(
		ITimeFactory $time,
		private readonly StorageReportService $storage,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(time: $time);
		$this->setInterval(seconds: self::INTERVAL_SECONDS);
		$this->setTimeSensitivity(sensitivity: IJob::TIME_INSENSITIVE);
	}//end __construct()

	/**
	 * Take and keep today's snapshot.
	 *
	 * @param mixed $argument Unused.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.2
	 */
	protected function run($argument): void {
		try {
			$this->storage->recordToday();
		} catch (Throwable $exception) {
			$this->logger->warning(
				message: 'launchpad.storage_report.snapshot_failed',
				context: ['app' => Application::APP_ID, 'exception' => $exception->getMessage()]
			);
		}
	}//end run()
}//end class
