<?php

/**
 * AnnouncementReadConfirmations
 *
 * The read confirmation of an announcement (REQ-ANN-007): a reader in the
 * audience confirms "I have read this" once, the card knows which ones the
 * reader confirmed, and the author reads who confirmed and who has not yet.
 * The receipts live in the acknowledgements table under the key
 * `announcement-<uuid>`, at content version 1, so they share its uniqueness
 * guard and never collide with a widget placement's key.
 *
 * @category Service
 * @package  OCA\LaunchPad\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use OCA\LaunchPad\Db\Acknowledgement;
use OCA\LaunchPad\Db\AcknowledgementMapper;
use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Kernel\Contracts\AnnouncementServiceInterface;
use OCP\DB\Exception as DbException;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;

/**
 * Confirms, answers and reports the read confirmations of announcements.
 *
 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 */
class AnnouncementReadConfirmations {

	/**
	 * The prefix of an announcement's receipt key.
	 */
	public const KEY_PREFIX = 'announcement-';

	/**
	 * The one content version an announcement's receipts carry.
	 */
	public const CONTENT_VERSION = 1;

	/**
	 * Constructor.
	 *
	 * @param AnnouncementServiceInterface $announcements The announcements a user may read or manage.
	 * @param AcknowledgementMapper        $receipts      The stored receipts.
	 * @param IGroupManager                $groupManager  The members of a target group.
	 * @param IUserManager                 $userManager   Everyone, for an announcement to all.
	 */
	public function __construct(
		private readonly AnnouncementServiceInterface $announcements,
		private readonly AcknowledgementMapper $receipts,
		private readonly IGroupManager $groupManager,
		private readonly IUserManager $userManager,
	) {
	}//end __construct()

	/**
	 * The receipt key of an announcement.
	 *
	 * @param string $uuid The announcement's uuid.
	 *
	 * @return string The key.
	 *
	 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	 */
	public static function key(string $uuid): string {
		return self::KEY_PREFIX . $uuid;
	}//end key()

	/**
	 * "I have read this": record the reader's confirmation once. Only a
	 * published announcement that asks for it, and only a reader it is
	 * addressed to; a second confirmation answers the first.
	 *
	 * @param string $userId The reader.
	 * @param string $uuid   The announcement.
	 *
	 * @return array{uuid: string, confirmed: bool, confirmedAt: string|null}
	 *
	 * @throws InvalidArgumentException When the announcement does not ask for a confirmation.
	 * @throws ForbiddenException       When the reader is not in its audience.
	 *
	 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	 */
	public function confirm(string $userId, string $uuid): array {
		$announcement = $this->announcements->findFor(userId: $userId, uuid: $uuid);
		if ($announcement->getRequiresConfirmation() !== 1 || $announcement->getStatus() !== Announcement::STATUS_PUBLISHED) {
			throw new InvalidArgumentException('This announcement does not ask for a read confirmation');
		}

		if ($this->addresses(announcement: $announcement, userId: $userId) === false) {
			throw new ForbiddenException(message: 'This announcement is not addressed to you');
		}

		$receipt = $this->record(key: self::key(uuid: $uuid), userId: $userId);

		return [
			'uuid'        => $uuid,
			'confirmed'   => true,
			'confirmedAt' => $receipt->getAcknowledgedAtFormatted(),
		];
	}//end confirm()

	/**
	 * Per list of announcements: the ones the reader confirmed, and the
	 * ones whose read confirmations the reader may open (their author or
	 * an administrator), both only among those that ask for one.
	 *
	 * @param string         $userId        The reader.
	 * @param Announcement[] $announcements The announcements on the list.
	 *
	 * @return array{confirmed: string[], receipts: string[]} Uuids.
	 *
	 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	 */
	public function stateFor(string $userId, array $announcements): array {
		$asking = array_values(
			array_filter(
				$announcements,
				static fn (Announcement $announcement): bool => $announcement->getRequiresConfirmation() === 1
			)
		);
		$keys = array_map(
			array: $asking,
			callback: static fn (Announcement $announcement): string => self::key(uuid: (string) $announcement->getUuid())
		);
		$confirmedKeys = $this->receipts->findKeysConfirmedBy(userId: $userId, announcementKeys: $keys);

		$confirmed = [];
		$receipts  = [];
		foreach ($asking as $announcement) {
			$uuid = (string) $announcement->getUuid();
			if (in_array(needle: self::key(uuid: $uuid), haystack: $confirmedKeys, strict: true) === true) {
				$confirmed[] = $uuid;
			}

			if ($this->mayManage(userId: $userId) === true) {
				$receipts[] = $uuid;
			}
		}

		return ['confirmed' => $confirmed, 'receipts' => $receipts];
	}//end stateFor()

	/**
	 * The read confirmation state of everything the reader sees now.
	 *
	 * @param string $userId The reader.
	 *
	 * @return array{confirmed: string[], receipts: string[]} Uuids.
	 *
	 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	 */
	public function stateOfVisible(string $userId): array {
		return $this->stateFor(userId: $userId, announcements: $this->announcements->visibleTo(userId: $userId));
	}//end stateOfVisible()

