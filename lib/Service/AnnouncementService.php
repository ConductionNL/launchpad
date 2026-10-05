<?php

/**
 * AnnouncementService
 *
 * Authoring, targeting, preview, likes, comments, follows and follower
 * notifications for announcements (engagement-announcements,
 * REQ-ANN-001..005).
 *
 * Targeting is checked on the server for every read: a published
 * announcement is visible to a person when its target groups are empty or
 * share a group with them, and the time is inside its publish window.
 * Drafts are visible to authors only. Likes and comments are stored by
 * Nextcloud's comments service under object type `launchpad_announcement`;
 * LaunchPad keeps no comment rows.
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
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTime;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\AdminSettingKey;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Db\AnnouncementFollowMapper;
use OCA\LaunchPad\Db\AnnouncementMapper;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Announcement service.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) One service owns the whole announcement flow by design (D1-D6).
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) Authoring, reading, reactions and follows share the visibility rule.
 * @SuppressWarnings(PHPMD.TooManyPublicMethods) Each public method backs one endpoint.
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
 */
class AnnouncementService {
	/**
	 * Comments object type for announcements.
	 */
	public const OBJECT_TYPE = 'launchpad_announcement';

	/**
	 * Comment verb of a like (one per person per announcement).
	 */
	public const VERB_LIKE = 'like';

	/**
	 * Comment verb of a reader's comment.
	 */
	public const VERB_COMMENT = 'comment';

	/**
	 * Notification subject sent to followers.
	 */
	public const SUBJECT_PUBLISHED = 'announcement_published';

	private const MAX_TITLE = 255;
	private const MAX_BODY = 20000;
	private const MAX_CATEGORY = 128;
	private const MAX_COMMENT = 1000;
	private const DB_FORMAT = 'Y-m-d H:i:s';

