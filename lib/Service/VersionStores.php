<?php

/**
 * VersionStores
 *
 * Where dashboard versions are stored: the version, dashboard and placement mappers and the
 * database.
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
 * @spec openspec/specs/dashboards/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardVersionMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCP\IDBConnection;

/**
 * Where dashboard versions are stored: the version, dashboard and placement mappers and the
 * database.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
class VersionStores {
	/**
	 * Constructor.
	 *
	 * @param DashboardVersionMapper $versionMapper Version row mapper.
	 * @param DashboardMapper        $dashboardMapper Dashboard row mapper.
	 * @param WidgetPlacementMapper  $placementMapper Widget placement mapper (drives snapshot payload assembly).
	 * @param IDBConnection|null     $db DB connection used to wrap restoreVersion in a transaction (WF1 fix).
	 *                                   Nullable for backwards-compat with existing test doubles.
	 */
	public function __construct(
		public readonly DashboardVersionMapper $versionMapper,
		public readonly DashboardMapper $dashboardMapper,
		public readonly WidgetPlacementMapper $placementMapper,
		public readonly ?IDBConnection $db=null,
	) {
	}//end __construct()
}//end class
