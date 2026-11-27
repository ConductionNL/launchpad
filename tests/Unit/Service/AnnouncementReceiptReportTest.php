<?php

/**
 * AnnouncementReceiptReportTest
 *
 * Seen and confirmed for managers (REQ-ANN-009): the figures over the
 * audience (seen, not seen, confirmed via the check and the x, not
 * confirmed), the counts of the tab's list, who has not confirmed, whether a
 * reminder may be sent, and readers refused.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 */

declare(strict_types=1);

namespace Unit\Service;

use DateTime;
use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Db\AnnouncementReceipt;
use OCA\LaunchPad\Db\AnnouncementReceiptMapper;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Kernel\Contracts\AnnouncementServiceInterface;
use OCA\LaunchPad\Service\AnnouncementAudience;
use OCA\LaunchPad\Service\AnnouncementReceiptReport;
use OCA\LaunchPad\Service\ReadReceiptPeople;
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
	 * Set up the doubles: Femke manages, the audience is four people.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->announcements = $this->createMock(originalClassName: AnnouncementServiceInterface::class);
		$this->receipts      = $this->createMock(originalClassName: AnnouncementReceiptMapper::class);
		$this->audience      = $this->createMock(originalClassName: AnnouncementAudience::class);
		$this->announcements->method('isAuthor')->willReturnCallback(static fn (string $userId): bool => $userId === 'femke');
		$this->audience->method('members')->willReturn(['anna', 'bart', 'cor', 'dirk']);
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
		$people = $this->createMock(originalClassName: ReadReceiptPeople::class);
		$people->method('withPeople')->willReturnCallback(static fn (array $report): array => $report + ['departmentLabel' => '']);

		return new AnnouncementReceiptReport(
			announcements: $this->announcements,
			receipts: $this->receipts,
			audience: $this->audience,
			people: $people
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
		$this->assertSame(expected: '', actual: $report['departmentLabel']);
		$bart = [
			'userId'         => 'bart',
			'status'         => 'acknowledged',
			'seen'           => true,
			'seenAt'         => '2026-10-27T09:00:00+00:00',
			'confirmedAt'    => '2026-10-27T09:15:00+00:00',
			'confirmedVia'   => 'x',
			'acknowledgedAt' => '2026-10-27T09:15:00+00:00',
		];
		$dirk = [
			'userId'         => 'dirk',
			'status'         => 'pending',
			'seen'           => false,
			'seenAt'         => null,
			'confirmedAt'    => null,
			'confirmedVia'   => null,
			'acknowledgedAt' => null,
		];
		$this->assertSame(expected: $bart, actual: $report['rows'][1]);
		$this->assertSame(expected: $dirk, actual: $report['rows'][3]);
	}//end testTheFiguresCountTheAudience()

	/**
	 * A reader cannot read the receipts.
	 *
	 * @return void
	 */
	public function testAReaderIsRefused(): void {
		$this->announcements->expects($this->never())->method('findFor');

		$this->expectException(exception: ForbiddenException::class);
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
	 * Who has not confirmed: the audience minus the confirmed, also those who only saw it.
	 *
	 * @return void
	 */
	public function testUnconfirmedIsTheAudienceMinusTheConfirmed(): void {
		$this->assertSame(expected: ['cor', 'dirk'], actual: $this->service()->unconfirmed(announcement: $this->announcement()));
	}//end testUnconfirmedIsTheAudienceMinusTheConfirmed()

	/**
	 * A reminder may be sent when none was, or the last one a day ago or more.
	 *
	 * @return void
	 */
	public function testMayRemindOnceADay(): void {
		$announcement = new Announcement();
		$this->assertTrue(condition: AnnouncementReceiptReport::mayRemind(announcement: $announcement));
		$announcement->setRemindedAt(new DateTime('-2 hours'));
		$this->assertFalse(condition: AnnouncementReceiptReport::mayRemind(announcement: $announcement));
		$announcement->setRemindedAt(new DateTime('-25 hours'));
		$this->assertTrue(condition: AnnouncementReceiptReport::mayRemind(announcement: $announcement));
	}//end testMayRemindOnceADay()

	/**
	 * A manager gets the announcement; a reader is refused before it is looked up.
	 *
	 * @return void
	 */
	public function testManagedIsForManagers(): void {
		$announcement = $this->announcement();
		$this->assertSame(expected: $announcement, actual: $this->service()->managed(userId: 'femke', uuid: self::UUID));

		$this->expectException(exception: ForbiddenException::class);
		$this->service()->managed(userId: 'pieter', uuid: self::UUID);
	}//end testManagedIsForManagers()
}//end class
