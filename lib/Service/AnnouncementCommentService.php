<?php

/**
 * AnnouncementCommentService
 *
 * Likes and comments on announcements, stored by Nextcloud's comments
 * service (engagement-announcements, REQ-ANN-002, D2).
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
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTimeInterface;
use InvalidArgumentException;
use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\IUserManager;

/**
 * Likes and comments on an announcement through ICommentsManager.
 *
 * Every call first resolves the announcement for the reader through
 * {@see AnnouncementService::findFor()}, so a reader can like, comment on or
 * read the comments of only an announcement that reaches them; anything
 * else is the same "not found" a direct read gives. A like is a comment with
 * the verb `like` and the 👍 message, one per reader; a comment has the verb
 * `comment` and needs the author to have allowed comments. LaunchPad stores
 * neither.
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
 */
class AnnouncementCommentService {

	/**
	 * The comments object type of an announcement.
	 *
	 * @var string
	 */
	public const OBJECT_TYPE = 'launchpad_announcement';

	/**
	 * The verb of a like.
	 *
	 * @var string
	 */
	public const VERB_LIKE = 'like';

	/**
	 * The verb of a comment.
	 *
	 * @var string
	 */
	public const VERB_COMMENT = 'comment';

	/**
	 * The message a like carries.
	 *
	 * @var string
	 */
	public const LIKE_MESSAGE = '👍';

	/**
	 * How many comments one read returns at most.
	 *
	 * @var int
	 */
	public const PAGE = 200;

	/**
	 * Constructor.
	 *
	 * @param AnnouncementService $announcements Resolves an announcement for a reader.
	 * @param ICommentsManager    $comments      Nextcloud's comments service.
	 * @param IUserManager        $users         Display names of commenters.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
	 */
	public function __construct(
		private readonly AnnouncementService $announcements,
		private readonly ICommentsManager $comments,
		private readonly IUserManager $users,
	) {
	}//end __construct()

	/**
	 * The like and comment counts of several announcements, and whether the reader liked each.
	 *
	 * @param string             $userId        The reader.
	 * @param list<Announcement> $announcements Announcements the reader may see.
	 *
	 * @return array<string, array{likes: int, comments: int, liked: bool}> Keyed by uuid.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
	 */
	public function countsFor(string $userId, array $announcements): array {
		$uuids = array_map(static fn (Announcement $announcement): string => $announcement->getUuid(), $announcements);
		if ($uuids === []) {
			return [];
		}

		$likes = $this->comments->getNumberOfCommentsForObjects(self::OBJECT_TYPE, $uuids, null, self::VERB_LIKE);
		$comments = $this->comments->getNumberOfCommentsForObjects(self::OBJECT_TYPE, $uuids, null, self::VERB_COMMENT);
		$counts = [];
		foreach ($uuids as $uuid) {
			$counts[$uuid] = [
				'likes' => (int) ($likes[$uuid] ?? 0),
				'comments' => (int) ($comments[$uuid] ?? 0),
				'liked' => $this->likeOf(userId: $userId, uuid: $uuid) !== null,
			];
		}

		return $counts;
	}//end countsFor()

	/**
	 * Like an announcement; liking it again changes nothing.
	 *
	 * @param string $userId The reader.
	 * @param string $uuid   The announcement.
	 *
	 * @return array{likes: int, liked: bool}
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException When the announcement does not reach the reader.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
	 */
	public function like(string $userId, string $uuid): array {
		$this->readable(userId: $userId, uuid: $uuid);
		if ($this->likeOf(userId: $userId, uuid: $uuid) === null) {
			$this->post(userId: $userId, uuid: $uuid, verb: self::VERB_LIKE, message: self::LIKE_MESSAGE);
		}

		return $this->likeState(userId: $userId, uuid: $uuid);
	}//end like()

	/**
	 * Take back a like; without one this changes nothing.
	 *
	 * @param string $userId The reader.
	 * @param string $uuid   The announcement.
	 *
	 * @return array{likes: int, liked: bool}
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException When the announcement does not reach the reader.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
	 */
	public function unlike(string $userId, string $uuid): array {
		$this->readable(userId: $userId, uuid: $uuid);
		$like = $this->likeOf(userId: $userId, uuid: $uuid);
		if ($like !== null) {
			$this->comments->delete($like->getId());
		}

		return $this->likeState(userId: $userId, uuid: $uuid);
	}//end unlike()

	/**
	 * The comments on an announcement, oldest first.
	 *
	 * @param string $userId The reader.
	 * @param string $uuid   The announcement.
	 *
	 * @return list<array{id: string, actorId: string, displayName: string, message: string, createdAt: string|null}>
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException When the announcement does not reach the reader.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
	 */
	public function commentsOn(string $userId, string $uuid): array {
		$this->readable(userId: $userId, uuid: $uuid);

		return array_map(
			fn (IComment $comment): array => $this->present(comment: $comment),
			$this->withVerb(uuid: $uuid, verb: self::VERB_COMMENT)
		);
	}//end commentsOn()

