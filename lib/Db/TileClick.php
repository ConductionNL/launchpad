<?php

/**
 * TileClick Entity
 *
 * Represents one daily aggregate row in `launchpad_tile_clicks` — the
 * persistent half of the tile usage-analytics capability
 * (REQ-TANLT-001..003). One entity per `(placementUuid, clickBucket)`
 * pair. This is a strict downward extension of the dashboard
 * view-analytics capability's `DashboardView` shape — see that entity
 * for the sibling table this one mirrors.
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
 * Tile click-analytics aggregate entity (REQ-TANLT-001).
 *
 * @method string|null getPlacementUuid()
 * @method void setPlacementUuid(?string $placementUuid)
 * @method string|null getDashboardUuid()
 * @method void setDashboardUuid(?string $dashboardUuid)
 * @method string|null getClickBucket()
 * @method void setClickBucket(?string $clickBucket)
 * @method int getClickCount()
 * @method void setClickCount(int $clickCount)
 * @method int getUniqueActorCount()
 * @method void setUniqueActorCount(int $uniqueActorCount)
 */
class TileClick extends Entity implements JsonSerializable {

	/**
	 * The widget-placement (tile) UUID this aggregate row belongs to.
	 *
	 * @var string|null
	 */
	protected ?string $placementUuid = null;

	/**
	 * The dashboard UUID the placement belongs to (denormalised for
	 * the per-dashboard breakdown query — REQ-TANLT-004).
	 *
	 * @var string|null
	 */
	protected ?string $dashboardUuid = null;

	/**
	 * Calendar date in UTC (YYYY-MM-DD).
	 *
	 * @var string|null
	 */
	protected ?string $clickBucket = null;

	/**
	 * Total click-event count for the bucket.
	 *
	 * @var integer
	 */
	protected int $clickCount = 0;

	/**
	 * Distinct actor count for the bucket (cache-deduped, reusing
	 * the same salted-daily-hash mechanism as dashboard-view
	 * analytics — REQ-TANLT-002).
	 *
	 * @var integer
	 */
	protected int $uniqueActorCount = 0;

	/**
	 * Constructor — register column types.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->addType(fieldName: 'id', type: 'integer');
		$this->addType(fieldName: 'clickCount', type: 'integer');
		$this->addType(fieldName: 'uniqueActorCount', type: 'integer');
	}//end __construct()

	/**
	 * Serialize to JSON.
	 *
	 * Stable, frontend-facing key names matching REQ-TANLT-004 /
	 * REQ-TANLT-005 scenario payload shapes.
	 *
	 * @return array The serialized aggregate row.
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'placementUuid' => $this->placementUuid,
			'dashboardUuid' => $this->dashboardUuid,
			'clickBucket' => $this->clickBucket,
			'clickCount' => $this->clickCount,
			'uniqueActorCount' => $this->uniqueActorCount,
		];
	}//end jsonSerialize()
}//end class
