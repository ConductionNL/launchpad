<?php

/**
 * AnnouncementNotifyJob
 *
 * Every five minutes, notifies the followers of each announcement that
 * became visible since the last run and was not notified yet
 * (engagement-announcements REQ-ANN-003, design D5). An announcement
 * published "now" is notified by the service at once and carries
 * `notified_at`, so this job skips it.
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

use OCA\LaunchPad\Service\AnnouncementService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

/**
 * Notify followers of scheduled announcements.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter) $argument required by TimedJob.
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
 */
class AnnouncementNotifyJob extends TimedJob {
	/**
	 * Constructor.
	 *
	 * @param ITimeFactory $time Clock.
	 * @param AnnouncementService $announcements Announcement service.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function __construct(
		ITimeFactory $time,
		private readonly AnnouncementService $announcements,
	) {
		parent::__construct(time: $time);
		$this->setInterval(seconds: 300);
	}//end __construct()

	/**
	 * Send the due notifications.
	 *
	 * @param mixed $argument Unused.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	protected function run($argument): void {
		$this->announcements->notifyDue();
	}//end run()
}//end class
