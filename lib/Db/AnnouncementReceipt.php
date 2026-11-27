<?php

/**
 * AnnouncementReceipt Entity
 *
 * One row in `oc_launchpad_ann_receipts`: what one reader did with one
 * announcement (announcements-managed-in-settings D3): when it was first on
 * their screen, and when and how they confirmed it (the check or the x).
 *
 * @category  Database
 * @package   OCA\LaunchPad\Db
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Db;

use DateTime;
use DateTimeInterface;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * A reader's receipt of an announcement.
 *
 * @method string|null getAnnouncementUuid()
 * @method void setAnnouncementUuid(string $announcementUuid)
 * @method string|null getUserId()
 * @method void setUserId(string $userId)
 * @method DateTime|null getSeenAt()
 * @method void setSeenAt(?DateTime $seenAt)
 * @method DateTime|null getConfirmedAt()
 * @method void setConfirmedAt(?DateTime $confirmedAt)
 * @method string|null getConfirmedVia()
 * @method void setConfirmedVia(?string $confirmedVia)
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 */
class AnnouncementReceipt extends Entity implements JsonSerializable {

	/**
	 * Confirmed with the check ("Read").
	 */
	public const VIA_CHECK = 'check';

	/**
	 * Confirmed with the x (closed).
	 */
	public const VIA_X = 'x';

	/**
	 * The announcement.
	 *
	 * @var ?string
	 */
	protected ?string $announcementUuid = null;

	/**
	 * The reader.
	 *
	 * @var ?string
	 */
	protected ?string $userId = null;

	/**
	 * The first time the announcement was on the reader's screen.
	 *
	 * @var ?DateTime
	 */
	protected ?DateTime $seenAt = null;

	/**
	 * The first confirmation.
	 *
	 * @var ?DateTime
	 */
	protected ?DateTime $confirmedAt = null;

	/**
	 * How the reader confirmed: check or x.
	 *
	 * @var ?string
	 */
	protected ?string $confirmedVia = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->addType(fieldName: 'id', type: 'integer');
		$this->addType(fieldName: 'seenAt', type: 'datetime');
		$this->addType(fieldName: 'confirmedAt', type: 'datetime');
	}//end __construct()

	/**
	 * The receipt as the API answers it.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
	 */
	public function jsonSerialize(): array {
		return [
			'announcementUuid' => $this->announcementUuid,
			'userId'           => $this->userId,
			'seenAt'           => $this->seenAt?->format(DateTimeInterface::ATOM),
			'confirmedAt'      => $this->confirmedAt?->format(DateTimeInterface::ATOM),
			'confirmedVia'     => $this->confirmedVia,
		];
	}//end jsonSerialize()
}//end class
