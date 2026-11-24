<?php

/**
 * AnnouncementReadConfirmationsTest
 *
 * "I have read this" on an announcement (REQ-ANN-007): only a published
 * announcement that asks for it, only its audience, once; the card state per
 * list; and the author's report of who confirmed.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 */

declare(strict_types=1);

namespace Unit\Service;

use DateTime;
use InvalidArgumentException;
use OCA\LaunchPad\Db\Acknowledgement;
use OCA\LaunchPad\Db\AcknowledgementMapper;
use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Kernel\Contracts\AnnouncementServiceInterface;
use OCA\LaunchPad\Service\AnnouncementReadConfirmations;
use OCA\LaunchPad\Service\ReadReceiptPeople;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception as DbException;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AnnouncementReadConfirmations.
 *
 * @covers \OCA\LaunchPad\Service\AnnouncementReadConfirmations
 * @uses   \OCA\LaunchPad\Db\Acknowledgement
 * @uses   \OCA\LaunchPad\Db\Announcement
 * @uses   \OCA\LaunchPad\Exception\ForbiddenException
 */
class AnnouncementReadConfirmationsTest extends TestCase {

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
	 * @var AcknowledgementMapper&MockObject
	 */
	private AcknowledgementMapper $receipts;

	/**
	 * The group manager double.
	 *
	 * @var IGroupManager&MockObject
	 */
	private IGroupManager $groupManager;

	/**
	 * The user manager double.
	 *
	 * @var IUserManager&MockObject
	 */
	private IUserManager $userManager;

	/**
	 * Set up the doubles.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->announcements = $this->createMock(AnnouncementServiceInterface::class);
		$this->receipts      = $this->createMock(AcknowledgementMapper::class);
		$this->groupManager  = $this->createMock(IGroupManager::class);
		$this->userManager   = $this->createMock(IUserManager::class);
	}//end setUp()

	/**
	 * The service, with names passed through and today fixed at 29 October.
	 *
	 * @return AnnouncementReadConfirmations
	 */
	private function service(): AnnouncementReadConfirmations {
		$people = $this->createMock(ReadReceiptPeople::class);
		$people->method('withPeople')->willReturnCallback(static fn (array $report): array => $report + ['departmentLabel' => '']);
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getDateTime')->willReturn(new DateTime('2026-10-29T12:00:00+00:00'));

		return new AnnouncementReadConfirmations(
			$this->announcements,
			$this->receipts,
			$this->groupManager,
			$this->userManager,
			$people,
			$time
		);
	}//end service()

	/**
	 * An announcement.
	 *
	 * @param bool          $asks    Whether it asks for a read confirmation.
	 * @param array<string> $groups  The target groups.
	 * @param string        $status  The status.
	 * @param string        $uuid    The uuid.
	 * @param string        $author  The author.
	 *
	 * @return Announcement
	 */
	private function announcement(bool $asks = true, array $groups = ['Medewerkers'], string $status = 'published', string $uuid = self::UUID, string $author = 'pieter'): Announcement {
		$announcement = new Announcement();
		$announcement->setAuthorId($author);
		$announcement->setUuid($uuid);
		$announcement->setTitle('New procedure');
		$announcement->setStatus($status);
		$announcement->setTargetGroupList($groups);
		$announcement->setRequiresConfirmation((int) $asks);
		if ($asks === true) {
			$announcement->setConfirmBefore(new DateTime('2026-10-30T23:59:00+00:00'));
		}

		return $announcement;
	}//end announcement()

	/**
	 * A stored receipt.
	 *
	 * @param string $userId The reader.
	 *
	 * @return Acknowledgement
	 */
	private function receipt(string $userId): Acknowledgement {
		$receipt = new Acknowledgement();
		$receipt->setAnnouncementKey('announcement-' . self::UUID);
		$receipt->setUserId($userId);
		$receipt->setAcknowledgedAt(new DateTime('2026-10-27 09:15:00'));

		return $receipt;
	}//end receipt()