	/**
	 * Constructor.
	 *
	 * @param AnnouncementMapper $announcements Announcement rows.
	 * @param AnnouncementFollowMapper $follows Category follows.
	 * @param AdminSettingMapper $settings Admin settings (editor groups).
	 * @param IGroupManager $groupManager Groups and the admin check.
	 * @param IUserManager $userManager Users.
	 * @param ICommentsManager $comments Nextcloud comments (likes and comments).
	 * @param INotificationManager $notifications Nextcloud notifications.
	 * @param ITimeFactory $time Clock.
	 * @param LoggerInterface $logger Logger.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function __construct(
		private readonly AnnouncementMapper $announcements,
		private readonly AnnouncementFollowMapper $follows,
		private readonly AdminSettingMapper $settings,
		private readonly IGroupManager $groupManager,
		private readonly IUserManager $userManager,
		private readonly ICommentsManager $comments,
		private readonly INotificationManager $notifications,
		private readonly ITimeFactory $time,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The groups whose members may author, besides administrators.
	 *
	 * @return string[]
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function getEditorGroups(): array {
		$raw = $this->settings->getValue(key: AdminSettingKey::ANNOUNCEMENT_EDITOR_GROUPS->value, default: []);
		if (is_array(value: $raw) === false) {
			return [];
		}

		return array_values(array: array_filter(array: $raw, callback: 'is_string'));
	}//end getEditorGroups()

	/**
	 * Replace the editor groups; unknown groups are refused.
	 *
	 * @param array $groups Group ids.
	 *
	 * @return string[] The stored groups.
	 *
	 * @throws InvalidArgumentException When a group does not exist.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function saveEditorGroups(array $groups): array {
		$clean = $this->cleanGroups(groups: $groups);
		$this->settings->setSetting(key: AdminSettingKey::ANNOUNCEMENT_EDITOR_GROUPS->value, value: $clean);

		return $clean;
	}//end saveEditorGroups()

	/**
	 * Whether a person may write announcements: administrators and members
	 * of the editor groups (D6).
	 *
	 * @param string $userId The person.
	 *
	 * @return boolean
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function canAuthor(string $userId): bool {
		if ($this->groupManager->isAdmin(userId: $userId) === true) {
			return true;
		}

		$editors = $this->getEditorGroups();

		return $editors !== [] && array_intersect($editors, $this->groupsOf(userId: $userId)) !== [];
	}//end canAuthor()

	/**
	 * Announcements a person sees now, newest first, with like and comment
	 * counts and whether they liked each.
	 *
	 * @param string $userId The reader.
	 * @param string|null $kind Only this kind (news or notice), or all.
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function listVisible(string $userId, ?string $kind = null): array {
		$groups = $this->groupsOf(userId: $userId);
		$items  = [];
		foreach ($this->announcements->findLive(now: $this->now()) as $announcement) {
			if ($kind !== null && $announcement->getKind() !== $kind) {
				continue;
			}

			if ($this->targets(announcement: $announcement, groups: $groups) === true) {
				$items[] = $announcement;
			}
		}

		return $this->withReactions(items: $items, userId: $userId);
	}//end listVisible()

	/**
	 * Every announcement, drafts included, for an author.
	 *
	 * @param string $userId The author.
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @throws ForbiddenException When the person may not author.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function listForAuthor(string $userId): array {
		$this->requireAuthor(userId: $userId);

		return $this->withReactions(items: $this->announcements->findAllNewestFirst(), userId: $userId);
	}//end listForAuthor()

	/**
	 * One announcement the person may see. An announcement that does not
	 * target them, is outside its window or is a draft they may not author
	 * reads as not found, so its existence does not leak (no IDOR).
	 *
	 * @param string $userId The reader.
	 * @param string $uuid The announcement.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws DoesNotExistException When the person may not see it.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function get(string $userId, string $uuid): array {
		return $this->withReactions(items: [$this->findVisible(userId: $userId, uuid: $uuid)], userId: $userId)[0];
	}//end get()

	/**
	 * Create a draft.
	 *
	 * @param string $userId The author.
	 * @param array $data Fields: kind, title, body, category, level, dismissible,
	 *                    targetGroups, publishAt, expiresAt, allowComments.
	 *
	 * @return Announcement
	 *
	 * @throws ForbiddenException When the person may not author.
	 * @throws InvalidArgumentException When a field is invalid.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function create(string $userId, array $data): Announcement {
		$this->requireAuthor(userId: $userId);

		$announcement = new Announcement();
		$now          = $this->now();
		// Entity setters take their argument positionally.
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$announcement->setUuid($this->uuid());
		$announcement->setAuthorId($userId);
		$announcement->setStatus(Announcement::STATUS_DRAFT);
		$announcement->setCreatedAt($now);
		$announcement->setUpdatedAt($now);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$this->apply(announcement: $announcement, data: $data, isNew: true);

		return $this->announcements->insert(entity: $announcement);
	}//end create()

	/**
	 * Update an announcement (draft or published).
	 *
	 * @param string $userId The author.
	 * @param string $uuid The announcement.
	 * @param array $data Fields to change.
	 *
	 * @return Announcement
	 *
	 * @throws ForbiddenException When the person may not author.
	 * @throws DoesNotExistException When there is none.
	 * @throws InvalidArgumentException When a field is invalid.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function update(string $userId, string $uuid, array $data): Announcement {
		$this->requireAuthor(userId: $userId);
		$announcement = $this->announcements->findByUuid(uuid: $uuid);
		$this->apply(announcement: $announcement, data: $data, isNew: false);
		if ($announcement->getStatus() === Announcement::STATUS_PUBLISHED) {
			$this->requireEndForNotice(announcement: $announcement);
		}

		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$announcement->setUpdatedAt($this->now());

		return $this->announcements->update(entity: $announcement);
	}//end update()

	/**
	 * Publish an announcement. A notice needs an end time. Without a publish
	 * time it is published now; followers of a now-visible announcement are
	 * notified at once, a scheduled one by the background job (D5).
	 *
	 * @param string $userId The author.
	 * @param string $uuid The announcement.
	 *
	 * @return Announcement
	 *
	 * @throws ForbiddenException When the person may not author.
	 * @throws DoesNotExistException When there is none.
	 * @throws InvalidArgumentException When a notice has no end time.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function publish(string $userId, string $uuid): Announcement {
		$this->requireAuthor(userId: $userId);
		$announcement = $this->announcements->findByUuid(uuid: $uuid);
		$this->requireEndForNotice(announcement: $announcement);

		$now = $this->now();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		if ($announcement->getPublishAt() === null) {
			$announcement->setPublishAt($now);
		}

		$announcement->setStatus(Announcement::STATUS_PUBLISHED);
		$announcement->setUpdatedAt($now);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$announcement = $this->announcements->update(entity: $announcement);

		if ($announcement->getPublishAt() <= $now) {
			$this->notifyFollowers(announcement: $announcement);
		}

		return $announcement;
	}//end publish()

	/**
	 * Delete an announcement with its likes and comments.
	 *
	 * @param string $userId The author.
	 * @param string $uuid The announcement.
	 *
	 * @return void
	 *
	 * @throws ForbiddenException When the person may not author.
	 * @throws DoesNotExistException When there is none.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function delete(string $userId, string $uuid): void {
		$this->requireAuthor(userId: $userId);
		$announcement = $this->announcements->findByUuid(uuid: $uuid);
		$this->comments->deleteCommentsAtObject(objectType: self::OBJECT_TYPE, objectId: $announcement->getUuid());
		$this->announcements->delete(entity: $announcement);
	}//end delete()

	/**
	 * How many people the targeting reaches: everyone, or the distinct
	 * members of the target groups (D4).
	 *
	 * @param string $userId The author asking.
	 * @param array $groups Target group ids; empty means everyone.
	 *
	 * @return integer
	 *
	 * @throws ForbiddenException When the person may not author.
	 * @throws InvalidArgumentException When a group does not exist.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function reach(string $userId, array $groups): int {
		$this->requireAuthor(userId: $userId);
		$clean = $this->cleanGroups(groups: $groups);
		if ($clean === []) {
			$total = 0;
			foreach ($this->userManager->countUsers() as $count) {
				$total += (int) $count;
			}

			return $total;
		}

		$members = [];
		foreach ($clean as $groupId) {
			$group = $this->groupManager->get(gid: $groupId);
			if ($group === null) {
				continue;
			}

			foreach ($group->getUsers() as $user) {
				$members[$user->getUID()] = true;
			}
		}

		return count($members);
	}//end reach()

	/**
	 * "Preview as": whether the targeting of an announcement reaches the
	 * given person, ignoring status and time so a draft can be checked.
	 *
	 * @param string $userId The author asking.
	 * @param array $groups Target group ids; empty means everyone.
	 * @param string $readerId The person to check.
	 *
	 * @return boolean
	 *
	 * @throws ForbiddenException When the person may not author.
	 * @throws InvalidArgumentException When the person does not exist.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function reachesPerson(string $userId, array $groups, string $readerId): bool {
		$this->requireAuthor(userId: $userId);
		if ($this->userManager->userExists(uid: $readerId) === false) {
			throw new InvalidArgumentException(message: 'Unknown user');
		}

		$clean = $this->cleanGroups(groups: $groups);

		return $clean === [] || array_intersect($clean, $this->groupsOf(userId: $readerId)) !== [];
	}//end reachesPerson()

	/**
	 * Like or unlike an announcement the person sees.
	 *
	 * @param string $userId The reader.
	 * @param string $uuid The announcement.
	 * @param boolean $liked True to like, false to take the like back.
	 *
	 * @return array<string, mixed> The announcement with fresh counts.
	 *
	 * @throws DoesNotExistException When the person may not see it.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function setLiked(string $userId, string $uuid, bool $liked): array {
		$announcement = $this->findVisible(userId: $userId, uuid: $uuid);
		$own          = $this->ownLike(userId: $userId, uuid: $uuid);
		if ($liked === true && $own === null) {
			$like = $this->comments->create(actorType: 'users', actorId: $userId, objectType: self::OBJECT_TYPE, objectId: $uuid);
			$like->setVerb(verb: self::VERB_LIKE);
			$like->setMessage(message: '👍');
			$this->comments->save(comment: $like);
		}

		if ($liked === false && $own !== null) {
			$this->comments->delete(id: $own->getId());
		}

		return $this->withReactions(items: [$announcement], userId: $userId)[0];
	}//end setLiked()

	/**
	 * Comment on an announcement the person sees and that allows comments.
	 *
	 * @param string $userId The reader.
	 * @param string $uuid The announcement.
	 * @param string $message The comment.
	 *
	 * @return array<string, mixed> The stored comment.
	 *
	 * @throws DoesNotExistException When the person may not see it.
	 * @throws ForbiddenException When comments are off.
	 * @throws InvalidArgumentException When the message is empty or too long.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function addComment(string $userId, string $uuid, string $message): array {
		$announcement = $this->findVisible(userId: $userId, uuid: $uuid);
		if ($announcement->getAllowComments() !== 1) {
			throw new ForbiddenException(message: 'Comments are off for this announcement');
		}

		$message = trim(string: $message);
		if ($message === '' || mb_strlen(string: $message) > self::MAX_COMMENT) {
			throw new InvalidArgumentException(message: 'A comment needs between 1 and 1000 characters');
		}

		$comment = $this->comments->create(actorType: 'users', actorId: $userId, objectType: self::OBJECT_TYPE, objectId: $uuid);
		$comment->setVerb(verb: self::VERB_COMMENT);
		$comment->setMessage(message: $message, maxLength: self::MAX_COMMENT);
		$this->comments->save(comment: $comment);

		return $this->serialiseComment(comment: $comment);
	}//end addComment()

	/**
	 * The comments on an announcement the person sees, oldest first.
	 *
	 * @param string $userId The reader.
	 * @param string $uuid The announcement.
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @throws DoesNotExistException When the person may not see it.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function listComments(string $userId, string $uuid): array {
		$this->findVisible(userId: $userId, uuid: $uuid);
		$list = [];
		foreach ($this->comments->getForObject(objectType: self::OBJECT_TYPE, objectId: $uuid) as $comment) {
			if ($comment->getVerb() === self::VERB_COMMENT) {
				$list[] = $this->serialiseComment(comment: $comment);
			}
		}

		return array_reverse(array: $list);
	}//end listComments()

	/**
	 * The categories a person follows.
	 *
	 * @param string $userId The person.
	 *
	 * @return string[]
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function followedCategories(string $userId): array {
		return $this->follows->findCategoriesOf(userId: $userId);
	}//end followedCategories()

	/**
	 * Follow or unfollow a category.
	 *
	 * @param string $userId The person.
	 * @param string $category The category.
	 * @param boolean $follow True to follow, false to stop.
	 *
	 * @return string[] The categories the person now follows.
	 *
	 * @throws InvalidArgumentException When the category is empty or too long.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function setFollowing(string $userId, string $category, bool $follow): array {
		$category = trim(string: $category);
		if ($category === '' || mb_strlen(string: $category) > self::MAX_CATEGORY) {
			throw new InvalidArgumentException(message: 'A category needs between 1 and 128 characters');
		}

		if ($follow === true) {
			$this->follows->follow(userId: $userId, category: $category);
		}

		if ($follow === false) {
			$this->follows->unfollow(userId: $userId, category: $category);
		}

		return $this->follows->findCategoriesOf(userId: $userId);
	}//end setFollowing()

	/**
	 * Notify the followers of every announcement that became visible and was
	 * not notified yet (the background job, D5).
	 *
	 * @return integer Notifications sent.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function notifyDue(): int {
		$sent = 0;
		foreach ($this->announcements->findDueForNotification(now: $this->now()) as $announcement) {
			$sent += $this->notifyFollowers(announcement: $announcement);
		}

		return $sent;
	}//end notifyDue()

	/**
	 * Send `announcement_published` once to every follower of the
	 * announcement's category who is in its target groups, then mark it
	 * notified so the job skips it.
	 *
	 * @param Announcement $announcement The visible announcement.
	 *
	 * @return integer Notifications sent.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function notifyFollowers(Announcement $announcement): int {
		if ($announcement->getNotifiedAt() !== null) {
			return 0;
		}

		$sent     = 0;
		$category = $announcement->getCategory();
		if ($category !== null && $category !== '') {
			foreach ($this->follows->findFollowersOf(category: $category) as $followerId) {
				if ($this->userManager->userExists(uid: $followerId) === false
					|| $this->targets(announcement: $announcement, groups: $this->groupsOf(userId: $followerId)) === false
				) {
					continue;
				}

				$sent += $this->sendNotification(announcement: $announcement, recipientId: $followerId);
			}
		}

		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$announcement->setNotifiedAt($this->now());
		$this->announcements->update(entity: $announcement);

		return $sent;
	}//end notifyFollowers()

	/**
	 * Send one notification; a failing notification is logged, not thrown.
	 *
	 * @param Announcement $announcement The announcement.
	 * @param string $recipientId The follower.
	 *
	 * @return integer 1 when sent, 0 when it failed.
	 */
	private function sendNotification(Announcement $announcement, string $recipientId): int {
		try {
			$notification = $this->notifications->createNotification();
			$notification->setApp(app: Application::APP_ID)
				->setUser(user: $recipientId)
				->setDateTime(dateTime: new DateTime())
				->setObject(type: 'announcement', id: $announcement->getUuid())
				->setSubject(
					subject: self::SUBJECT_PUBLISHED,
					parameters: [(string) $announcement->getCategory(), $announcement->getTitle()]
				);
			$this->notifications->notify(notification: $notification);

			return 1;
		} catch (Throwable $e) {
			$this->logger->warning(
				message: 'launchpad: announcement notification failed: ' . $e->getMessage(),
				context: ['app' => Application::APP_ID]
			);

			return 0;
		}
	}//end sendNotification()

