<?php

/**
 * AnnouncementReceiptReportTest
 *
 * Seen and confirmed for managers (REQ-ANN-009): the figures over the
 * audience (seen, not seen, confirmed via the check and the x, not
 * confirmed), the counts of the tab's list, Send reminder to those who have
 * not confirmed at most once a day, and readers refused.
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
use OCA\LaunchPad\Db\AnnouncementReceipt;
use OCA\LaunchPad\Db\AnnouncementReceiptMapper;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Kernel\Contracts\AnnouncementServiceInterface;
use OCA\LaunchPad\Service\AnnouncementAudience;
use OCA\LaunchPad\Service\AnnouncementReceiptReport;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AnnouncementReceiptReport.
 *
 * @covers \OCA\LaunchPad\Service\AnnouncementReceiptReport
 * @uses   \OCA\LaunchPad\Db\AnnouncementReceipt
 * @uses   \OCA\LaunchPad\Db\Announcement
 * @uses   \OCA\LaunchPad\Exception\ForbiddenException
 */
class AnnouncementReceiptReportTest extends TestCase {

	private const UUID = '11111111-2222-4333-8444-555555555555';

	/**
	 * The announcements double.
	 *
	 * @var AnnouncementServiceInterface&MockObject
	 */
	private AnnouncementServiceInterface $announcements;

	/**
	 * The receipts double.
	 *
	 * @var AnnouncementReceiptMapper&MockObject
	 */
	private AnnouncementReceiptMapper $receipts;

