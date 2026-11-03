<?php

/**
 * DashboardBuilders
 *
 * What DashboardService builds new dashboards from: templates, the factory and the resolver.
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

/**
 * What DashboardService builds new dashboards from: templates, the factory and the resolver.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
class DashboardBuilders {
	/**
	 * Constructor.
	 *
	 * @param TemplateService      $templateService Template service.
	 * @param DashboardFactory     $dashboardFactory Dashboard factory.
	 * @param DashboardResolver    $dashResolver Dashboard resolver.
	 * @param AdminTemplateService $adminTemplateService Routing resolver — single source of truth for
	 *                                                   `IGroupManager::getUserGroupIds` (REQ-TMPL-013).
	 */
	public function __construct(
		public readonly TemplateService $templateService,
		public readonly DashboardFactory $dashboardFactory,
		public readonly DashboardResolver $dashResolver,
		public readonly AdminTemplateService $adminTemplateService,
	) {
	}//end __construct()
}//end class
