<?php

/**
 * ResyncStores
 *
 * Where a template resync writes: the dashboard and placement mappers, the database and the job
 * list.
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
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;

/**
 * Where a template resync writes: the dashboard and placement mappers, the database and the job
 * list.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
class ResyncStores {
	/**
	 * Constructor.
	 *
	 * @param DashboardMapper       $dashboardMapper Dashboard mapper.
	 * @param WidgetPlacementMapper $placementMapper Widget placement mapper.
	 * @param IDBConnection         $db DB connection — used for the per-copy transaction boundary.
	 * @param IJobList              $jobList Background job list — used to enqueue {@see TemplateResyncJob} for
	 *                                       large target groups.
	 */
	public function __construct(
		public readonly DashboardMapper $dashboardMapper,
		public readonly WidgetPlacementMapper $placementMapper,
		public readonly IDBConnection $db,
		public readonly IJobList $jobList,
	) {
	}//end __construct()
}//end class
