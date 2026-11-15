<?php

/**
 * AnnouncementCommentServiceTest
 *
 * Likes and comments on announcements through Nextcloud's comments service
 * (engagement-announcements, REQ-ANN-002).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
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

namespace Unit\Service;

use DateTime;
use InvalidArgumentException;
use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Service\AnnouncementCommentService;
use OCA\LaunchPad\Service\AnnouncementService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AnnouncementCommentService.
 */
#[CoversClass(AnnouncementCommentService::class)]
#[UsesClass(Announcement::class)]
class AnnouncementCommentServiceTest extends TestCase {

	private const UUID = '11111111-2222-4333-8444-555555555555';

	/**
	 * The announcement service stand-in.
	 *
	 * @var AnnouncementService&MockObject
	 */
	private AnnouncementService $announcements;

	/**
	 * The comments manager.
	 *
	 * @var ICommentsManager&MockObject
	 */
	private ICommentsManager $comments;

	/**
	 * The service under test.
	 *
	 * @var AnnouncementCommentService
	 */
	private AnnouncementCommentService $service;

	/**
	 * Wire the service with a reachable, published announcement that allows comments.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->announcements = $this->createMock(AnnouncementService::class);
		$this->comments = $this->createMock(ICommentsManager::class);
		$users = $this->createMock(IUserManager::class);
		$users->method('getDisplayName')->willReturnCallback(static fn (string $uid): ?string => ($uid === 'pieter') ? 'Pieter de Vries' : null);
		$this->service = new AnnouncementCommentService(announcements: $this->announcements, comments: $this->comments, users: $users);
	}//end setUp()

	/**
	 * An announcement in the given state.
	 *
	 * @param string $status        The status.
	 * @param int    $allowComments Whether comments are allowed.
	 *
	 * @return Announcement
	 */
	private function announcement(string $status = Announcement::STATUS_PUBLISHED, int $allowComments = 1): Announcement {
		$announcement = new Announcement();
		$announcement->setUuid(self::UUID);
		$announcement->setStatus($status);
		$announcement->setAllowComments($allowComments);
		return $announcement;
	}//end announcement()

	/**
	 * A stored comment.
	 *
	 * @param string $id      The comment id.
	 * @param string $actorId Who wrote it.
	 * @param string $message The text.
	 *
	 * @return IComment&MockObject
	 */
	private function stored(string $id, string $actorId, string $message = '👍'): IComment {
		$comment = $this->createMock(IComment::class);
		$comment->method('getId')->willReturn($id);
		$comment->method('getActorType')->willReturn('users');
		$comment->method('getActorId')->willReturn($actorId);
		$comment->method('getMessage')->willReturn($message);
		$comment->method('getCreationDateTime')->willReturn(new DateTime('2026-11-14T10:00:00+00:00'));
		return $comment;
	}//end stored()

	/**
	 * A first like is stored once as a `like` comment by the reader; liking again stores nothing.
	 *
	 * @return void
	 */
	public function testALikeIsStoredOncePerReader(): void {
		$this->announcements->method('findFor')->with('pieter', self::UUID)->willReturn($this->announcement());
		$new = $this->createMock(IComment::class);
		$new->expects($this->once())->method('setVerb')->with('like');
		$new->expects($this->once())->method('setMessage')->with('👍');
		$this->comments->expects($this->once())->method('create')->with('users', 'pieter', 'launchpad_announcement', self::UUID)->willReturn($new);
		$this->comments->expects($this->once())->method('save')->with($new);
		$this->comments->method('getCommentsWithVerbForObjectSinceComment')
			->with('launchpad_announcement', self::UUID, ['like'], 0, 'asc', 200, false)
			->willReturnOnConsecutiveCalls([], [$this->stored('7', 'pieter')], [$this->stored('7', 'pieter')], [$this->stored('7', 'pieter')]);
		$this->comments->method('getNumberOfCommentsForObject')->with('launchpad_announcement', self::UUID, null, 'like')->willReturn(1);

		$this->assertSame(['likes' => 1, 'liked' => true], $this->service->like(userId: 'pieter', uuid: self::UUID));
		$this->assertSame(['likes' => 1, 'liked' => true], $this->service->like(userId: 'pieter', uuid: self::UUID));
	}//end testALikeIsStoredOncePerReader()

	/**
	 * Unliking deletes the reader's own like, not someone else's.
	 *
	 * @return void
	 */
	public function testUnlikeDeletesOnlyTheReadersLike(): void {
		$this->announcements->method('findFor')->willReturn($this->announcement());
		$this->comments->method('getCommentsWithVerbForObjectSinceComment')
			->willReturnOnConsecutiveCalls([$this->stored('6', 'sanne'), $this->stored('7', 'pieter')], [$this->stored('6', 'sanne')]);
		$this->comments->expects($this->once())->method('delete')->with('7');
		$this->comments->method('getNumberOfCommentsForObject')->willReturn(1);

		$this->assertSame(['likes' => 1, 'liked' => false], $this->service->unlike(userId: 'pieter', uuid: self::UUID));
	}//end testUnlikeDeletesOnlyTheReadersLike()

