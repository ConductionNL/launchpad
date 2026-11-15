<?php

/**
 * PageSources
 *
 * What the main page reads: initial state, widgets, dashboards and admin templates.
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

use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\WidgetService;
use OCP\AppFramework\Services\IInitialState;

/**
 * What the main page reads: initial state, widgets, dashboards and admin templates.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
class PageSources {
	/**
	 * Constructor.
	 *
	 * @param IInitialState        $initialState The Nextcloud initial-state service.
	 * @param WidgetService        $widgetService Available-widgets descriptor formatter.
	 * @param DashboardService     $dashboardService Dashboard listing + resolver (also exposes the
	 *                                               `allow_user_dashboards` flag — REQ-ASET-003).
	 * @param AdminTemplateService $adminTemplateService Primary-group routing resolver (REQ-TMPL-012,
	 *                                                   REQ-TMPL-013).
	 */
	public function __construct(
		public readonly IInitialState $initialState,
		public readonly WidgetService $widgetService,
		public readonly DashboardService $dashboardService,
		public readonly AdminTemplateService $adminTemplateService,
	) {
	}//end __construct()
}//end class