	/**
	 * The read confirmations of an announcement for its author or an
	 * administrator: one row per person in the audience, confirmed with the
	 * moment or not yet, in the report shape the Read confirmations dialog
	 * reads (REQ-ACK-010); the controller adds names and departments.
	 *
	 * @param string $userId The author or administrator.
	 * @param string $uuid   The announcement.
	 *
	 * @return array<string, mixed> The report.
	 *
	 * @throws ForbiddenException       When the user may not manage the announcement.
	 * @throws InvalidArgumentException When it does not ask for a confirmation.
	 *
	 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	 */
	public function report(string $userId, string $uuid): array {
		$announcement = $this->announcements->findFor(userId: $userId, uuid: $uuid);
		if ($this->mayManage(userId: $userId) === false) {
			throw new ForbiddenException(message: 'Only an announcements manager may read the read confirmations');
		}

		if ($announcement->getRequiresConfirmation() !== 1) {
			throw new InvalidArgumentException('This announcement does not ask for a read confirmation');
		}

		$key       = self::key(uuid: $uuid);
		$confirmed = [];
		foreach ($this->receipts->findByAnnouncement(announcementKey: $key, contentVersion: self::CONTENT_VERSION) as $receipt) {
			$confirmed[(string) $receipt->getUserId()] = $receipt->getAcknowledgedAtFormatted();
		}

		$rows = [];
		foreach ($this->audienceOf(announcement: $announcement) as $member) {
			$status = 'pending';
			if (array_key_exists($member, $confirmed) === true) {
				$status = 'acknowledged';
			}

			$rows[] = [
				'userId'         => $member,
				'status'         => $status,
				'acknowledgedAt' => $confirmed[$member] ?? null,
			];
		}

		$deadline = $announcement->getConfirmBefore()?->format('Y-m-d');
		$done     = array_values(array_filter($rows, static fn (array $row): bool => $row['status'] === 'acknowledged'));
		$pending  = array_values(array_filter($rows, static fn (array $row): bool => $row['status'] === 'pending'));

		return [
			'announcementKey'   => $key,
			'title'             => $announcement->getTitle(),
			'contentVersion'    => self::CONTENT_VERSION,
			'deadline'          => $deadline,
			'overdue'           => $deadline !== null && $deadline < (new DateTimeImmutable('today'))->format('Y-m-d'),
			'acknowledgedCount' => count($done),
			'pendingCount'      => count($pending),
			'pending'           => array_column($pending, 'userId'),
			'acknowledged'      => array_map(
				array: $done,
				callback: static fn (array $row): array => ['userId' => $row['userId'], 'acknowledgedAt' => $row['acknowledgedAt']]
			),
			'rows'              => $rows,
		];
	}//end report()

	/**
	 * Whether an announcement is addressed to a user: no target groups is
	 * everyone, else a member of one of them.
	 *
	 * @param Announcement $announcement The announcement.
	 * @param string       $userId       The user.
	 *
	 * @return bool
	 */
	private function addresses(Announcement $announcement, string $userId): bool {
		return $this->announcements->reachesUser(groups: $announcement->getTargetGroupList(), userId: $userId);
	}//end addresses()

	/**
	 * Whether a user may read an announcement's read confirmations: an
	 * announcements manager, the same people who may change it
	 * (announcements-managed-in-settings D1).
	 *
	 * @param string $userId The user.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
	 */
	private function mayManage(string $userId): bool {
		return $this->announcements->isAuthor(userId: $userId);
	}//end mayManage()

	/**
	 * The audience, sorted: the members of the target groups, or, for an
	 * announcement to everyone, every user who has logged in.
	 *
	 * @param Announcement $announcement The announcement.
	 *
	 * @return string[] User ids.
	 */
	private function audienceOf(Announcement $announcement): array {
		$members = [];
		$targets = $announcement->getTargetGroupList();
		if ($targets === []) {
			$this->userManager->callForSeenUsers(
				callback: static function (IUser $user) use (&$members): bool {
					$members[$user->getUID()] = true;
					return true;
				}
			);
		}

		foreach ($targets as $groupId) {
			foreach ($this->groupManager->get($groupId)?->getUsers() ?? [] as $user) {
				$members[$user->getUID()] = true;
			}
		}

		$audience = array_map('strval', array_keys($members));
		sort($audience);

		return $audience;
	}//end audienceOf()

	/**
	 * Store the receipt once; a concurrent identical write answers the row that won.
	 *
	 * @param string $key    The announcement's key.
	 * @param string $userId The reader.
	 *
	 * @return Acknowledgement The receipt.
	 *
	 * @throws DbException When the insert fails for another reason.
	 */
	private function record(string $key, string $userId): Acknowledgement {
		$existing = $this->receipts->findOneFor(announcementKey: $key, userId: $userId, contentVersion: self::CONTENT_VERSION);
		if ($existing !== null) {
			return $existing;
		}

		try {
			return $this->receipts->record(announcementKey: $key, userId: $userId, contentVersion: self::CONTENT_VERSION);
		} catch (DbException $exception) {
			$winner = $this->receipts->findOneFor(announcementKey: $key, userId: $userId, contentVersion: self::CONTENT_VERSION);
			if ($exception->getReason() !== DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION || $winner === null) {
				throw $exception;
			}

			return $winner;
		}
	}//end record()
}//end class
