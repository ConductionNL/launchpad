<?php

/**
 * CatalogueApp Entity
 *
 * An application an administrator approved for the app catalogue
 * (launcher-app-catalogue, REQ-ACAT-001): what a tile made from it shows and
 * links to, and the groups that may see it. No groups means everyone.
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
 * Catalogue app entity.
 *
 * @method string getUuid()
 * @method void setUuid(string $uuid)
 * @method string getName()
 * @method void setName(string $name)
 * @method string|null getDescription()
 * @method void setDescription(string|null $description)
 * @method string|null getIcon()
 * @method void setIcon(string|null $icon)
 * @method string getIconType()
 * @method void setIconType(string $iconType)
 * @method string getLinkType()
 * @method void setLinkType(string $linkType)
 * @method string getLinkValue()
 * @method void setLinkValue(string $linkValue)
 * @method string|null getTargetGroups()
 * @method void setTargetGroups(string|null $targetGroups)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 * @method string|null getCreatedAt()
 * @method void setCreatedAt(string|null $createdAt)
 * @method string|null getUpdatedAt()
 * @method void setUpdatedAt(string|null $updatedAt)
 *
 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-1
 */
class CatalogueApp extends Entity implements JsonSerializable {

	/**
	 * Stable public id.
	 *
	 * @var string
	 */
	protected string $uuid = '';

	/**
	 * Name, also the title of a tile made from it.
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * Short description.
	 *
	 * @var string|null
	 */
	protected ?string $description = null;

	/**
	 * Icon: a class name or an image address.
	 *
	 * @var string|null
	 */
	protected ?string $icon = null;

	/**
	 * How to read the icon: class or url.
	 *
	 * @var string
	 */
	protected string $iconType = 'class';

	/**
	 * What the address is: app (a Nextcloud app id) or url.
	 *
	 * @var string
	 */
	protected string $linkType = 'url';

	/**
	 * The Nextcloud app id or the web address.
	 *
	 * @var string
	 */
	protected string $linkValue = '';

	/**
	 * JSON list of group ids that see the entry; empty means everyone.
	 *
	 * @var string|null
	 */
	protected ?string $targetGroups = null;

	/**
	 * Position in the catalogue.
	 *
	 * @var int
	 */
	protected int $sortOrder = 0;

	/**
	 * Created.
	 *
	 * @var string|null
	 */
	protected ?string $createdAt = null;

	/**
	 * Last changed.
	 *
	 * @var string|null
	 */
	protected ?string $updatedAt = null;

	/**
	 * Register the column types.
	 *
	 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-1
	 */
	public function __construct() {
		$this->addType(fieldName: 'id', type: 'integer');
		$this->addType(fieldName: 'sortOrder', type: 'integer');
	}//end __construct()

	/**
	 * The groups that may see the entry, decoded; empty means everyone.
	 *
	 * @return array<int, string>
	 *
	 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-1
	 */
	public function getTargetGroupList(): array {
		$decoded = json_decode(json: (string)($this->targetGroups ?? ''), associative: true);
		if (is_array($decoded) === false) {
			return [];
		}

		return array_values(
			array: array_filter(
				array: array_map(callback: 'strval', array: $decoded),
				callback: static fn (string $group): bool => $group !== ''
			)
		);
	}//end getTargetGroupList()

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
			'name' => $this->name,
			'description' => ($this->description ?? ''),
			'icon' => ($this->icon ?? ''),
			'iconType' => $this->iconType,
			'linkType' => $this->linkType,
			'linkValue' => $this->linkValue,
			'targetGroups' => $this->getTargetGroupList(),
			'sortOrder' => $this->sortOrder,
		];
	}//end jsonSerialize()
}//end class
