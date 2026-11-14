<?php

/**
 * AnnouncementService
 *
 * News items and notices (engagement-announcements). Who may author, who sees
 * what, and the rules a notice must meet, all decided on the server:
 *
 * - Authors are administrators and members of the `announcement_editor_groups`
 *   admin setting (design D6).
 * - A reader sees a published announcement whose target groups are empty or
 *   include one of theirs, from its publish time until its end time (D3).
 *   Anything else, drafts included, is "not found" to them, so an id reveals
 *   nothing.
 * - A draft is visible to its author and to administrators.
 * - A notice needs an end time before it is published (REQ-ANN-005).
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
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTime;
use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Db\AnnouncementMapper;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IGroupManager;
use OCP\IUserManager;

/**
 * Authoring, targeting and following of announcements.
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
 */
class AnnouncementService {

	/**
	 * The admin setting naming the editor groups; the value of
	 * {@see \OCA\LaunchPad\Db\AdminSettingKey::ANNOUNCEMENT_EDITOR_GROUPS}.
	 */
	public const EDITOR_GROUPS_SETTING = 'announcement_editor_groups';

	/**
	 * Constructor.
	 *
	 * @param AnnouncementMapper       $announcements The announcements.
	 * @param AnnouncementInput        $input         Checks and copies an author's fields.
	 * @param AdminSettingMapper       $settings      Reads the editor groups setting.
	 * @param AdminTemplateService     $userGroups    The reader's groups (the one place that asks Nextcloud).
	 * @param IGroupManager            $groupManager  Administrators and group sizes.
	 * @param ITimeFactory             $time          The clock.
	 * @param IUserManager             $userManager   Counts everyone, for an announcement without target groups.
	 */
	public function __construct(
		private readonly AnnouncementMapper $announcements,
		private readonly AnnouncementInput $input,
		private readonly AdminSettingMapper $settings,
		private readonly AdminTemplateService $userGroups,
		private readonly IGroupManager $groupManager,
		private readonly ITimeFactory $time,
		private readonly IUserManager $userManager,
	) {
	}//end __construct()

