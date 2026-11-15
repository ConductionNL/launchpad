<?php

/**
 * AnnouncementFollow Entity
 *
 * One row in `oc_launchpad_ann_follows`: a reader follows a category
 * (engagement-announcements, REQ-ANN-003).
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
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Db;

use DateTime;
use DateTimeInterface;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * A followed category.
 *
 * @method string|null getUserId()
 * @method void setUserId(string $userId)
 * @method string|null getCategory()
 * @method void setCategory(string $category)
 * @method DateTime|null getCreatedAt()
 * @method void setCreatedAt(?DateTime $createdAt)
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
 */
class AnnouncementFollow extends Entity implements JsonSerializable {

	/**
	 * The reader.
	 *
	 * @var ?string
	 */
	protected ?string $userId = null;

	/**
	 * The followed category.
	 *
	 * @var ?string
	 */
	protected ?string $category = null;

	/**
	 * When the reader started following it.
	 *
	 * @var ?DateTime
	 */
	protected ?DateTime $createdAt = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->addType(fieldName: 'id', type: 'integer');
		$this->addType(fieldName: 'createdAt', type: 'datetime');
	}//end __construct()

	/**
	 * The follow as the API answers it.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'userId' => $this->userId,
			'category' => $this->category,
			'createdAt' => $this->createdAt?->format(DateTimeInterface::ATOM),
		];
	}//end jsonSerialize()
}//end class