	/**
	 * Find an announcement the person may see, or throw not found.
	 *
	 * @param string $userId The reader.
	 * @param string $uuid The announcement.
	 *
	 * @return Announcement
	 *
	 * @throws DoesNotExistException When the person may not see it.
	 */
	private function findVisible(string $userId, string $uuid): Announcement {
		$announcement = $this->announcements->findByUuid(uuid: $uuid);
		if ($this->isLive(announcement: $announcement) === true
			&& $this->targets(announcement: $announcement, groups: $this->groupsOf(userId: $userId)) === true
		) {
			return $announcement;
		}

		if ($this->canAuthor(userId: $userId) === true) {
			return $announcement;
		}

		throw new DoesNotExistException(msg: 'Announcement not found');
	}//end findVisible()

	/**
	 * Published and inside its window now.
	 *
	 * @param Announcement $announcement The announcement.
	 *
	 * @return boolean
	 */
	private function isLive(Announcement $announcement): bool {
		$now = $this->now();

		return $announcement->getStatus() === Announcement::STATUS_PUBLISHED
			&& $announcement->getPublishAt() !== null
			&& $announcement->getPublishAt() <= $now
			&& ($announcement->getExpiresAt() === null || $announcement->getExpiresAt() > $now);
	}//end isLive()

	/**
	 * Whether the announcement targets someone in these groups.
	 *
	 * @param Announcement $announcement The announcement.
	 * @param string[] $groups The person's groups.
	 *
	 * @return boolean
	 */
	private function targets(Announcement $announcement, array $groups): bool {
		$targets = $announcement->getTargetGroupList();

		return $targets === [] || array_intersect($targets, $groups) !== [];
	}//end targets()

