<?php

/**
 * AnnouncementReceiptReport
 *
 * The manager's side of an announcement's receipts (REQ-ANN-009, board
 * LpLeesbevestigingen): per person in the audience whether and when they saw
 * and confirmed it and how (check or x), with names and departments, the
 * figures, and the seen and confirmed counts on the Announcements tab's list
 * (REQ-ANN-008). Send reminder is AnnouncementReminder.
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
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTime;
use DateTimeInterface;
use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Db\AnnouncementReceipt;
use OCA\LaunchPad\Db\AnnouncementReceiptMapper;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Kernel\Contracts\AnnouncementServiceInterface;

/**
 * Reports and counts for announcement managers.
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 */
class AnnouncementReceiptReport {

	/**
	 * Seconds between two reminders of one announcement.
	 */
	public const REMINDER_INTERVAL = 86400;

	/**
	 * Constructor.
	 *
	 * @param AnnouncementServiceInterface $announcements The announcements and who manages them.
	 * @param AnnouncementReceiptMapper    $receipts      The stored receipts.
	 * @param AnnouncementAudience         $audience      Who an announcement is for.
	 * @param ReadReceiptPeople            $people        Names and departments on the rows.
	 */
	public function __construct(
		private readonly AnnouncementServiceInterface $announcements,
		private readonly AnnouncementReceiptMapper $receipts,
		private readonly AnnouncementAudience $audience,
		private readonly ReadReceiptPeople $people,
	) {
	}//end __construct()

	/**
	 * Seen and confirmed for one announcement: one row per person in the
	 * audience, and the figures. Keeps the keys of the earlier read
	 * confirmations report (acknowledgedCount, pending, rows[].status) so
	 * the receipts view reads both.
	 *
	 * @param string $userId The manager.
	 * @param string $uuid   The announcement.
	 *
	 * @return array<string, mixed> The report.
	 *
	 * @throws ForbiddenException When the user does not manage announcements.
	 *
	 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
	 */
	public function report(string $userId, string $uuid): array {
		$announcement = $this->managed(userId: $userId, uuid: $uuid);
		$byUser       = [];
		foreach ($this->receipts->findByAnnouncement(uuid: $uuid) as $receipt) {
			$byUser[(string) $receipt->getUserId()] = $receipt;
		}

		$rows    = [];
		$figures = ['seen' => 0, 'check' => 0, 'x' => 0];
		foreach ($this->audience->members(announcement: $announcement) as $member) {
			$row    = $this->row(userId: $member, receipt: $byUser[$member] ?? null);
			$rows[] = $row;
			$figures['seen'] += (int) ($row['seenAt'] !== null);
			if ($row['confirmedVia'] !== null) {
				$figures[$row['confirmedVia']]++;
			}
		}

		$audience  = count($rows);
		$confirmed = ($figures['check'] + $figures['x']);
		$pending   = array_values(array_filter($rows, static fn (array $row): bool => $row['status'] === 'pending'));
		$deadline  = $announcement->getConfirmBefore()?->format('Y-m-d');

		return $this->people->withPeople(report: [
			'uuid'               => $uuid,
			'title'              => $announcement->getTitle(),
			'requiresConfirmation' => $announcement->getRequiresConfirmation() === 1,
			'deadline'           => $deadline,
			'overdue'            => $deadline !== null && $deadline < (new DateTime('today'))->format('Y-m-d'),
			'audienceCount'      => $audience,
			'seenCount'          => $figures['seen'],
			'notSeenCount'       => ($audience - $figures['seen']),
			'confirmedCount'     => $confirmed,
			'confirmedViaCheck'  => $figures['check'],
			'confirmedViaX'      => $figures['x'],
			'notConfirmedCount'  => ($audience - $confirmed),
			'remindedAt'         => $announcement->getRemindedAt()?->format(DateTimeInterface::ATOM),
			'canRemind'          => self::mayRemind(announcement: $announcement),
			'acknowledgedCount'  => $confirmed,
			'pendingCount'       => count($pending),
			'pending'            => array_column($pending, 'userId'),
			'rows'               => $rows,
		], viewerId: $userId);
	}//end report()

	/**
	 * The seen and confirmed counts of the given announcements, for the
	 * Announcements tab's list (REQ-ANN-008).
	 *
	 * @param string[] $uuids The announcements.
	 *
	 * @return array<string, array{seen: int, confirmed: int}> Keyed by uuid; zero for one without receipts.
	 *
	 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
	 */
	public function countsFor(array $uuids): array {
		$stored = $this->receipts->countsFor(uuids: $uuids);
		$counts = [];
		foreach ($uuids as $uuid) {
			$counts[$uuid] = $stored[$uuid] ?? ['seen' => 0, 'confirmed' => 0];
		}

		return $counts;
	}//end countsFor()

	/**
	 * The audience members who have not confirmed the announcement.
	 *
	 * @param Announcement $announcement The announcement.
	 *
	 * @return string[] User ids.
	 *
	 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
	 */
	public function unconfirmed(Announcement $announcement): array {
		$confirmed = [];
		foreach ($this->receipts->findByAnnouncement(uuid: (string) $announcement->getUuid()) as $receipt) {
			if ($receipt->getConfirmedAt() !== null) {
				$confirmed[(string) $receipt->getUserId()] = true;
			}
		}

		return array_values(
			array_filter(
				$this->audience->members(announcement: $announcement),
				static fn (string $member): bool => isset($confirmed[$member]) === false
			)
		);
	}//end unconfirmed()

	/**
	 * The announcement, when the user manages announcements.
	 *
	 * @param string $userId The user.
	 * @param string $uuid   The announcement.
	 *
	 * @return Announcement
	 *
	 * @throws ForbiddenException When the user does not manage announcements.
	 *
	 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
	 */
	public function managed(string $userId, string $uuid): Announcement {
		if ($this->announcements->isAuthor(userId: $userId) === false) {
			throw new ForbiddenException(message: 'Only an announcements manager may read the receipts');
		}

		return $this->announcements->findFor(userId: $userId, uuid: $uuid);
	}//end managed()

	/**
	 * Whether a reminder may be sent now: none yet, or the last one a day ago or more.
	 *
	 * @param Announcement $announcement The announcement.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
	 */
	public static function mayRemind(Announcement $announcement): bool {
		$last = $announcement->getRemindedAt();

		return $last === null || (time() - $last->getTimestamp()) >= self::REMINDER_INTERVAL;
	}//end mayRemind()

	/**
	 * One person's row.
	 *
	 * @param string                   $userId  The person.
	 * @param AnnouncementReceipt|null $receipt Their receipt, if any.
	 *
	 * @return array<string, string|bool|null> userId, status, seen, seenAt, confirmedAt, confirmedVia, acknowledgedAt.
	 */
	private function row(string $userId, ?AnnouncementReceipt $receipt): array {
		$confirmedAt = $receipt?->getConfirmedAt()?->format(DateTimeInterface::ATOM);
		$status      = 'pending';
		if ($confirmedAt !== null) {
			$status = 'acknowledged';
		}

		$seenAt = $receipt?->getSeenAt()?->format(DateTimeInterface::ATOM);
		$via    = null;
		if ($confirmedAt !== null) {
			$via = $receipt?->getConfirmedVia();
		}

		return [
			'userId'         => $userId,
			'status'         => $status,
			'seen'           => $seenAt !== null,
			'seenAt'         => $seenAt,
			'confirmedAt'    => $confirmedAt,
			'confirmedVia'   => $via,
			'acknowledgedAt' => $confirmedAt,
		];
	}//end row()
}//end class
