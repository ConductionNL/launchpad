<?php

/**
 * ShippedTemplateStores
 *
 * Where shipped template updates are written: the dashboard and placement mappers, the database and
 * app config.
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
use OCP\IAppConfig;
use OCP\IDBConnection;

/**
 * Where shipped template updates are written: the dashboard and placement mappers, the database and
 * app config.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
class ShippedTemplateStores {
	/**
	 * Constructor.
	 *
	 * @param DashboardMapper       $dashboardMapper Counts the members' copies.
	 * @param WidgetPlacementMapper $placementMapper Reads and writes the template's widgets.
	 * @param IDBConnection         $db One transaction for the template's widgets.
	 * @param IAppConfig            $appConfig Records the installed version.
	 */
	public function __construct(
		public readonly DashboardMapper $dashboardMapper,
		public readonly WidgetPlacementMapper $placementMapper,
		public readonly IDBConnection $db,
		public readonly IAppConfig $appConfig,
	) {
	}//end __construct()
}//end class