	/**
	 * Add like and comment counts and the reader's own like.
	 *
	 * @param Announcement[] $items The announcements.
	 * @param string $userId The reader.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function withReactions(array $items, string $userId): array {
		if ($items === []) {
			return [];
		}

		$uuids    = array_map(callback: static fn (Announcement $item): string => $item->getUuid(), array: $items);
		$likes    = $this->comments->getNumberOfCommentsForObjects(objectType: self::OBJECT_TYPE, objectIds: $uuids, notOlderThan: null, verb: self::VERB_LIKE);
		$comments = $this->comments->getNumberOfCommentsForObjects(objectType: self::OBJECT_TYPE, objectIds: $uuids, notOlderThan: null, verb: self::VERB_COMMENT);

		$out = [];
		foreach ($items as $item) {
			$row = $item->jsonSerialize();
			$row['likeCount']    = (int) ($likes[$item->getUuid()] ?? 0);
			$row['commentCount'] = (int) ($comments[$item->getUuid()] ?? 0);
			$row['likedByMe']    = ($this->ownLike(userId: $userId, uuid: $item->getUuid()) !== null);
			$out[] = $row;
		}

		return $out;
	}//end withReactions()

	/**
	 * The reader's own like on an announcement, if any.
	 *
	 * @param string $userId The reader.
	 * @param string $uuid The announcement.
	 *
	 * @return IComment|null
	 */
	private function ownLike(string $userId, string $uuid): ?IComment {
		foreach ($this->comments->getForObject(objectType: self::OBJECT_TYPE, objectId: $uuid) as $comment) {
			if ($comment->getVerb() === self::VERB_LIKE && $comment->getActorType() === 'users' && $comment->getActorId() === $userId) {
				return $comment;
			}
		}

		return null;
	}//end ownLike()

