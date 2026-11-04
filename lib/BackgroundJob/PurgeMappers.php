<?php

/**
 * PurgeMappers
 *
 * The view and tile click mappers the purge job prunes.
 *
 * @category  BackgroundJob
 * @package   OCA\LaunchPad\BackgroundJob
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/dashboard-view-analytics/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\BackgroundJob;

use OCA\LaunchPad\Db\DashboardViewMapper;
use OCA\LaunchPad\Db\TileClickMapper;

/**
 * The view and tile click mappers the purge job prunes.
 *
 * @spec openspec/specs/dashboard-view-analytics/spec.md
 */
class PurgeMappers {
	/**
	 * Constructor.
	 *
	 * @param DashboardViewMapper $viewMapper Aggregate-row mapper.
	 * @param TileClickMapper     $tileClickMapper Tile-click aggregate-row mapper — reuses the same cutoff date
	 *                                             (REQ-TANLT-005).
	 */
	public function __construct(
		public readonly DashboardViewMapper $viewMapper,
		public readonly TileClickMapper $tileClickMapper,
	) {
	}//end __construct()
}//end class
