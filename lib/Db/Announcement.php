<?php

/**
 * Announcement Entity
 *
 * A news item or a notice an author publishes to the people in its target
 * groups between its publish time and its end time (engagement-announcements,
 * REQ-ANN-001, REQ-ANN-005).
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
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * Announcement entity.
 *
 * @method string getUuid()
 * @method void setUuid(string $uuid)
 * @method string getKind()
 * @method void setKind(string $kind)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method string|null getBody()
 * @method void setBody(?string $body)
 * @method string|null getCategory()
 * @method void setCategory(?string $category)
 * @method string getLevel()
 * @method void setLevel(string $level)
 * @method int getDismissible()
 * @method void setDismissible(int $dismissible)
 * @method string|null getTargetGroups()
 * @method void setTargetGroups(?string $targetGroups)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string|null getPublishAt()
 * @method void setPublishAt(?string $publishAt)
 * @method string|null getExpiresAt()
 * @method void setExpiresAt(?string $expiresAt)
 * @method int getAllowComments()
 * @method void setAllowComments(int $allowComments)
 * @method string getAuthorId()
 * @method void setAuthorId(string $authorId)
 * @method string|null getNotifiedAt()
 * @method void setNotifiedAt(?string $notifiedAt)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 * @method string getUpdatedAt()
 * @method void setUpdatedAt(string $updatedAt)
 *
 * @spec openspec/specs/announcements/spec.md
 */
class Announcement extends Entity implements JsonSerializable {
	public const KIND_NEWS = 'news';
	public const KIND_NOTICE = 'notice';
	public const STATUS_DRAFT = 'draft';
	public const STATUS_PUBLISHED = 'published';
	public const LEVEL_INFO = 'info';
	public const LEVEL_WARNING = 'warning';

	/**
	 * Public identifier.
	 *
	 * @var string
	 */
	protected string $uuid = '';

	/**
	 * Kind: news or notice.
	 *
	 * @var string
	 */
	protected string $kind = self::KIND_NEWS;

	/**
	 * Title.
	 *
	 * @var string
	 */
	protected string $title = '';

	/**
	 * Text, shown as plain text with line breaks.
	 *
	 * @var string|null
	 */
	protected ?string $body = null;

	/**
	 * Category readers can filter on and follow.
	 *
	 * @var string|null
	 */
	protected ?string $category = null;

	/**
	 * Level of a notice: info or warning.
	 *
	 * @var string
	 */
	protected string $level = self::LEVEL_INFO;

	/**
	 * Whether a reader may dismiss a notice (1) or not (0).
	 *
	 * @var integer
	 */
	protected int $dismissible = 1;

	/**
	 * JSON list of target group ids; empty means everyone.
	 *
	 * @var string|null
	 */
	protected ?string $targetGroups = null;

	/**
	 * Status: draft or published.
	 *
	 * @var string
	 */
	protected string $status = self::STATUS_DRAFT;

	/**
	 * Publish time (UTC, Y-m-d H:i:s).
	 *
	 * @var string|null
	 */
	protected ?string $publishAt = null;

	/**
	 * End time (UTC, Y-m-d H:i:s); required for notices.
	 *
	 * @var string|null
	 */
	protected ?string $expiresAt = null;

	/**
	 * Whether readers may comment (1) or not (0).
	 *
	 * @var integer
	 */
	protected int $allowComments = 1;

	/**
	 * Author user id.
	 *
	 * @var string
	 */
	protected string $authorId = '';

	/**
	 * When the followers were notified; null while not yet.
	 *
	 * @var string|null
	 */
	protected ?string $notifiedAt = null;

	/**
	 * Created at.
	 *
	 * @var string
	 */
	protected string $createdAt = '';

	/**
	 * Updated at.
	 *
	 * @var string
	 */
	protected string $updatedAt = '';

	/**
	 * Register the column types.
	 *
	 * @spec openspec/specs/announcements/spec.md
	 */
	public function __construct() {
		$this->addType(fieldName: 'id', type: 'integer');
		$this->addType(fieldName: 'dismissible', type: 'integer');
		$this->addType(fieldName: 'allowComments', type: 'integer');
	}//end __construct()

	/**
	 * The target group ids.
	 *
	 * @return string[]
	 *
	 * @spec openspec/specs/announcements/spec.md
	 */
	public function getTargetGroupList(): array {
		$decoded = json_decode(json: ($this->targetGroups ?? '[]'), associative: true);
		if (is_array(value: $decoded) === false) {
			return [];
		}

		return array_values(array: array_filter(array: $decoded, callback: 'is_string'));
	}//end getTargetGroupList()

	/**
	 * Serialise for the API.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/specs/announcements/spec.md
	 */
	public function jsonSerialize(): array {
		return [
			'id'            => $this->getId(),
			'uuid'          => $this->uuid,
			'kind'          => $this->kind,
			'title'         => $this->title,
			'body'          => $this->body,
			'category'      => $this->category,
			'level'         => $this->level,
			'dismissible'   => ($this->dismissible === 1),
			'targetGroups'  => $this->getTargetGroupList(),
			'status'        => $this->status,
			'publishAt'     => $this->publishAt,
			'expiresAt'     => $this->expiresAt,
			'allowComments' => ($this->allowComments === 1),
			'authorId'      => $this->authorId,
			'createdAt'     => $this->createdAt,
			'updatedAt'     => $this->updatedAt,
		];
	}//end jsonSerialize()
}//end class