	/**
	 * Serialise a comment for the API.
	 *
	 * @param IComment $comment The comment.
	 *
	 * @return array<string, mixed>
	 */
	private function serialiseComment(IComment $comment): array {
		$actorId = $comment->getActorId();
		$user    = $this->userManager->get(uid: $actorId);

		return [
			'id'          => $comment->getId(),
			'actorId'     => $actorId,
			'displayName' => ($user?->getDisplayName() ?? $actorId),
			'message'     => $comment->getMessage(),
			'createdAt'   => $comment->getCreationDateTime()->format(format: DATE_ATOM),
		];
	}//end serialiseComment()

	/**
	 * Apply and validate the editable fields.
	 *
	 * @param Announcement $announcement The announcement.
	 * @param array $data The fields.
	 * @param boolean $isNew Whether the title is required.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When a field is invalid.
	 *
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity) One guarded branch per field.
	 * @SuppressWarnings(PHPMD.NPathComplexity) One guarded branch per field.
	 */
	private function apply(Announcement $announcement, array $data, bool $isNew): void {
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		if (array_key_exists('kind', $data) === true) {
			$kind = (string) $data['kind'];
			if (in_array($kind, [Announcement::KIND_NEWS, Announcement::KIND_NOTICE], true) === false) {
				throw new InvalidArgumentException('Kind must be news or notice');
			}

			$announcement->setKind($kind);
		}

		if (array_key_exists('title', $data) === true || $isNew === true) {
			$title = trim((string) ($data['title'] ?? ''));
			if ($title === '' || mb_strlen($title) > self::MAX_TITLE) {
				throw new InvalidArgumentException('A title needs between 1 and 255 characters');
			}

			$announcement->setTitle($title);
		}

		if (array_key_exists('body', $data) === true) {
			$body = (string) ($data['body'] ?? '');
			if (mb_strlen($body) > self::MAX_BODY) {
				throw new InvalidArgumentException('The text is too long');
			}

			$announcement->setBody($body);
		}

		if (array_key_exists('category', $data) === true) {
			$category = trim((string) ($data['category'] ?? ''));
			if (mb_strlen($category) > self::MAX_CATEGORY) {
				throw new InvalidArgumentException('A category needs at most 128 characters');
			}

			$announcement->setCategory($category === '' ? null : $category);
		}

		if (array_key_exists('level', $data) === true) {
			$level = (string) $data['level'];
			if (in_array($level, [Announcement::LEVEL_INFO, Announcement::LEVEL_WARNING], true) === false) {
				throw new InvalidArgumentException('Level must be info or warning');
			}

			$announcement->setLevel($level);
		}

		if (array_key_exists('dismissible', $data) === true) {
			$announcement->setDismissible((bool) $data['dismissible'] === true ? 1 : 0);
		}

		if (array_key_exists('allowComments', $data) === true) {
			$announcement->setAllowComments((bool) $data['allowComments'] === true ? 1 : 0);
		}

		if (array_key_exists('targetGroups', $data) === true) {
			$groups = is_array($data['targetGroups']) === true ? $data['targetGroups'] : [];
			$announcement->setTargetGroups(json_encode($this->cleanGroups(groups: $groups)));
		}

		if (array_key_exists('publishAt', $data) === true) {
			$announcement->setPublishAt($this->parseTime(value: $data['publishAt']));
		}

		if (array_key_exists('expiresAt', $data) === true) {
			$announcement->setExpiresAt($this->parseTime(value: $data['expiresAt']));
		}

		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$publishAt = $announcement->getPublishAt();
		$expiresAt = $announcement->getExpiresAt();
		if ($publishAt !== null && $expiresAt !== null && $expiresAt <= $publishAt) {
			throw new InvalidArgumentException(message: 'The end time must be after the publish time');
		}
	}//end apply()

