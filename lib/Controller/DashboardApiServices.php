<?php

/**
 * DashboardApiServices
 *
 * The dashboard service and the optional quota, translation and metadata services of the dashboard
 * API.
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

use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTranslationService;
use OCA\LaunchPad\Service\MetadataService;
use OCA\LaunchPad\Service\QuotaService;

/**
 * The dashboard service and the optional quota, translation and metadata services of the dashboard
 * API.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
class DashboardApiServices {
	/**
	 * Constructor.
	 *
	 * @param DashboardService                 $dashboardService The dashboard service.
	 * @param QuotaService|null                $quotaService The quota-enforcement service used to gate dashboard
	 *                                                       creation (dashboard-quota-limits).
	 * @param DashboardTranslationService|null $translationService Says whether a dashboard has more than one
	 *                                                             language.
	 * @param MetadataService|null             $metadataService Filters the visible list by detail fields.
	 */
	public function __construct(
		public readonly DashboardService $dashboardService,
		public readonly ?QuotaService $quotaService=null,
		public readonly ?DashboardTranslationService $translationService=null,
		public readonly ?MetadataService $metadataService=null,
	) {
	}//end __construct()
}//end class
