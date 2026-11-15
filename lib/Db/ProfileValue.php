<?php

/**
 * ProfileValue Entity
 *
 * One value of a person's profile field: a custom field an administrator
 * defined, or a mirrored standard field (role, headline, biography) with its
 * Nextcloud visibility scope (REQ-PEX-001..004). A tags field has one row
 * per tag.
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
 * Profile value entity.
 *
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getFieldKey()
 * @method void setFieldKey(string $fieldKey)
 * @method string getValue()
 * @method void setValue(string $value)
 * @method string getValueSearch()
 * @method void setValueSearch(string $valueSearch)
 * @method string getSource()
 * @method void setSource(string $source)
 * @method string|null getScope()
 * @method void setScope(?string $scope)
 * @method string|null getUpdatedAt()
 * @method void setUpdatedAt(?string $updatedAt)
 *
 * @spec openspec/specs/people-widget/spec.md
 */
class ProfileValue extends Entity implements JsonSerializable {

	/**
	 * Owner user id.
	 *
	 * @var string
	 */
	protected string $userId = '';

	/**
	 * Field key: a custom field key, or nc:role, nc:headline, nc:biography.
	 *
	 * @var string
	 */
	protected string $fieldKey = '';

	/**
	 * The value as entered or read.
	 *
	 * @var string
	 */
	protected string $value = '';

	/**
	 * Lower-cased value for the search.
	 *
	 * @var string
	 */
	protected string $valueSearch = '';

	/**
	 * Where the value came from: self, ldap or nextcloud.
	 *
	 * @var string
	 */
	protected string $source = '';

	/**
	 * Nextcloud visibility scope of a mirrored standard field.
	 *
	 * @var string|null
	 */
	protected ?string $scope = null;

	/**
	 * Last write.
	 *
	 * @var string|null
	 */
	protected ?string $updatedAt = null;

	/**
	 * Register the column types.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function __construct() {
		$this->addType(fieldName: 'id', type: 'integer');
	}//end __construct()

	/**
	 * Serialise for the API.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'userId' => $this->userId,
			'fieldKey' => $this->fieldKey,
			'value' => $this->value,
			'source' => $this->source,
			'scope' => $this->scope,
		];
	}//end jsonSerialize()
}//end class
