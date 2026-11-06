<?php

/**
 * ConfluenceDashboardWriters
 *
 * What the Confluence importer writes dashboards with: the factory, the tree checks and the
 * dashboard and placement mappers.
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

/**
 * What the Confluence importer writes dashboards with: the factory, the tree checks and the
 * dashboard and placement mappers.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
class ConfluenceDashboardWriters {
	/**
	 * Constructor.
	 *
	 * @param DashboardFactory      $dashboardFactory Dashboard factory.
	 * @param DashboardTreeService  $treeService Tree validation / lookups.
	 * @param DashboardMapper       $dashboardMapper Dashboard mapper.
	 * @param WidgetPlacementMapper $placementMapper Widget mapper.
	 */
	public function __construct(
		public readonly DashboardFactory $dashboardFactory,
		public readonly DashboardTreeService $treeService,
		public readonly DashboardMapper $dashboardMapper,
		public readonly WidgetPlacementMapper $placementMapper,
	) {
	}//end __construct()
}//end class