	/**
	 * Comment on an announcement that allows comments.
	 *
	 * @param string $userId  The reader.
	 * @param string $uuid    The announcement.
	 * @param string $message The comment text.
	 *
	 * @return array{id: string, actorId: string, displayName: string, message: string, createdAt: string|null}
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException When the announcement does not reach the reader.
	 * @throws ForbiddenException       When the author did not allow comments.
	 * @throws InvalidArgumentException When the text is empty or too long.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
	 */
	public function comment(string $userId, string $uuid, string $message): array {
		$announcement = $this->readable(userId: $userId, uuid: $uuid);
		if ($announcement->getAllowComments() !== 1) {
			throw new ForbiddenException(message: 'Comments are closed on this announcement');
		}

		$message = trim($message);
		if ($message === '' || mb_strlen($message) > IComment::MAX_MESSAGE_LENGTH) {
			throw new InvalidArgumentException('A comment needs between 1 and ' . IComment::MAX_MESSAGE_LENGTH . ' characters');
		}

		return $this->present(comment: $this->post(userId: $userId, uuid: $uuid, verb: self::VERB_COMMENT, message: $message));
	}//end comment()

	/**
	 * Remove every like and comment of an announcement, after it is deleted.
	 *
	 * @param string $uuid The announcement.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
	 */
	public function purge(string $uuid): void {
		$this->comments->deleteCommentsAtObject(self::OBJECT_TYPE, $uuid);
	}//end purge()

	/**
	 * The announcement when it is published and reaches the reader; a draft takes no likes or comments.
	 *
	 * @param string $userId The reader.
	 * @param string $uuid   The announcement.
	 *
	 * @return Announcement
	 *
	 * @throws ForbiddenException When the announcement is a draft.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
	 */
	private function readable(string $userId, string $uuid): Announcement {
		$announcement = $this->announcements->findFor(userId: $userId, uuid: $uuid);
		if ($announcement->getStatus() !== Announcement::STATUS_PUBLISHED) {
			throw new ForbiddenException(message: 'A draft takes no likes or comments');
		}

		return $announcement;
	}//end readable()

	/**
	 * The like count of an announcement and whether the reader liked it.
	 *
	 * @param string $userId The reader.
	 * @param string $uuid   The announcement.
	 *
	 * @return array{likes: int, liked: bool}
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
	 */
	private function likeState(string $userId, string $uuid): array {
		return [
			'likes' => (int) $this->comments->getNumberOfCommentsForObject(self::OBJECT_TYPE, $uuid, null, self::VERB_LIKE),
			'liked' => $this->likeOf(userId: $userId, uuid: $uuid) !== null,
		];
	}//end likeState()

	/**
	 * The reader's like of an announcement, if any.
	 *
	 * @param string $userId The reader.
	 * @param string $uuid   The announcement.
	 *
	 * @return IComment|null
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
	 */
	private function likeOf(string $userId, string $uuid): ?IComment {
		foreach ($this->withVerb(uuid: $uuid, verb: self::VERB_LIKE) as $like) {
			if ($like->getActorType() === 'users' && $like->getActorId() === $userId) {
				return $like;
			}
		}

		return null;
	}//end likeOf()

	/**
	 * The comments of one verb on an announcement, oldest first.
	 *
	 * @param string $uuid The announcement.
	 * @param string $verb The verb.
	 *
	 * @return list<IComment>
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
	 */
	private function withVerb(string $uuid, string $verb): array {
		return array_values(
			$this->comments->getCommentsWithVerbForObjectSinceComment(self::OBJECT_TYPE, $uuid, [$verb], 0, 'asc', self::PAGE, false)
		);
	}//end withVerb()

	/**
	 * Store a like or a comment by the reader.
	 *
	 * @param string $userId  The reader.
	 * @param string $uuid    The announcement.
	 * @param string $verb    The verb.
	 * @param string $message The message.
	 *
	 * @return IComment
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
	 */
	private function post(string $userId, string $uuid, string $verb, string $message): IComment {
		$comment = $this->comments->create('users', $userId, self::OBJECT_TYPE, $uuid);
		$comment->setVerb($verb);
		$comment->setMessage($message);
		$this->comments->save($comment);

		return $comment;
	}//end post()

	/**
	 * A comment as the widget reads it.
	 *
	 * @param IComment $comment The comment.
	 *
	 * @return array{id: string, actorId: string, displayName: string, message: string, createdAt: string|null}
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
	 */
	private function present(IComment $comment): array {
		$actorId = (string) $comment->getActorId();
		$created = null;
		if ($comment->getCreationDateTime() instanceof DateTimeInterface) {
			$created = $comment->getCreationDateTime()->format(DateTimeInterface::ATOM);
		}

		return [
			'id' => (string) $comment->getId(),
			'actorId' => $actorId,
			'displayName' => ($this->users->getDisplayName($actorId) ?? $actorId),
			'message' => (string) $comment->getMessage(),
			'createdAt' => $created,
		];
	}//end present()
}//end class
