<?php

/**
 * AnnouncementNotifyJob
 *
 * Every five minutes, tells category followers about announcements that
 * became visible since the last run (engagement-announcements, D5).
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
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
 */

declare(strict_types=1);

namespace OCA\LaunchPad\BackgroundJob;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\AnnouncementFollowNotifier;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * A thin TimedJob shell over {@see AnnouncementFollowNotifier::notifyDue()}.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter) — $argument required by TimedJob interface.
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
 */
class AnnouncementNotifyJob extends TimedJob {

	/**
	 * Seconds between runs.
	 *
	 * @var int
	 */
	public const INTERVAL_SECONDS = 300;

	/**
	 * Constructor.
	 *
	 * @param ITimeFactory               $time     The clock.
	 * @param AnnouncementFollowNotifier $notifier Sends the follow notifications.
	 * @param LoggerInterface            $logger   A failed run.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
	 */
	public function __construct(
		ITimeFactory $time,
		private readonly AnnouncementFollowNotifier $notifier,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(time: $time);
		$this->setInterval(seconds: self::INTERVAL_SECONDS);
	}//end __construct()

	/**
	 * Notify the announcements that became visible.
	 *
	 * @param mixed $argument Unused.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
	 */
	protected function run($argument): void {
		try {
			$this->notifier->notifyDue();
		} catch (Throwable $exception) {
			$this->logger->warning(
				message: 'launchpad.announcement.notify_job_failed',
				context: ['app' => Application::APP_ID, 'exception' => $exception->getMessage()]
			);
		}
	}//end run()
}//end class
