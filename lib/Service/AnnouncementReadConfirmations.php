<?php

/**
 * AnnouncementReadConfirmations
 *
 * The reader's side of an announcement's receipts (REQ-ANN-007, REQ-ANN-009,
 * announcements-managed-in-settings D3, D4): the first time an announcement is
 * on the reader's screen it is recorded as seen (batched, after render); the
 * check and the x both confirm it, once, keeping the first time and kind; and
 * the widget and the banner learn which ones the reader has seen, confirmed
 * and closed. Every published announcement addressed to the reader takes a
 * confirmation, whether or not its author asked for one.
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
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTime;
use DateTimeInterface;
use InvalidArgumentException;
use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Db\AnnouncementReceipt;
use OCA\LaunchPad\Db\AnnouncementReceiptMapper;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Kernel\Contracts\AnnouncementServiceInterface;
use OCP\DB\Exception as DbException;

/**
 * Records and answers a reader's seen and confirmed receipts.
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 */
class AnnouncementReadConfirmations {

	/**
	 * The most announcements one seen request records.
	 */
	public const MAX_SEEN_BATCH = 100;

	/**
	 * Constructor.
	 *
	 * @param AnnouncementServiceInterface $announcements The announcements a user may read or manage.
	 * @param AnnouncementReceiptMapper    $receipts      The stored receipts.
	 */
	public function __construct(
		private readonly AnnouncementServiceInterface $announcements,
		private readonly AnnouncementReceiptMapper $receipts,
	) {
	}//end __construct()

	/**
	 * The check or the x: record the reader's confirmation once. Only a
	 * published announcement, only a reader it is addressed to; a second
	 * confirmation answers the first, with its time and kind.
	 *
	 * @param string $userId The reader.
	 * @param string $uuid   The announcement.
	 * @param string $via    check or x.
	 *
	 * @return array{uuid: string, confirmed: bool, confirmedAt: string|null, confirmedVia: string|null}
	 *
	 * @throws InvalidArgumentException When via is unknown or the announcement is not published.
	 * @throws ForbiddenException       When the reader is not in its audience.
	 *
	 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	 */
	public function confirm(string $userId, string $uuid, string $via=AnnouncementReceipt::VIA_CHECK): array {
		if (in_array(needle: $via, haystack: [AnnouncementReceipt::VIA_CHECK, AnnouncementReceipt::VIA_X], strict: true) === false) {
			throw new InvalidArgumentException('Confirm with check or x');
		}

		$announcement = $this->announcements->findFor(userId: $userId, uuid: $uuid);
		if ($announcement->getStatus() !== Announcement::STATUS_PUBLISHED) {
			throw new InvalidArgumentException('Only a published announcement can be confirmed');
		}

		if ($this->announcements->reachesUser(groups: $announcement->getTargetGroupList(), userId: $userId) === false) {
			throw new ForbiddenException(message: 'This announcement is not addressed to you');
		}

		$receipt = $this->receipts->findOne(uuid: $uuid, userId: $userId);
		if ($receipt === null || $receipt->getConfirmedAt() === null) {
			$receipt = $this->store(receipt: $receipt, uuid: $uuid, userId: $userId, via: $via);
		}

		return [
			'uuid'         => $uuid,
			'confirmed'    => true,
			'confirmedAt'  => $receipt->getConfirmedAt()?->format(DateTimeInterface::ATOM),
			'confirmedVia' => $receipt->getConfirmedVia(),
		];
	}//end confirm()

	/**
	 * Record that the given announcements were on the reader's screen: once
	 * each, only those the reader may see now; others are ignored.
	 *
	 * @param string   $userId The reader.
	 * @param string[] $uuids  The announcements that came into view.
	 *
	 * @return string[] The uuids recorded as seen by this call.
	 *
	 * @throws InvalidArgumentException When more than MAX_SEEN_BATCH uuids are sent.
	 *
	 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
	 */
	public function markSeen(string $userId, array $uuids): array {
		$uuids = array_values(array_unique(array_filter(array_map('strval', $uuids), static fn (string $uuid): bool => $uuid !== '')));
		if (count($uuids) > self::MAX_SEEN_BATCH) {
			throw new InvalidArgumentException('At most '.self::MAX_SEEN_BATCH.' announcements per request');
		}

		if ($uuids === []) {
			return [];
		}

		$visible = array_map(
			static fn (Announcement $announcement): string => (string) $announcement->getUuid(),
			$this->announcements->visibleTo(userId: $userId)
		);
		$wanted  = array_values(array_intersect($uuids, $visible));
		$known   = [];
		foreach ($this->receipts->findByUserAmong(userId: $userId, uuids: $wanted) as $receipt) {
			$known[(string) $receipt->getAnnouncementUuid()] = $receipt;
		}

		$recorded = [];
		foreach ($wanted as $uuid) {
			$receipt = $known[$uuid] ?? null;
			if ($receipt !== null && $receipt->getSeenAt() !== null) {
				continue;
			}

			$this->store(receipt: $receipt, uuid: $uuid, userId: $userId, via: null);
			$recorded[] = $uuid;
		}

		return $recorded;
	}//end markSeen()

