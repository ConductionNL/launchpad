<?php

/**
 * ShowcaseStores
 *
 * Where the demo showcases are stored: the dashboard and placement mappers, the database and the
 * install lock.
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
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCP\IDBConnection;
use OCP\Lock\ILockingProvider;

/**
 * Where the demo showcases are stored: the dashboard and placement mappers, the database and the
 * install lock.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
class ShowcaseStores {
	/**
	 * Constructor.
	 *
	 * @param DashboardMapper       $dashboardMapper Dashboard data mapper.
	 * @param WidgetPlacementMapper $placementMapper Widget placement mapper.
	 * @param IDBConnection         $db Database connection.
	 * @param ILockingProvider      $lockingProvider Advisory lock provider (M8 — concurrency guard on
	 *                                               installShowcase).
	 */
	public function __construct(
		public readonly DashboardMapper $dashboardMapper,
		public readonly WidgetPlacementMapper $placementMapper,
		public readonly IDBConnection $db,
		public readonly ILockingProvider $lockingProvider,
	) {
	}//end __construct()
}//end class
