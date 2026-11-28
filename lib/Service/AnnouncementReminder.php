<?php

/**
 * AnnouncementReminder
 *
 * Send reminder on Seen and confirmed (REQ-ANN-009, board
 * LpLeesbevestigingen): one Nextcloud notification to every audience member
 * who has not confirmed a published announcement, at most once a day per
 * announcement; the time is kept on the announcement.
 *
 * @category Service
 * @package  OCA\LaunchPad\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTime;
use DateTimeInterface;
use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Db\AnnouncementMapper;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCP\Notification\IManager as INotificationManager;

/**
 * Sends the once-a-day reminder of an announcement.
 *
 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 */
class AnnouncementReminder {

	/**
	 * The notification subject of a reminder.
	 */
	public const SUBJECT = 'announcement_reminder';

	/**
	 * Constructor.
	 *
	 * @param AnnouncementReceiptReport $report        Who manages, and who has not confirmed.
	 * @param AnnouncementMapper        $store         Stores the reminder time.
	 * @param INotificationManager      $notifications Sends the reminders.
	 */
	public function __construct(
		private readonly AnnouncementReceiptReport $report,
		private readonly AnnouncementMapper $store,
		private readonly INotificationManager $notifications,
	) {
	}//end __construct()

	/**
	 * Send reminder to every audience member who has not confirmed.
	 *
	 * @param string $userId The manager.
	 * @param string $uuid   The announcement.
	 *
	 * @return array{uuid: string, sent: int, remindedAt: string}
	 *
	 * @throws ForbiddenException       When the user does not manage announcements.
	 * @throws InvalidArgumentException When it is not published or was reminded less than a day ago.
	 *
	 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
	 */
	public function remind(string $userId, string $uuid): array {
		$announcement = $this->report->managed(userId: $userId, uuid: $uuid);
		if ($announcement->getStatus() !== Announcement::STATUS_PUBLISHED) {
			throw new InvalidArgumentException('Only a published announcement can be reminded');
		}

		if ($this->report->mayRemind(announcement: $announcement) === false) {
			throw new InvalidArgumentException('A reminder was already sent in the last 24 hours');
		}

		$now     = new DateTime();
		$members = $this->report->unconfirmed(announcement: $announcement);
		foreach ($members as $member) {
			$notification = $this->notifications->createNotification();
			$notification->setApp(Application::APP_ID)
				->setUser($member)
				->setDateTime($now)
				->setObject('announcement', $uuid)
				->setSubject(self::SUBJECT, [(string) $announcement->getTitle()]);
			$this->notifications->notify($notification);
		}

		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters -- Entity setters resolve via __call.
		$announcement->setRemindedAt($now);
		$this->store->update(entity: $announcement);

		return ['uuid' => $uuid, 'sent' => count($members), 'remindedAt' => $now->format(DateTimeInterface::ATOM)];
	}//end remind()
}//end class