	/**
	 * Per list of announcements: the ones the reader has seen, confirmed
	 * (either way) and closed with the x, and those whose receipts the
	 * reader may open (an announcements manager).
	 *
	 * @param string         $userId        The reader.
	 * @param Announcement[] $announcements The announcements on the list.
	 *
	 * @return array{seen: string[], confirmed: string[], closed: string[], receipts: string[]} Uuids.
	 *
	 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	 */
	public function stateFor(string $userId, array $announcements): array {
		$uuids = array_map(static fn (Announcement $announcement): string => (string) $announcement->getUuid(), $announcements);
		$state = ['seen' => [], 'confirmed' => [], 'closed' => [], 'receipts' => []];
		foreach ($this->receipts->findByUserAmong(userId: $userId, uuids: $uuids) as $receipt) {
			$uuid = (string) $receipt->getAnnouncementUuid();
			if ($receipt->getSeenAt() !== null) {
				$state['seen'][] = $uuid;
			}

			if ($receipt->getConfirmedAt() !== null) {
				$state['confirmed'][] = $uuid;
			}

			if ($receipt->getConfirmedVia() === AnnouncementReceipt::VIA_X) {
				$state['closed'][] = $uuid;
			}
		}

		if ($this->announcements->isAuthor(userId: $userId) === true) {
			$state['receipts'] = array_values($uuids);
		}

		return $state;
	}//end stateFor()

	/**
	 * The receipt state of everything the reader sees now.
	 *
	 * @param string $userId The reader.
	 *
	 * @return array{seen: string[], confirmed: string[], closed: string[], receipts: string[]} Uuids.
	 *
	 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	 */
	public function stateOfVisible(string $userId): array {
		return $this->stateFor(userId: $userId, announcements: $this->announcements->visibleTo(userId: $userId));
	}//end stateOfVisible()

	/**
	 * Write the receipt: seen now when it was not yet, and confirmed now
	 * with `via` when given. A concurrent first write answers the row that won.
	 *
	 * @param AnnouncementReceipt|null $receipt The stored receipt, if any.
	 * @param string                   $uuid    The announcement.
	 * @param string                   $userId  The reader.
	 * @param string|null              $via     check or x to confirm, null to only mark seen.
	 *
	 * @return AnnouncementReceipt
	 *
	 * @throws DbException When the write fails for another reason.
	 */
	private function store(?AnnouncementReceipt $receipt, string $uuid, string $userId, ?string $via): AnnouncementReceipt {
		$now = new DateTime();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		// Entity setters resolve via __call which forwards $args[0].
		if ($receipt !== null) {
			if ($receipt->getSeenAt() === null) {
				$receipt->setSeenAt($now);
			}

			if ($via !== null) {
				$receipt->setConfirmedAt($now);
				$receipt->setConfirmedVia($via);
			}

			return $this->receipts->update(entity: $receipt);
		}

		$receipt = new AnnouncementReceipt();
		$receipt->setAnnouncementUuid($uuid);
		$receipt->setUserId($userId);
		$receipt->setSeenAt($now);
		if ($via !== null) {
			$receipt->setConfirmedAt($now);
			$receipt->setConfirmedVia($via);
		}

		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		try {
			return $this->receipts->insert(entity: $receipt);
		} catch (DbException $exception) {
			$winner = $this->receipts->findOne(uuid: $uuid, userId: $userId);
			if ($exception->getReason() !== DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION || $winner === null) {
				throw $exception;
			}

			if ($via !== null && $winner->getConfirmedAt() === null) {
				return $this->store(receipt: $winner, uuid: $uuid, userId: $userId, via: $via);
			}

			return $winner;
		}
	}//end store()
}//end class
