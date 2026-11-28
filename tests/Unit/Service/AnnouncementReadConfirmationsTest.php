<?php

/**
 * AnnouncementReadConfirmationsTest
 *
 * The reader's receipts of an announcement (REQ-ANN-007, REQ-ANN-009): the
 * check and the x both confirm, once, keeping the first time and kind; only
 * a published announcement and only its audience; seen once per reader, only
 * for what the reader may see; and the state the widget and banner read.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 */

declare(strict_types=1);

namespace Unit\Service;

use DateTime;
use InvalidArgumentException;
use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Db\AnnouncementReceipt;
use OCA\LaunchPad\Db\AnnouncementReceiptMapper;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Kernel\Contracts\AnnouncementServiceInterface;
use OCA\LaunchPad\Service\AnnouncementReadConfirmations;
use OCP\DB\Exception as DbException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AnnouncementReadConfirmations.
 *
 * @covers \OCA\LaunchPad\Service\AnnouncementReadConfirmations
 * @uses   \OCA\LaunchPad\Db\AnnouncementReceipt
 * @uses   \OCA\LaunchPad\Db\Announcement
 * @uses   \OCA\LaunchPad\Exception\ForbiddenException
 */
class AnnouncementReadConfirmationsTest extends TestCase {

	private const UUID = '11111111-2222-4333-8444-555555555555';

