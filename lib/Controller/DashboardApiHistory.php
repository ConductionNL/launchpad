<?php

/**
 * DashboardApiHistory
 *
 * The tree, versions, analytics and log of the dashboard API.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
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

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Service\AnalyticsService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\DashboardVersionService;
use Psr\Log\LoggerInterface;

/**
 * The tree, versions, analytics and log of the dashboard API.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
class DashboardApiHistory {
	/**
	 * Constructor.
	 *
	 * @param DashboardTreeService    $treeService The tree service that owns hierarchy queries, cycle detection,
	 *                                             slug uniqueness, path resolution, and the cascade-delete
	 *                                             walker (REQ-DASH-023..030).
	 * @param DashboardVersionService $versionService Snapshot service (REQ-VERS-001) — automatic snapshots fire
	 *                                                after every successful PUT via the debounced
	 *                                                `captureSnapshot` helper.
	 * @param AnalyticsService        $analyticsService The view-analytics service used by the `viewEvent`
	 *                                                  endpoint (REQ-ANLT-002).
	 * @param LoggerInterface         $logger PSR logger (used by fork to report unexpected errors —
	 *                                        REQ-DASH-021).
	 */
	public function __construct(
		public readonly DashboardTreeService $treeService,
		public readonly DashboardVersionService $versionService,
		public readonly AnalyticsService $analyticsService,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()
}//end class
