<?php

/**
 * AppRequest Entity
 *
 * A user's request for an application the catalogue lacks
 * (launcher-app-catalogue, REQ-ACAT-003/004), and how an administrator
 * decided on it.
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
 * App request entity.
 *
 * @method string getUuid()
 * @method void setUuid(string $uuid)
 * @method string getRequestedBy()
 * @method void setRequestedBy(string $requestedBy)
 * @method string getAppName()
 * @method void setAppName(string $appName)
 * @method string|null getPurpose()
 * @method void setPurpose(string|null $purpose)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string|null getDecidedBy()
 * @method void setDecidedBy(string|null $decidedBy)
 * @method string|null getDecisionNote()
 * @method void setDecisionNote(string|null $decisionNote)
 * @method int|null getCatalogueAppId()
 * @method void setCatalogueAppId(int|null $catalogueAppId)
 * @method string|null getCreatedAt()
 * @method void setCreatedAt(string|null $createdAt)
 * @method string|null getDecidedAt()
 * @method void setDecidedAt(string|null $decidedAt)
 *
 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-1
 */
class AppRequest extends Entity implements JsonSerializable {

	/**
	 * Stable public id.
	 *
	 * @var string
	 */
	protected string $uuid = '';

	/**
	 * The user who asked.
	 *
	 * @var string
	 */
	protected string $requestedBy = '';

	/**
	 * The application asked for.
	 *
	 * @var string
	 */
	protected string $appName = '';

	/**
	 * What the user needs it for.
	 *
	 * @var string|null
	 */
	protected ?string $purpose = null;

	/**
	 * open, approved or declined.
	 *
	 * @var string
	 */
	protected string $status = 'open';

	/**
	 * The administrator who decided.
	 *
	 * @var string|null
	 */
	protected ?string $decidedBy = null;

	/**
	 * Why it was declined.
	 *
	 * @var string|null
	 */
	protected ?string $decisionNote = null;

	/**
	 * The catalogue entry an approval created.
	 *
	 * @var int|null
	 */
	protected ?int $catalogueAppId = null;

	/**
	 * Asked.
	 *
	 * @var string|null
	 */
	protected ?string $createdAt = null;

	/**
	 * Decided.
	 *
	 * @var string|null
	 */
	protected ?string $decidedAt = null;

	/**
	 * Register the column types.
	 *
	 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-1
	 */
	public function __construct() {
		$this->addType(fieldName: 'id', type: 'integer');
		$this->addType(fieldName: 'catalogueAppId', type: 'integer');
	}//end __construct()

	/**
	 * Serialise for the API.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-1
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'uuid' => $this->uuid,
			'requestedBy' => $this->requestedBy,
			'appName' => $this->appName,
			'purpose' => ($this->purpose ?? ''),
			'status' => $this->status,
			'decidedBy' => $this->decidedBy,
			'decisionNote' => $this->decisionNote,
			'catalogueAppId' => $this->catalogueAppId,
			'createdAt' => $this->createdAt,
			'decidedAt' => $this->decidedAt,
		];
	}//end jsonSerialize()
}//end class