	/**
	 * A comment is stored with the `comment` verb and the trimmed text, and comes back with the display name.
	 *
	 * @return void
	 */
	public function testACommentIsStoredAndPresented(): void {
		$this->announcements->method('findFor')->willReturn($this->announcement());
		$new = $this->stored('9', 'pieter', 'Komen er ook sta-bureaus?');
		$new->expects($this->once())->method('setVerb')->with('comment');
		$new->expects($this->once())->method('setMessage')->with('Komen er ook sta-bureaus?');
		$this->comments->expects($this->once())->method('create')->with('users', 'pieter', 'launchpad_announcement', self::UUID)->willReturn($new);
		$this->comments->expects($this->once())->method('save')->with($new);

		$this->assertSame(
			['id' => '9', 'actorId' => 'pieter', 'displayName' => 'Pieter de Vries', 'message' => 'Komen er ook sta-bureaus?', 'createdAt' => '2026-11-14T10:00:00+00:00'],
			$this->service->comment(userId: 'pieter', uuid: self::UUID, message: '  Komen er ook sta-bureaus?  ')
		);
	}//end testACommentIsStoredAndPresented()

	/**
	 * Comments are refused when the author closed them, and an empty or too long text is refused; nothing is stored.
	 *
	 * @return void
	 */
	public function testClosedCommentsAndBadTextAreRefused(): void {
		$this->announcements->method('findFor')->willReturnOnConsecutiveCalls($this->announcement(allowComments: 0), $this->announcement(), $this->announcement());
		$this->comments->expects($this->never())->method('save');

		try {
			$this->service->comment(userId: 'pieter', uuid: self::UUID, message: 'Hallo');
			$this->fail('A closed announcement took a comment');
		} catch (ForbiddenException $e) {
			$this->assertSame('Comments are closed on this announcement', $e->getMessage());
		}

		try {
			$this->service->comment(userId: 'pieter', uuid: self::UUID, message: '   ');
			$this->fail('An empty comment was taken');
		} catch (InvalidArgumentException $e) {
			$this->assertSame('A comment needs between 1 and 1000 characters', $e->getMessage());
		}

		$this->expectException(InvalidArgumentException::class);
		$this->service->comment(userId: 'pieter', uuid: self::UUID, message: str_repeat('a', 1001));
	}//end testClosedCommentsAndBadTextAreRefused()

	/**
	 * A draft takes no likes, and an announcement that does not reach the reader stays not found.
	 *
	 * @return void
	 */
	public function testDraftsAndUnreachableAnnouncementsTakeNoLikes(): void {
		$this->announcements->method('findFor')->willReturnCallback(
			fn (string $userId): Announcement => ($userId === 'karin') ? $this->announcement(status: Announcement::STATUS_DRAFT) : throw new DoesNotExistException('Announcement not found')
		);
		$this->comments->expects($this->never())->method('create');

		try {
			$this->service->like(userId: 'karin', uuid: self::UUID);
			$this->fail('A draft took a like');
		} catch (ForbiddenException $e) {
			$this->assertSame('A draft takes no likes or comments', $e->getMessage());
		}

		$this->expectException(DoesNotExistException::class);
		$this->service->like(userId: 'sanne', uuid: self::UUID);
	}//end testDraftsAndUnreachableAnnouncementsTakeNoLikes()

	/**
	 * The counts of several announcements come from one count per verb, with the reader's own like.
	 *
	 * @return void
	 */
	public function testCountsForReadsLikesCommentsAndTheReadersLike(): void {
		$other = $this->announcement();
		$other->setUuid('99999999-2222-4333-8444-555555555555');
		$this->comments->method('getNumberOfCommentsForObjects')->willReturnCallback(
			static fn (string $type, array $ids, ?DateTime $since, string $verb): array => ($verb === 'like') ? [self::UUID => 2] : [self::UUID => 1]
		);
		$this->comments->method('getCommentsWithVerbForObjectSinceComment')->willReturnCallback(
			fn (string $type, string $uuid): array => ($uuid === self::UUID) ? [$this->stored('7', 'pieter')] : []
		);

		$this->assertSame(
			[
				self::UUID => ['likes' => 2, 'comments' => 1, 'liked' => true],
				'99999999-2222-4333-8444-555555555555' => ['likes' => 0, 'comments' => 0, 'liked' => false],
			],
			$this->service->countsFor(userId: 'pieter', announcements: [$this->announcement(), $other])
		);
		$this->assertSame([], $this->service->countsFor(userId: 'pieter', announcements: []));
	}//end testCountsForReadsLikesCommentsAndTheReadersLike()

	/**
	 * Comments are read oldest first with display names, an unknown user keeping the uid; purge clears the object.
	 *
	 * @return void
	 */
	public function testCommentsOnAndPurge(): void {
		$this->announcements->method('findFor')->willReturn($this->announcement());
		$this->comments->method('getCommentsWithVerbForObjectSinceComment')
			->with('launchpad_announcement', self::UUID, ['comment'], 0, 'asc', 200, false)
			->willReturn([$this->stored('3', 'pieter', 'Mooi!'), $this->stored('4', 'gone', 'Ja')]);
		$this->comments->expects($this->once())->method('deleteCommentsAtObject')->with('launchpad_announcement', self::UUID);

		$comments = $this->service->commentsOn(userId: 'pieter', uuid: self::UUID);
		$this->assertSame(['Pieter de Vries', 'gone'], array_column($comments, 'displayName'));
		$this->assertSame(['Mooi!', 'Ja'], array_column($comments, 'message'));

		$this->service->purge(uuid: self::UUID);
	}//end testCommentsOnAndPurge()
}//end class
