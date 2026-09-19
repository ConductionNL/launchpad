<?php

/**
 * PersonalLayer Entity
 *
 * One person's arrangement of a dashboard somebody else owns
 * (REQ-DWMS-001). One row per `(userId, dashboardId)` pair, holding only the
 * differences from what the owner composed: per placement a position, a size
 * and a sort order, plus the set of placements this person hid.
 *
 * It is a layer, not a copy. When the organisation changes its dashboard the
 * change is seen immediately, because the shared placements are still the
 * ones being rendered.
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
 * A personal layer over a shared dashboard (REQ-DWMS-001).
 *
 * @method string|null getUserId()
 * @method void setUserId(?string $userId)
 * @method int getDashboardId()
 * @method void setDashboardId(int $dashboardId)
 * @method string|null getOverrides()
 * @method void setOverrides(?string $overrides)
 * @method string|null getHidden()
 * @method void setHidden(?string $hidden)
 * @method string|null getUpdatedAt()
 * @method void setUpdatedAt(?string $updatedAt)
 */
class PersonalLayer extends Entity implements JsonSerializable {

	/**
	 * The person whose arrangement this is. Nobody else ever reads it.
	 *
	 * @var string|null
	 */
	protected ?string $userId = null;

	/**
	 * The dashboard it applies over.
	 *
	 * @var integer
	 */
	protected int $dashboardId = 0;

	/**
	 * Per placement id, the keys this person changed: `gridX`, `gridY`,
	 * `gridWidth`, `gridHeight`, `sortOrder`. JSON.
	 *
	 * @var string|null
	 */
	protected ?string $overrides = null;

	/**
	 * The placement ids this person hid. JSON array.
	 *
	 * @var string|null
	 */
	protected ?string $hidden = null;

	/**
	 * When the layer last changed, ISO 8601.
	 *
	 * @var string|null
	 */
	protected ?string $updatedAt = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->addType(fieldName: 'id', type: 'integer');
		$this->addType(fieldName: 'dashboardId', type: 'integer');
	}//end __construct()

	/**
	 * The overrides as an array, keyed by placement id.
	 *
	 * A column that will not decode is read as an empty layer rather than
	 * throwing: a corrupt personal arrangement must not take the shared
	 * dashboard down with it.
	 *
	 * @return array<int, array<string, int>>
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function overridesArray(): array {
		$decoded = json_decode((string)$this->getOverrides(), true);
		if (is_array($decoded) === false) {
			return [];
		}

		$out = [];
		foreach ($decoded as $placementId => $values) {
			if (is_array($values) === true) {
				$out[(int)$placementId] = array_map('intval', $values);
			}
		}

		return $out;
	}//end overridesArray()

	/**
	 * The hidden placement ids.
	 *
	 * @return array<int, int>
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function hiddenArray(): array {
		$decoded = json_decode((string)$this->getHidden(), true);
		if (is_array($decoded) === false) {
			return [];
		}

		return array_values(array_unique(array_map('intval', $decoded)));
	}//end hiddenArray()

	/**
	 * Serialise for the API.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'dashboardId' => $this->getDashboardId(),
			// Cast to an object so `overrides` is a JSON object in every
			// state. PHP encodes an empty array, and an array whose keys
			// happen to run 0, 1, 2, as `[]`, which hands the client a
			// different type for the same field depending on what is in it.
			'overrides' => (object)$this->overridesArray(),
			// `hidden` is genuinely a list of placement ids, and
			// `hiddenArray()` runs it through `array_values()`, so it is
			// always a JSON array and stays one.
			'hidden' => $this->hiddenArray(),
			'updatedAt' => $this->getUpdatedAt(),
		];
	}//end jsonSerialize()
}//end class
