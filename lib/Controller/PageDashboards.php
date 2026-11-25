<?php

/**
 * PageDashboards
 *
 * What the main page reads about dashboards: the dashboard service, and the
 * builder of the first dashboard load the page carries in its initial state.
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
 * @spec openspec/changes/dashboard-first-load-from-initial-state/tasks.md#task-2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\SharingLimits;

/**
 * The dashboard service and the first-load builder of the main page.
 *
 * @spec openspec/changes/dashboard-first-load-from-initial-state/tasks.md#task-2
 */
class PageDashboards {
	/**
	 * Constructor.
	 *
	 * @param DashboardService       $dashboardService Dashboard listing + resolver (also exposes the
	 *                                                 `allow_user_dashboards` flag — REQ-ASET-003).
	 * @param DashboardPayloads|null $payloads         The first dashboard load the page carries; without
	 *                                                 it the store fetches.
	 * @param SharingLimits|null     $sharing          Whether the viewer may share and may create public
	 *                                                 links (REQ-SHARE-015); without it the tab offers both.
	 */
	public function __construct(
		public readonly DashboardService $dashboardService,
		public readonly ?DashboardPayloads $payloads=null,
		public readonly ?SharingLimits $sharing=null,
	) {
	}//end __construct()
}//end class