	private const OTHER = '99999999-2222-4333-8444-555555555555';

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
	 * Set up the doubles.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->announcements = $this->createMock(originalClassName: AnnouncementServiceInterface::class);
		$this->receipts      = $this->createMock(originalClassName: AnnouncementReceiptMapper::class);
		$this->receipts->method('insert')->willReturnArgument(0);
		$this->receipts->method('update')->willReturnArgument(0);
	}//end setUp()

	/**
	 * The service.
	 *
	 * @return AnnouncementReadConfirmations
	 */
	private function service(): AnnouncementReadConfirmations {
		return new AnnouncementReadConfirmations(announcements: $this->announcements, receipts: $this->receipts);
	}//end service()

	/**
	 * An announcement.
	 *
	 * @param string        $uuid   The uuid.
	 * @param string        $status The status.
	 * @param array<string> $groups The target groups.
	 *
	 * @return Announcement
	 */
	private function announcement(string $uuid = self::UUID, string $status = 'published', array $groups = ['Medewerkers']): Announcement {
		$announcement = new Announcement();
		$announcement->setUuid($uuid);
		$announcement->setStatus($status);
		$announcement->setTargetGroupList($groups);

		return $announcement;
	}//end announcement()

	/**
	 * A stored receipt.
	 *
	 * @param string      $uuid The announcement.
	 * @param bool        $seen Whether it was seen.
	 * @param string|null $via  How it was confirmed, or null.
	 *
	 * @return AnnouncementReceipt
	 */
	private function receipt(string $uuid = self::UUID, bool $seen = true, ?string $via = null): AnnouncementReceipt {
		$receipt = new AnnouncementReceipt();
		$receipt->setAnnouncementUuid($uuid);
		$receipt->setUserId('anna');
		if ($seen === true) {
			$receipt->setSeenAt(new DateTime('2026-10-27T09:00:00+00:00'));
		}

		if ($via !== null) {
			$receipt->setConfirmedAt(new DateTime('2026-10-27T09:15:00+00:00'));
			$receipt->setConfirmedVia($via);
		}

		return $receipt;
	}//end receipt()

	/**
	 * Let anna read the published announcement.
	 *
	 * @param Announcement|null $announcement The announcement, default the published one.
	 *
	 * @return void
	 */
	private function annaReads(?Announcement $announcement = null): void {
		$this->announcements->method('findFor')->with('anna', self::UUID)->willReturn($announcement ?? $this->announcement());
		$this->announcements->method('reachesUser')->willReturn(true);
	}//end annaReads()

	/**
	 * A first confirmation with the x stores seen, confirmed and the kind.
	 *
	 * @return void
	 */
	public function testTheXConfirmsAndMarksSeen(): void {
		$this->annaReads();
		$stored = [];
		$receipts = $this->createMock(originalClassName: AnnouncementReceiptMapper::class);
		$receipts->method('findOne')->willReturn(null);
		$receipts->expects($this->once())->method('insert')->willReturnCallback(
			static function (AnnouncementReceipt $receipt) use (&$stored): AnnouncementReceipt {
				$stored[] = $receipt;
				return $receipt;
			}
		);
		$this->receipts = $receipts;

		$answer = $this->service()->confirm(userId: 'anna', uuid: self::UUID, via: 'x');

		$this->assertTrue(condition: $answer['confirmed']);
		$this->assertSame(expected: 'x', actual: $answer['confirmedVia']);
		$this->assertNotNull(actual: $answer['confirmedAt']);
		$this->assertSame(expected: 'anna', actual: $stored[0]->getUserId());
		$this->assertNotNull(actual: $stored[0]->getSeenAt());
	}//end testTheXConfirmsAndMarksSeen()

	/**
	 * Confirming a seen receipt updates it and keeps the seen time.
	 *
	 * @return void
	 */
	public function testConfirmingASeenReceiptKeepsTheSeenTime(): void {
		$this->annaReads();
		$this->receipts->method('findOne')->willReturn($this->receipt());
		$this->receipts->expects($this->never())->method('insert');
		$this->receipts->expects($this->once())->method('update')->willReturnArgument(0);

		$answer = $this->service()->confirm(userId: 'anna', uuid: self::UUID);

		$this->assertSame(expected: 'check', actual: $answer['confirmedVia']);
	}//end testConfirmingASeenReceiptKeepsTheSeenTime()

	/**
	 * A second confirmation answers the first, kind and time.
	 *
	 * @return void
	 */
	public function testASecondConfirmationKeepsTheFirst(): void {
		$this->annaReads();
		$this->receipts->method('findOne')->willReturn($this->receipt(via: 'check'));
		$this->receipts->expects($this->never())->method('update');
		$this->receipts->expects($this->never())->method('insert');

		$answer = $this->service()->confirm(userId: 'anna', uuid: self::UUID, via: 'x');

		$this->assertSame(expected: 'check', actual: $answer['confirmedVia']);
		$this->assertSame(expected: '2026-10-27T09:15:00+00:00', actual: $answer['confirmedAt']);
	}//end testASecondConfirmationKeepsTheFirst()

	/**
	 * A concurrent first write answers the row that won, confirming it when it was only seen.
	 *
	 * @return void
	 */
	public function testARaceConfirmsTheWinningRow(): void {
		$this->annaReads();
		$exception = $this->createMock(originalClassName: DbException::class);
		$exception->method('getReason')->willReturn(DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION);
		$receipts = $this->createMock(originalClassName: AnnouncementReceiptMapper::class);
		$receipts->method('findOne')->willReturnOnConsecutiveCalls(null, $this->receipt());
		$receipts->method('insert')->willThrowException($exception);
		$receipts->expects($this->once())->method('update')->willReturnArgument(0);
		$this->receipts = $receipts;

		$this->assertSame(expected: 'check', actual: $this->service()->confirm(userId: 'anna', uuid: self::UUID)['confirmedVia']);
	}//end testARaceConfirmsTheWinningRow()

	/**
	 * Another database error is not swallowed.
	 *
	 * @return void
	 */
	public function testAnotherDatabaseErrorIsThrown(): void {
		$this->annaReads();
		$exception = $this->createMock(originalClassName: DbException::class);
		$exception->method('getReason')->willReturn(DbException::REASON_DRIVER);
		$receipts = $this->createMock(originalClassName: AnnouncementReceiptMapper::class);
		$receipts->method('findOne')->willReturn(null);
		$receipts->method('insert')->willThrowException($exception);
		$this->receipts = $receipts;

		$this->expectException(exception: DbException::class);
		$this->service()->confirm(userId: 'anna', uuid: self::UUID);
	}//end testAnotherDatabaseErrorIsThrown()

	/**
	 * An unknown kind, or a draft, cannot be confirmed.
	 *
	 * @return void
	 */
	public function testAnUnknownKindOrADraftIsRefused(): void {
		$this->annaReads(announcement: $this->announcement(status: 'draft'));

		try {
			$this->service()->confirm(userId: 'anna', uuid: self::UUID, via: 'thumbs-up');
			$this->fail(message: 'An unknown kind must be refused');
		} catch (InvalidArgumentException) {
			$this->addToAssertionCount(count: 1);
		}

		$this->expectException(exception: InvalidArgumentException::class);
		$this->service()->confirm(userId: 'anna', uuid: self::UUID);
	}//end testAnUnknownKindOrADraftIsRefused()

	/**
	 * A reader outside the audience cannot confirm.
	 *
	 * @return void
	 */
	public function testAReaderOutsideTheAudienceIsRefused(): void {
		$this->announcements->method('findFor')->willReturn($this->announcement());
		$this->announcements->method('reachesUser')->with(['Medewerkers'], 'sanne')->willReturn(false);
		$this->receipts->expects($this->never())->method('insert');

		$this->expectException(exception: ForbiddenException::class);
		$this->service()->confirm(userId: 'sanne', uuid: self::UUID);
	}//end testAReaderOutsideTheAudienceIsRefused()

	/**
	 * Seen records only what the reader may see, once: a new one is
	 * inserted, an unseen confirmed one is updated, a seen one is skipped.
	 *
	 * @return void
	 */
	public function testSeenRecordsOnlyVisibleUnseenOnes(): void {
		$third = '33333333-2222-4333-8444-555555555555';
		$this->announcements->method('visibleTo')->with('anna')->willReturn(
			[$this->announcement(), $this->announcement(uuid: self::OTHER), $this->announcement(uuid: $third)]
		);
		$unseen = $this->receipt(uuid: $third, seen: false);
		$this->receipts->method('findByUserAmong')->willReturn([$this->receipt(uuid: self::OTHER), $unseen]);
		$this->receipts->expects($this->once())->method('insert')->willReturnArgument(0);
		$this->receipts->expects($this->once())->method('update')->with($unseen)->willReturnArgument(0);

		$recorded = $this->service()->markSeen(userId: 'anna', uuids: [self::UUID, self::OTHER, $third, 'not-visible', self::UUID, '']);

		$this->assertSame(expected: [self::UUID, $third], actual: $recorded);
	}//end testSeenRecordsOnlyVisibleUnseenOnes()

	/**
	 * An empty batch asks nothing; a batch over the limit is refused.
	 *
	 * @return void
	 */
	public function testSeenBatchLimits(): void {
		$this->announcements->expects($this->never())->method('visibleTo');

		$this->assertSame(expected: [], actual: $this->service()->markSeen(userId: 'anna', uuids: []));

		$this->expectException(exception: InvalidArgumentException::class);
		$this->service()->markSeen(userId: 'anna', uuids: array_map(static fn (int $n): string => 'u'.$n, range(1, 101)));
	}//end testSeenBatchLimits()

	/**
	 * The state names what the reader saw, confirmed and closed; a manager may open every receipt.
	 *
	 * @return void
	 */
	public function testStateOfVisible(): void {
		$this->announcements->method('visibleTo')->willReturn([$this->announcement(), $this->announcement(uuid: self::OTHER)]);
		$this->announcements->method('isAuthor')->willReturnOnConsecutiveCalls(false, true);
		$this->receipts->method('findByUserAmong')->willReturn([$this->receipt(via: 'x'), $this->receipt(uuid: self::OTHER)]);

		$this->assertSame(
			expected: ['seen' => [self::UUID, self::OTHER], 'confirmed' => [self::UUID], 'closed' => [self::UUID], 'receipts' => []],
			actual: $this->service()->stateOfVisible(userId: 'anna')
		);
		$this->assertSame(expected: [self::UUID, self::OTHER], actual: $this->service()->stateOfVisible(userId: 'anna')['receipts']);
	}//end testStateOfVisible()
}//end class