	/**
	 * A notice must have an end time (REQ-ANN-005).
	 *
	 * @param Announcement $announcement The announcement.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When a notice has no end time.
	 */
	private function requireEndForNotice(Announcement $announcement): void {
		if ($announcement->getKind() === Announcement::KIND_NOTICE && $announcement->getExpiresAt() === null) {
			throw new InvalidArgumentException(message: 'A notice needs an end time');
		}
	}//end requireEndForNotice()

	/**
	 * Refuse a person who may not author.
	 *
	 * @param string $userId The person.
	 *
	 * @return void
	 *
	 * @throws ForbiddenException When the person may not author.
	 */
	private function requireAuthor(string $userId): void {
		if ($this->canAuthor(userId: $userId) === false) {
			throw new ForbiddenException(message: 'Only announcement editors may do this');
		}
	}//end requireAuthor()

	/**
	 * Keep existing, distinct group ids.
	 *
	 * @param array $groups Raw group ids.
	 *
	 * @return string[]
	 *
	 * @throws InvalidArgumentException When a group does not exist.
	 */
	private function cleanGroups(array $groups): array {
		$clean = [];
		foreach ($groups as $groupId) {
			if (is_string(value: $groupId) === false || $groupId === '') {
				continue;
			}

			if ($this->groupManager->groupExists(gid: $groupId) === false) {
				throw new InvalidArgumentException(message: 'Unknown group: ' . $groupId);
			}

			$clean[$groupId] = true;
		}

		return array_keys(array: $clean);
	}//end cleanGroups()