	/**
	 * A reader in the audience confirms once; the receipt carries the announcement's key.
	 *
	 * @return void
	 */
	public function testAReaderInTheAudienceConfirms(): void {
		$this->announcements->method('findFor')->with('anna', self::UUID)->willReturn($this->announcement());
		$this->announcements->method('reachesUser')->with(['Medewerkers'], 'anna')->willReturn(true);
		$this->receipts->method('findOneFor')->willReturn(null);
		$this->receipts->expects($this->once())->method('record')
			->with('announcement-' . self::UUID, 'anna', 1)
			->willReturn($this->receipt(userId: 'anna'));

		$answer = $this->service()->confirm(userId: 'anna', uuid: self::UUID);

		$this->assertSame(['uuid' => self::UUID, 'confirmed' => true, 'confirmedAt' => '2026-10-27 09:15:00'], $answer);
	}//end testAReaderInTheAudienceConfirms()

	/**
	 * A second confirmation answers the first and stores nothing.
	 *
	 * @return void
	 */
	public function testASecondConfirmationAnswersTheFirst(): void {
		$this->announcements->method('findFor')->willReturn($this->announcement(groups: []));
		$this->announcements->method('reachesUser')->with([], 'anna')->willReturn(true);
		$this->receipts->method('findOneFor')->willReturn($this->receipt(userId: 'anna'));
		$this->receipts->expects($this->never())->method('record');

		$this->assertTrue($this->service()->confirm(userId: 'anna', uuid: self::UUID)['confirmed']);
	}//end testASecondConfirmationAnswersTheFirst()

	/**
	 * A concurrent identical write answers the row that won the race.
	 *
	 * @return void
	 */
	public function testARaceAnswersTheWinningRow(): void {
		$this->announcements->method('findFor')->willReturn($this->announcement(groups: []));
		$this->announcements->method('reachesUser')->willReturn(true);
		$this->receipts->method('findOneFor')->willReturnOnConsecutiveCalls(null, $this->receipt(userId: 'anna'));
		$exception = $this->createMock(DbException::class);
		$exception->method('getReason')->willReturn(DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION);
		$this->receipts->method('record')->willThrowException($exception);

		$this->assertSame('2026-10-27 09:15:00', $this->service()->confirm(userId: 'anna', uuid: self::UUID)['confirmedAt']);
	}//end testARaceAnswersTheWinningRow()

	/**
	 * An announcement that does not ask, or a draft, cannot be confirmed.
	 *
	 * @return void
	 */
	public function testOnlyAPublishedQuestionCanBeConfirmed(): void {
		$this->announcements->method('findFor')->willReturnOnConsecutiveCalls(
			$this->announcement(asks: false),
			$this->announcement(status: 'draft')
		);
		$this->receipts->expects($this->never())->method('record');

		foreach ([1, 2] as $attempt) {
			try {
				$this->service()->confirm(userId: 'anna', uuid: self::UUID);
				$this->fail('Attempt ' . $attempt . ' was accepted');
			} catch (InvalidArgumentException $exception) {
				$this->assertSame('This announcement does not ask for a read confirmation', $exception->getMessage());
			}
		}
	}//end testOnlyAPublishedQuestionCanBeConfirmed()

	/**
	 * An administrator outside the target groups sees it but cannot confirm it.
	 *
	 * @return void
	 */
	public function testSomeoneOutsideTheAudienceCannotConfirm(): void {
		$this->announcements->method('findFor')->willReturn($this->announcement());
		$this->announcements->method('reachesUser')->willReturn(false);
		$this->receipts->expects($this->never())->method('record');

		$this->expectException(ForbiddenException::class);
		$this->service()->confirm(userId: 'admin', uuid: self::UUID);
	}//end testSomeoneOutsideTheAudienceCannotConfirm()

	/**
	 * The card state: which asking announcements the reader confirmed, in one query, and which reports the reader may open.
	 *
	 * @return void
	 */
	public function testTheStatePerListAsksOnce(): void {
		$other = '99999999-2222-4333-8444-555555555555';
		$this->receipts->expects($this->once())->method('findKeysConfirmedBy')
			->with('pieter', ['announcement-' . self::UUID, 'announcement-' . $other])
			->willReturn(['announcement-' . $other]);
		$this->groupManager->method('isAdmin')->willReturn(false);
		$this->announcements->method('isAuthor')->with('pieter')->willReturn(true);

		$state = $this->service()->stateFor(
			userId: 'pieter',
			announcements: [
				$this->announcement(),
				$this->announcement(asks: false, uuid: '00000000-2222-4333-8444-555555555555'),
				$this->announcement(uuid: $other, author: 'marieke'),
			]
		);

		$this->assertSame(['confirmed' => [$other], 'receipts' => [self::UUID]], $state);
	}//end testTheStatePerListAsksOnce()

