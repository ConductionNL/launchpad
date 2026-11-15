<?php

/**
 * LockSources
 *
 * The lock service and dashboard mapper of the lock API.
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

use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Service\DashboardLockService;

/**
 * The lock service and dashboard mapper of the lock API.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
class LockSources {
	/**
	 * Constructor.
	 *
	 * @param DashboardLockService $lockService The lock service.
	 * @param DashboardMapper      $dashboardMapper UUID → id lookup.
	 */
	public function __construct(
		public readonly DashboardLockService $lockService,
		public readonly DashboardMapper $dashboardMapper,
	) {
	}//end __construct()
}//end class
