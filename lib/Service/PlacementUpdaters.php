<?php

/**
 * PlacementUpdaters
 *
 * The tile and placement updaters of the placement service.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/widgets/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * The tile and placement updaters of the placement service.
 *
 * @spec openspec/specs/widgets/spec.md
 */
class PlacementUpdaters {
	/**
	 * Constructor.
	 *
	 * @param TileUpdater      $tileUpdater Tile updater service.
	 * @param PlacementUpdater $placementUpdater Placement updater service.
	 */
	public function __construct(
		public readonly TileUpdater $tileUpdater,
		public readonly PlacementUpdater $placementUpdater,
	) {
	}//end __construct()
}//end class
