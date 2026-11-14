<?php

/**
 * Announcement Entity
 *
 * One row in `oc_launchpad_announcements`: a news item or a notice
 * (engagement-announcements, REQ-ANN-001, REQ-ANN-005).
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
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Db;

use DateTime;
use DateTimeInterface;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * A news item or a notice.
 *
 * @method string|null getUuid()
 * @method void setUuid(string $uuid)
 * @method string|null getKind()
 * @method void setKind(string $kind)
 * @method string|null getTitle()
 * @method void setTitle(string $title)
 * @method string|null getBody()
 * @method void setBody(?string $body)
 * @method string|null getCategory()
 * @method void setCategory(?string $category)
 * @method string|null getLevel()
 * @method void setLevel(?string $level)
 * @method int getDismissible()
 * @method void setDismissible(int $dismissible)
 * @method string|null getTargetGroups()
 * @method void setTargetGroups(?string $targetGroups)
 * @method string|null getStatus()
 * @method void setStatus(string $status)
 * @method DateTime|null getPublishAt()
 * @method void setPublishAt(?DateTime $publishAt)
 * @method DateTime|null getExpiresAt()
 * @method void setExpiresAt(?DateTime $expiresAt)
 * @method int getAllowComments()
 * @method void setAllowComments(int $allowComments)
 * @method string|null getAuthorId()
 * @method void setAuthorId(string $authorId)
 * @method DateTime|null getNotifiedAt()
 * @method void setNotifiedAt(?DateTime $notifiedAt)
 * @method DateTime|null getCreatedAt()
 * @method void setCreatedAt(?DateTime $createdAt)
 * @method DateTime|null getUpdatedAt()
 * @method void setUpdatedAt(?DateTime $updatedAt)
 *
 * @SuppressWarnings(PHPMD.TooManyFields) Each field is a column the design names.
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
 */
class Announcement extends Entity implements JsonSerializable {

	public const KIND_NEWS = 'news';

	public const KIND_NOTICE = 'notice';

	public const STATUS_DRAFT = 'draft';

	public const STATUS_PUBLISHED = 'published';

	/**
	 * The announcement uuid.
	 *
	 * @var ?string
	 */
	protected ?string $uuid = null;

	/**
	 * news or notice.
	 *
	 * @var ?string
	 */
	protected ?string $kind = null;

	/**
	 * The title.
	 *
	 * @var ?string
	 */
	protected ?string $title = null;

	/**
	 * The text, in Markdown.
	 *
	 * @var ?string
	 */
	protected ?string $body = null;

	/**
	 * The category, or null.
	 *
	 * @var ?string
	 */
	protected ?string $category = null;

	/**
	 * info or warning, for a notice.
	 *
	 * @var ?string
	 */
	protected ?string $level = null;

	/**
	 * 1 when a reader may dismiss the notice.
	 *
	 * @var int
	 */
	protected int $dismissible = 0;

	/**
	 * JSON list of target group ids; empty means everyone.
	 *
	 * @var ?string
	 */
	protected ?string $targetGroups = null;

	/**
	 * draft or published.
	 *
	 * @var ?string
	 */
	protected ?string $status = null;

	/**
	 * When it becomes visible.
	 *
	 * @var ?DateTime
	 */
	protected ?DateTime $publishAt = null;

	/**
	 * When it stops being visible.
	 *
	 * @var ?DateTime
	 */
	protected ?DateTime $expiresAt = null;

	/**
	 * 1 when readers may comment.
	 *
	 * @var int
	 */
	protected int $allowComments = 0;

	/**
	 * The author.
	 *
	 * @var ?string
	 */
	protected ?string $authorId = null;

	/**
	 * When followers were told.
	 *
	 * @var ?DateTime
	 */
	protected ?DateTime $notifiedAt = null;

	/**
	 * When it was written.
	 *
	 * @var ?DateTime
	 */
	protected ?DateTime $createdAt = null;

	/**
	 * When it was last changed.
	 *
	 * @var ?DateTime
	 */
	protected ?DateTime $updatedAt = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->addType(fieldName: 'id', type: 'integer');
		$this->addType(fieldName: 'dismissible', type: 'integer');
		$this->addType(fieldName: 'allowComments', type: 'integer');
		$this->addType(fieldName: 'publishAt', type: 'datetime');
		$this->addType(fieldName: 'expiresAt', type: 'datetime');
		$this->addType(fieldName: 'notifiedAt', type: 'datetime');
		$this->addType(fieldName: 'createdAt', type: 'datetime');
		$this->addType(fieldName: 'updatedAt', type: 'datetime');
	}//end __construct()

	/**
	 * The target groups; an empty list means everyone.
	 *
	 * @return list<string>
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function getTargetGroupList(): array {
		$decoded = json_decode((string) $this->targetGroups, true);
		if (is_array($decoded) === false) {
			return [];
		}

		return array_values(array_filter($decoded, static fn (mixed $group): bool => is_string($group) && $group !== ''));
	}//end getTargetGroupList()

	/**
	 * Store the target groups.
	 *
	 * @param list<string> $groups The group ids; empty means everyone.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function setTargetGroupList(array $groups): void {
		$this->setTargetGroups((string) json_encode(array_values(array_unique($groups))));
	}//end setTargetGroupList()

	/**
	 * A date as ISO 8601, or null.
	 *
	 * @param DateTimeInterface|null $date The date.
	 *
	 * @return string|null
	 */
	private static function iso(?DateTimeInterface $date): ?string {
		return $date?->format(DateTimeInterface::ATOM);
	}//end iso()

	/**
	 * The announcement as the API answers it.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'uuid' => $this->uuid,
			'kind' => $this->kind,
			'title' => $this->title,
			'body' => $this->body,
			'category' => $this->category,
			'level' => $this->level,
			'dismissible' => $this->dismissible === 1,
			'targetGroups' => $this->getTargetGroupList(),
			'status' => $this->status,
			'publishAt' => self::iso(date: $this->publishAt),
			'expiresAt' => self::iso(date: $this->expiresAt),
			'allowComments' => $this->allowComments === 1,
			'authorId' => $this->authorId,
			'createdAt' => self::iso(date: $this->createdAt),
			'updatedAt' => self::iso(date: $this->updatedAt),
		];
	}//end jsonSerialize()
}//end class