	/**
	 * Whether a user may write announcements: an administrator or a member of an editor group.
	 *
	 * @param string $userId The user.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function isAuthor(string $userId): bool {
		if ($this->groupManager->isAdmin($userId) === true) {
			return true;
		}

		$editors = $this->settings->getValue(key: self::EDITOR_GROUPS_SETTING, default: []);
		if (is_array($editors) === false || $editors === []) {
			return false;
		}

		return array_intersect($editors, $this->userGroups->getUserGroupIdsFor($userId)) !== [];
	}//end isAuthor()

	/**
	 * The published announcements a reader sees now, newest first.
	 *
	 * @param string $userId The reader.
	 *
	 * @return list<Announcement>
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function visibleTo(string $userId): array {
		$groups = $this->userGroups->getUserGroupIdsFor($userId);

		return array_values(
			array_filter(
				$this->announcements->findLive(now: $this->now()),
				fn (Announcement $announcement): bool => $this->targets(announcement: $announcement, groups: $groups)
			)
		);
	}//end visibleTo()

	/**
	 * The drafts a user may see: every draft for an administrator, their own for an author, none otherwise.
	 *
	 * @param string $userId The user.
	 *
	 * @return list<Announcement>
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function draftsFor(string $userId): array {
		if ($this->groupManager->isAdmin($userId) === true) {
			return $this->announcements->findDrafts(authorId: null);
		}

		if ($this->isAuthor(userId: $userId) === false) {
			return [];
		}

		return $this->announcements->findDrafts(authorId: $userId);
	}//end draftsFor()

	/**
	 * One announcement as a user may see it; anything they may not see is not found.
	 *
	 * @param string $userId The user.
	 * @param string $uuid   The announcement.
	 *
	 * @return Announcement
	 *
	 * @throws DoesNotExistException When it does not exist or the user may not see it.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function findFor(string $userId, string $uuid): Announcement {
		$announcement = $this->announcements->findByUuid(uuid: $uuid);
		if ($this->mayManage(userId: $userId, announcement: $announcement) === true) {
			return $announcement;
		}

		if ($announcement->getStatus() === Announcement::STATUS_PUBLISHED
			&& $this->isLive(announcement: $announcement) === true
			&& $this->targets(announcement: $announcement, groups: $this->userGroups->getUserGroupIdsFor($userId)) === true
		) {
			return $announcement;
		}

		throw new DoesNotExistException('Announcement not found');
	}//end findFor()

	/**
	 * Write a new draft.
	 *
	 * @param string               $userId The author.
	 * @param array<string, mixed> $input  The fields.
	 *
	 * @return Announcement
	 *
	 * @throws ForbiddenException       When the user may not author.
	 * @throws InvalidArgumentException When a field is not valid.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function create(string $userId, array $input): Announcement {
		if ($this->isAuthor(userId: $userId) === false) {
			throw new ForbiddenException(message: 'Only announcement editors may write announcements');
		}

		$announcement = new Announcement();
		$announcement->setUuid($this->uuid());
		$announcement->setStatus(Announcement::STATUS_DRAFT);
		$announcement->setAuthorId($userId);
		$announcement->setKind(Announcement::KIND_NEWS);
		$announcement->setTargetGroupList([]);
		$announcement->setCreatedAt($this->now());
		$this->input->apply(announcement: $announcement, input: $input);
		$announcement->setUpdatedAt($this->now());

		return $this->announcements->insert(entity: $announcement);
	}//end create()

	/**
	 * Change an announcement.
	 *
	 * @param string               $userId The user.
	 * @param string               $uuid   The announcement.
	 * @param array<string, mixed> $input  The fields to change.
	 *
	 * @return Announcement
	 *
	 * @throws DoesNotExistException    When the user may not see it.
	 * @throws ForbiddenException       When the user may see it but not change it.
	 * @throws InvalidArgumentException When a field is not valid.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function update(string $userId, string $uuid, array $input): Announcement {
		$announcement = $this->managed(userId: $userId, uuid: $uuid);
		$this->input->apply(announcement: $announcement, input: $input);
		if ($announcement->getStatus() === Announcement::STATUS_PUBLISHED) {
			$this->assertPublishable(announcement: $announcement);
		}

		$announcement->setUpdatedAt($this->now());

		return $this->announcements->update(entity: $announcement);
	}//end update()

	/**
	 * Publish an announcement; without a publish time it is published now.
	 *
	 * @param string $userId The user.
	 * @param string $uuid   The announcement.
	 *
	 * @return Announcement
	 *
	 * @throws DoesNotExistException    When the user may not see it.
	 * @throws ForbiddenException       When the user may see it but not publish it.
	 * @throws InvalidArgumentException When a notice has no end time.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-notices-show-above-every-targeted-dashboard-for-their-period-req-ann-005
	 */
	public function publish(string $userId, string $uuid): Announcement {
		$announcement = $this->managed(userId: $userId, uuid: $uuid);
		$this->assertPublishable(announcement: $announcement);
		if ($announcement->getPublishAt() === null) {
			$announcement->setPublishAt($this->now());
		}

		$announcement->setStatus(Announcement::STATUS_PUBLISHED);
		$announcement->setUpdatedAt($this->now());

		return $this->announcements->update(entity: $announcement);
	}//end publish()