	/**
	 * A person's group ids.
	 *
	 * @param string $userId The person.
	 *
	 * @return string[]
	 */
	private function groupsOf(string $userId): array {
		$user = $this->userManager->get(uid: $userId);
		if ($user === null) {
			return [];
		}

		return $this->groupManager->getUserGroupIds(user: $user);
	}//end groupsOf()

	/**
	 * Parse an ISO 8601 time to the stored UTC format; empty means none.
	 *
	 * @param mixed $value The time.
	 *
	 * @return string|null
	 *
	 * @throws InvalidArgumentException When it is not a time.
	 */
	private function parseTime(mixed $value): ?string {
		if ($value === null || $value === '') {
			return null;
		}

		try {
			$time = new DateTime(datetime: (string) $value, timezone: new DateTimeZone(timezone: 'UTC'));
		} catch (Exception) {
			throw new InvalidArgumentException(message: 'Not a valid time: ' . (string) $value);
		}

		return $time->setTimezone(timezone: new DateTimeZone(timezone: 'UTC'))->format(format: self::DB_FORMAT);
	}//end parseTime()

	/**
	 * Now in the stored UTC format.
	 *
	 * @return string
	 */
	private function now(): string {
		return gmdate(format: self::DB_FORMAT, timestamp: $this->time->getTime());
	}//end now()

	/**
	 * A random version 4 uuid.
	 *
	 * @return string
	 */
	private function uuid(): string {
		$data    = random_bytes(length: 16);
		$data[6] = chr(codepoint: ((ord(character: $data[6]) & 0x0F) | 0x40));
		$data[8] = chr(codepoint: ((ord(character: $data[8]) & 0x3F) | 0x80));

		return vsprintf(format: '%s%s-%s-%s-%s-%s%s%s', values: str_split(string: bin2hex(string: $data), length: 4));
	}//end uuid()
}//end class