	/**
	 * The audience double.
	 *
	 * @var AnnouncementAudience&MockObject
	 */
	private AnnouncementAudience $audience;

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
	 * Set up the doubles: Femke manages, the audience is four people.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->announcements = $this->createMock(originalClassName: AnnouncementServiceInterface::class);
		$this->receipts      = $this->createMock(originalClassName: AnnouncementReceiptMapper::class);
		$this->audience      = $this->createMock(originalClassName: AnnouncementAudience::class);
		$this->store         = $this->createMock(originalClassName: AnnouncementMapper::class);
		$this->notifications = $this->createMock(originalClassName: INotificationManager::class);
		$this->announcements->method('isAuthor')->willReturnCallback(static fn (string $userId): bool => $userId === 'femke');
		$this->audience->method('of')->willReturn(['anna', 'bart', 'cor', 'dirk']);
		$this->receipts->method('findByAnnouncement')->willReturn(
			[
				$this->receipt(userId: 'anna', via: 'check'),
				$this->receipt(userId: 'bart', via: 'x'),
				$this->receipt(userId: 'cor', via: null),
				$this->receipt(userId: 'left-the-group', via: 'check'),
			]
		);
	}//end setUp()

	/**
	 * The service.
	 *
	 * @return AnnouncementReceiptReport
	 */
	private function service(): AnnouncementReceiptReport {
		return new AnnouncementReceiptReport(
			announcements: $this->announcements,
			receipts: $this->receipts,
			audience: $this->audience,
			store: $this->store,
			notifications: $this->notifications
		);
	}//end service()

	/**
	 * The announcement.
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
		$announcement->setRequiresConfirmation(1);
		$announcement->setConfirmBefore(new DateTime('2026-10-30T23:59:00+00:00'));
		$announcement->setRemindedAt($remindedAt);
		$this->announcements->method('findFor')->with('femke', self::UUID)->willReturn($announcement);

		return $announcement;
	}//end announcement()

	/**
	 * A receipt, seen, and confirmed when via is given.
	 *
	 * @param string      $userId The reader.
	 * @param string|null $via    check, x or null.
	 *
	 * @return AnnouncementReceipt
	 */
	private function receipt(string $userId, ?string $via): AnnouncementReceipt {
		$receipt = new AnnouncementReceipt();
		$receipt->setAnnouncementUuid(self::UUID);
		$receipt->setUserId($userId);
		$receipt->setSeenAt(new DateTime('2026-10-27T09:00:00+00:00'));
		if ($via !== null) {
			$receipt->setConfirmedAt(new DateTime('2026-10-27T09:15:00+00:00'));
			$receipt->setConfirmedVia($via);
		}

		return $receipt;
	}//end receipt()

	/**
	 * The figures count the audience only, check and x apart.
	 *
	 * @return void
	 */
	public function testTheFiguresCountTheAudience(): void {
		$this->announcement();

		$report = $this->service()->report(userId: 'femke', uuid: self::UUID);

		$this->assertSame(expected: 4, actual: $report['audienceCount']);
		$this->assertSame(expected: 3, actual: $report['seenCount']);
		$this->assertSame(expected: 1, actual: $report['notSeenCount']);
		$this->assertSame(expected: 2, actual: $report['confirmedCount']);
		$this->assertSame(expected: 1, actual: $report['confirmedViaCheck']);
		$this->assertSame(expected: 1, actual: $report['confirmedViaX']);
		$this->assertSame(expected: 2, actual: $report['notConfirmedCount']);
		$this->assertSame(expected: ['cor', 'dirk'], actual: $report['pending']);
		$this->assertTrue(condition: $report['canRemind']);
		$this->assertSame(
			expected: ['userId' => 'bart', 'status' => 'acknowledged', 'seen' => true, 'seenAt' => '2026-10-27T09:00:00+00:00', 'confirmedAt' => '2026-10-27T09:15:00+00:00', 'confirmedVia' => 'x', 'acknowledgedAt' => '2026-10-27T09:15:00+00:00'],
			actual: $report['rows'][1]
		);
		$this->assertSame(
			expected: ['userId' => 'dirk', 'status' => 'pending', 'seen' => false, 'seenAt' => null, 'confirmedAt' => null, 'confirmedVia' => null, 'acknowledgedAt' => null],
			actual: $report['rows'][3]
		);
	}//end testTheFiguresCountTheAudience()

	/**
	 * A reader cannot read the receipts.
	 *
	 * @return void
	 */
	public function testAReaderIsRefused(): void {
		$this->announcements->expects($this->never())->method('findFor');

		$this->expectException(ForbiddenException::class);
		$this->service()->report(userId: 'pieter', uuid: self::UUID);
	}//end testAReaderIsRefused()

	/**
	 * The list counts fill in zero for an announcement without receipts.
	 *
	 * @return void
	 */
	public function testCountsFillInZero(): void {
		$this->receipts->method('countsFor')->willReturn([self::UUID => ['seen' => 3, 'confirmed' => 2]]);

		$this->assertSame(
			expected: [self::UUID => ['seen' => 3, 'confirmed' => 2], 'other' => ['seen' => 0, 'confirmed' => 0]],
			actual: $this->service()->countsFor(uuids: [self::UUID, 'other'])
		);
	}//end testCountsFillInZero()

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
		$this->notifications->expects($this->exactly(2))->method('notify');
		$this->store->expects($this->once())->method('update')->with($announcement)->willReturnArgument(0);

		$result = $this->service()->remind(userId: 'femke', uuid: self::UUID);

		$this->assertSame(expected: 2, actual: $result['sent']);
		$this->assertSame(expected: ['cor', 'dirk'], actual: $users);
		$this->assertNotNull(actual: $announcement->getRemindedAt());
	}//end testRemindNotifiesTheUnconfirmed()

	/**
	 * A second reminder within a day is refused; a day later it is allowed.
	 *
	 * @return void
	 */
	public function testRemindAtMostOnceADay(): void {
		$this->announcement(remindedAt: new DateTime('-2 hours'));
		$this->notifications->expects($this->never())->method('notify');

		$this->assertFalse(condition: $this->service()->report(userId: 'femke', uuid: self::UUID)['canRemind']);
		$this->expectException(InvalidArgumentException::class);
		$this->service()->remind(userId: 'femke', uuid: self::UUID);
	}//end testRemindAtMostOnceADay()

	/**
	 * A draft cannot be reminded.
	 *
	 * @return void
	 */
	public function testADraftIsNotReminded(): void {
		$this->announcement(status: 'draft', remindedAt: new DateTime('-2 days'));

		$this->expectException(InvalidArgumentException::class);
		$this->service()->remind(userId: 'femke', uuid: self::UUID);
	}//end testADraftIsNotReminded()

	/**
	 * Purge removes the receipts of a deleted announcement.
	 *
	 * @return void
	 */
	public function testPurge(): void {
		$this->receipts->expects($this->once())->method('deleteByAnnouncement')->with(self::UUID);

		$this->service()->purge(uuid: self::UUID);
	}//end testPurge()
}//end class