	/**
	 * Delete an announcement.
	 *
	 * @param string $userId The user.
	 * @param string $uuid   The announcement.
	 *
	 * @return void
	 *
	 * @throws DoesNotExistException When the user may not see it.
	 * @throws ForbiddenException    When the user may see it but not delete it.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function delete(string $userId, string $uuid): void {
		$this->announcements->delete(entity: $this->managed(userId: $userId, uuid: $uuid));
	}//end delete()

	/**
	 * About how many people the target groups reach: everyone when there are none.
	 *
	 * @param list<string> $groups The target groups.
	 *
	 * @return int
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-preview-before-publishing-req-ann-004
	 */
	public function reach(array $groups): int {
		if ($groups === []) {
			$total = $this->userManager->countUsersTotal();
			if ($total === false) {
				return 0;
			}

			return $total;
		}

		$members = [];
		foreach ($groups as $groupId) {
			$group = $this->groupManager->get($groupId);
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
	 * An announcement the user may change.
	 *
	 * @param string $userId The user.
	 * @param string $uuid   The announcement.
	 *
	 * @return Announcement
	 *
	 * @throws DoesNotExistException When the user may not see it.
	 * @throws ForbiddenException    When the user may see it but not change it.
	 */
	private function managed(string $userId, string $uuid): Announcement {
		$announcement = $this->findFor(userId: $userId, uuid: $uuid);
		if ($this->mayManage(userId: $userId, announcement: $announcement) === false) {
			throw new ForbiddenException(message: 'Only the author or an administrator may change this announcement');
		}

		return $announcement;
	}//end managed()

	/**
	 * Whether a user may change an announcement: an administrator, or its author while still an author.
	 *
	 * @param string       $userId       The user.
	 * @param Announcement $announcement The announcement.
	 *
	 * @return bool
	 */
	private function mayManage(string $userId, Announcement $announcement): bool {
		if ($this->groupManager->isAdmin($userId) === true) {
			return true;
		}

		return $announcement->getAuthorId() === $userId && $this->isAuthor(userId: $userId) === true;
	}//end mayManage()

	/**
	 * Whether an announcement targets someone in these groups (no targets: everyone).
	 *
	 * @param Announcement $announcement The announcement.
	 * @param list<string> $groups       The reader's groups.
	 *
	 * @return bool
	 */
	private function targets(Announcement $announcement, array $groups): bool {
		$targets = $announcement->getTargetGroupList();

		return $targets === [] || array_intersect($targets, $groups) !== [];
	}//end targets()

	/**
	 * Whether an announcement is inside its time window now.
	 *
	 * @param Announcement $announcement The announcement.
	 *
	 * @return bool
	 */
	private function isLive(Announcement $announcement): bool {
		$now = $this->now();
		$publishAt = $announcement->getPublishAt();
		$expiresAt = $announcement->getExpiresAt();

		return ($publishAt === null || $publishAt <= $now) && ($expiresAt === null || $expiresAt > $now);
	}//end isLive()

	/**
	 * Refuse to publish what may not be published.
	 *
	 * @param Announcement $announcement The announcement.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When a notice has no end time, or it ends before it starts or before now.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-notices-show-above-every-targeted-dashboard-for-their-period-req-ann-005
	 */
	private function assertPublishable(Announcement $announcement): void {
		if ($announcement->getKind() === Announcement::KIND_NOTICE && $announcement->getExpiresAt() === null) {
			throw new InvalidArgumentException('A notice needs an end time');
		}

		$publishAt = $announcement->getPublishAt();
		$expiresAt = $announcement->getExpiresAt();
		if ($expiresAt !== null && $publishAt !== null && $expiresAt <= $publishAt) {
			throw new InvalidArgumentException('The end time must be after the publish time');
		}

		if ($expiresAt !== null && $expiresAt <= $this->now()) {
			throw new InvalidArgumentException('The end time has already passed');
		}
	}//end assertPublishable()

	/**
	 * Now.
	 *
	 * @return DateTime
	 */
	private function now(): DateTime {
		return $this->time->getDateTime();
	}//end now()

	/**
	 * A v4 uuid.
	 *
	 * @return string
	 */
	private function uuid(): string {
		$data = random_bytes(16);
		$data[6] = chr((ord($data[6]) & 0x0F) | 0x40);
		$data[8] = chr((ord($data[8]) & 0x3F) | 0x80);

		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}//end uuid()
}//end class
