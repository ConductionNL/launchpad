<?php

/**
 * AnnouncementFollowNotifier
 *
 * Tells the followers of a category once when an announcement in it becomes
 * visible to them (engagement-announcements, REQ-ANN-003, D5).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
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

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Db\AnnouncementFollow;
use OCA\LaunchPad\Db\AnnouncementFollowMapper;
use OCA\LaunchPad\Db\AnnouncementMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Sends `announcement_published` to the followers an announcement reaches, once.
 *
 * An announcement is notified when it is live (published, past its publish
 * time, before its end time) and its `notified_at` is empty; sending stamps
 * `notified_at`, so the job and an immediate publish never notify twice. A
 * follower is told only when the announcement targets them, and the author
 * is not told about their own announcement.
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
 */
class AnnouncementFollowNotifier {

	/**
	 * The notification subject.
	 *
	 * @var string
	 */
	public const SUBJECT = 'announcement_published';

	/**
	 * Constructor.
	 *
	 * @param AnnouncementMapper       $announcements The announcements.
	 * @param AnnouncementFollowMapper $follows       The followed categories.
	 * @param AdminTemplateService     $userGroups    A user's group ids.
	 * @param INotificationManager     $notifications Nextcloud's notifications.
	 * @param ITimeFactory             $time          The clock.
	 * @param LoggerInterface          $logger        Failed sends.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
	 */
	public function __construct(
		private readonly AnnouncementMapper $announcements,
		private readonly AnnouncementFollowMapper $follows,
		private readonly AdminTemplateService $userGroups,
		private readonly INotificationManager $notifications,
		private readonly ITimeFactory $time,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Notify every live announcement that has not been notified yet.
	 *
	 * @return int How many notifications were sent.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
	 */
	public function notifyDue(): int {
		$sent = 0;
		foreach ($this->announcements->findLive(now: $this->time->getDateTime()) as $announcement) {
			$sent += $this->notify(announcement: $announcement);
		}

		return $sent;
	}//end notifyDue()

	/**
	 * Notify the followers one live announcement reaches, unless that was done before.
	 *
	 * @param Announcement $announcement The announcement.
	 *
	 * @return int How many notifications were sent.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
	 */
	public function notify(Announcement $announcement): int {
		if ($announcement->getNotifiedAt() !== null || $this->isLive(announcement: $announcement) === false) {
			return 0;
		}

		$sent = 0;
		foreach ($this->recipients(announcement: $announcement) as $userId) {
			$sent += $this->send(announcement: $announcement, userId: $userId);
		}

		$announcement->setNotifiedAt($this->time->getDateTime());
		$this->announcements->update(entity: $announcement);

		return $sent;
	}//end notify()

	/**
	 * The followers of the announcement's category it targets, without its author.
	 *
	 * @param Announcement $announcement The announcement.
	 *
	 * @return list<string>
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
	 */
	private function recipients(Announcement $announcement): array {
		$category = (string) $announcement->getCategory();
		if ($category === '') {
			return [];
		}

		$targets = $announcement->getTargetGroupList();
		$recipients = [];
		foreach ($this->follows->findByCategory(category: $category) as $follow) {
			$userId = ($follow instanceof AnnouncementFollow) ? (string) $follow->getUserId() : '';
			if ($userId === '' || $userId === $announcement->getAuthorId()) {
				continue;
			}

			if ($targets === [] || array_intersect($targets, $this->userGroups->getUserGroupIdsFor($userId)) !== []) {
				$recipients[$userId] = true;
			}
		}

		return array_keys($recipients);
	}//end recipients()

	/**
	 * Send one notification; a failed send is logged and counts as none.
	 *
	 * @param Announcement $announcement The announcement.
	 * @param string       $userId       The follower.
	 *
	 * @return int 1 when sent, else 0.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
	 */
	private function send(Announcement $announcement, string $userId): int {
		try {
			$notification = $this->notifications->createNotification();
			$notification->setApp(Application::APP_ID)
				->setUser($userId)
				->setDateTime($this->time->getDateTime())
				->setObject('announcement', (string) $announcement->getUuid())
				->setSubject(self::SUBJECT, [(string) $announcement->getCategory(), (string) $announcement->getTitle()]);
			$this->notifications->notify($notification);
			return 1;
		} catch (Throwable $exception) {
			$this->logger->warning(
				message: 'launchpad.announcement.notify_failed',
				context: ['app' => Application::APP_ID, 'exception' => $exception->getMessage()]
			);
			return 0;
		}
	}//end send()

	/**
	 * Whether the announcement is published and inside its period now.
	 *
	 * @param Announcement $announcement The announcement.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
	 */
	private function isLive(Announcement $announcement): bool {
		$now = $this->time->getDateTime();
		$publishAt = $announcement->getPublishAt();
		$expiresAt = $announcement->getExpiresAt();

		return $announcement->getStatus() === Announcement::STATUS_PUBLISHED
			&& ($publishAt === null || $publishAt <= $now)
			&& ($expiresAt === null || $expiresAt > $now);
	}//end isLive()
}//end class
