<?php

/**
 * AnnouncementReminderTest
 *
 * Send reminder (REQ-ANN-009): one notification to every audience member who
 * has not confirmed, the time stored, at most once a day, never for a draft.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 */

declare(strict_types=1);

namespace Unit\Service;

use DateTime;
use InvalidArgumentException;
use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Db\AnnouncementMapper;
use OCA\LaunchPad\Service\AnnouncementReceiptReport;
use OCA\LaunchPad\Service\AnnouncementReminder;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AnnouncementReminder.
 *
 * @covers \OCA\LaunchPad\Service\AnnouncementReminder
 * @uses   \OCA\LaunchPad\Service\AnnouncementReceiptReport
 * @uses   \OCA\LaunchPad\Db\Announcement
 */
class AnnouncementReminderTest extends TestCase {

	private const UUID = '11111111-2222-4333-8444-555555555555';

	/**
	 * The report double.
	 *
	 * @var AnnouncementReceiptReport&MockObject
	 */
	private AnnouncementReceiptReport $report;

	/**
	 * The announcement store double.
	 *
	 * @var AnnouncementMapper&MockObject
	 */
	private AnnouncementMapper $store;

	/**
	 * The notification manager double.
	 *
	 * @var INotificationManager&MockObject
	 */
	private INotificationManager $notifications;

	/**
	 * Set up the doubles.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->report        = $this->createMock(originalClassName: AnnouncementReceiptReport::class);
		$this->store         = $this->createMock(originalClassName: AnnouncementMapper::class);
		$this->notifications = $this->createMock(originalClassName: INotificationManager::class);
		$this->report->method('unconfirmed')->willReturn(['cor', 'dirk']);
	}//end setUp()

	/**
	 * The service.
	 *
	 * @return AnnouncementReminder
	 */
	private function service(): AnnouncementReminder {
		return new AnnouncementReminder(report: $this->report, store: $this->store, notifications: $this->notifications);
	}//end service()

	/**
	 * The managed announcement.
	 *
	 * @param string        $status     The status.
	 * @param DateTime|null $remindedAt The last reminder.
	 *
	 * @return Announcement
	 */
	private function announcement(string $status = 'published', ?DateTime $remindedAt = null): Announcement {
		$announcement = new Announcement();
		$announcement->setUuid(self::UUID);
		$announcement->setTitle('Nieuwe werkwijze Woo-verzoeken');
		$announcement->setStatus($status);
		$announcement->setRemindedAt($remindedAt);
		$this->report->method('managed')->with('femke', self::UUID)->willReturn($announcement);

		return $announcement;
	}//end announcement()

	/**
	 * Send reminder notifies those who have not confirmed and stores the time.
	 *
	 * @return void
	 */
	public function testRemindNotifiesTheUnconfirmed(): void {
		$announcement = $this->announcement();
		$users        = [];
		$this->notifications->method('createNotification')->willReturnCallback(function () use (&$users): INotification {
			$notification = $this->createMock(originalClassName: INotification::class);
			foreach (['setApp', 'setDateTime', 'setObject'] as $setter) {
				$notification->method($setter)->willReturnSelf();
			}

			$notification->method('setUser')->willReturnCallback(static function (string $user) use (&$users, $notification): INotification {
				$users[] = $user;
				return $notification;
			});
			$notification->method('setSubject')->with('announcement_reminder', ['Nieuwe werkwijze Woo-verzoeken'])->willReturnSelf();
			return $notification;
		});
		$this->notifications->expects($this->exactly(count: 2))->method('notify');
		$this->store->expects($this->once())->method('update')->with($announcement)->willReturnArgument(0);

		$result = $this->service()->remind(userId: 'femke', uuid: self::UUID);

		$this->assertSame(expected: 2, actual: $result['sent']);
		$this->assertSame(expected: ['cor', 'dirk'], actual: $users);
		$this->assertNotNull(actual: $announcement->getRemindedAt());
	}//end testRemindNotifiesTheUnconfirmed()

	/**
	 * A second reminder within a day is refused.
	 *
	 * @return void
	 */
	public function testRemindAtMostOnceADay(): void {
		$this->announcement(remindedAt: new DateTime('-2 hours'));
		$this->notifications->expects($this->never())->method('notify');
		$this->store->expects($this->never())->method('update');

		$this->expectException(exception: InvalidArgumentException::class);
		$this->service()->remind(userId: 'femke', uuid: self::UUID);
	}//end testRemindAtMostOnceADay()

	/**
	 * A draft cannot be reminded.
	 *
	 * @return void
	 */
	public function testADraftIsNotReminded(): void {
		$this->announcement(status: 'draft', remindedAt: new DateTime('-2 days'));

		$this->expectException(exception: InvalidArgumentException::class);
		$this->service()->remind(userId: 'femke', uuid: self::UUID);
	}//end testADraftIsNotReminded()
}//end class