	/**
	 * The author's report: one row per member of the target groups, confirmed or not yet, with the deadline.
	 *
	 * @return void
	 */
	public function testTheAuthorReadsWhoConfirmed(): void {
		$this->announcements->method('findFor')->willReturn($this->announcement(groups: ['Medewerkers', 'HR']));
		$this->groupManager->method('isAdmin')->with('pieter')->willReturn(true);
		$this->groupManager->method('get')->willReturnCallback(fn (string $groupId): ?IGroup => match ($groupId) {
			'Medewerkers' => $this->group(members: ['jan', 'anna']),
			default => null,
		});
		$this->receipts->method('findByAnnouncement')->with('announcement-' . self::UUID, 1)->willReturn([$this->receipt(userId: 'anna')]);

		$report = $this->service()->report(userId: 'pieter', uuid: self::UUID);

		$this->assertSame('New procedure', $report['title']);
		$this->assertSame('2026-10-30', $report['deadline']);
		$this->assertFalse($report['overdue']);
		$this->assertSame(1, $report['acknowledgedCount']);
		$this->assertSame(['jan'], $report['pending']);
		$this->assertSame(
			[
				['userId' => 'anna', 'status' => 'acknowledged', 'acknowledgedAt' => '2026-10-27 09:15:00'],
				['userId' => 'jan', 'status' => 'pending', 'acknowledgedAt' => null],
			],
			$report['rows']
		);
	}//end testTheAuthorReadsWhoConfirmed()

	/**
	 * An announcement to everyone reports everyone who logged in.
	 *
	 * @return void
	 */
	public function testAnAnnouncementToEveryoneReportsEveryone(): void {
		$this->announcements->method('findFor')->willReturn($this->announcement(groups: []));
		$this->groupManager->method('isAdmin')->willReturn(true);
		$this->userManager->method('callForSeenUsers')->willReturnCallback(function (callable $callback): void {
			foreach (['zoe', 'bram'] as $uid) {
				$callback($this->user(uid: $uid));
			}
		});
		$this->receipts->method('findByAnnouncement')->willReturn([]);

		$this->assertSame(['bram', 'zoe'], $this->service()->report(userId: 'admin', uuid: self::UUID)['pending']);
	}//end testAnAnnouncementToEveryoneReportsEveryone()

	/**
	 * Only the author or an administrator reads the report, and only of an announcement that asks.
	 *
	 * @return void
	 */
	public function testTheReportIsGuarded(): void {
		$this->announcements->method('findFor')->willReturnOnConsecutiveCalls($this->announcement(), $this->announcement(asks: false));
		$this->groupManager->method('isAdmin')->willReturn(false);
		$this->announcements->method('isAuthor')->willReturn(true);

		try {
			$this->service()->report(userId: 'anna', uuid: self::UUID);
			$this->fail('A reader read the report');
		} catch (ForbiddenException) {
			$this->addToAssertionCount(1);
		}

		$this->expectException(InvalidArgumentException::class);
		$this->service()->report(userId: 'pieter', uuid: self::UUID);
	}//end testTheReportIsGuarded()

	/**
	 * A group of the given members.
	 *
	 * @param array<string> $members User ids.
	 *
	 * @return IGroup
	 */
	private function group(array $members): IGroup {
		$group = $this->createMock(IGroup::class);
		$group->method('getUsers')->willReturn(array_map(fn (string $uid): IUser => $this->user(uid: $uid), $members));

		return $group;
	}//end group()

	/**
	 * A user.
	 *
	 * @param string $uid The user id.
	 *
	 * @return IUser
	 */
	private function user(string $uid): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);

		return $user;
	}//end user()
}//end class
