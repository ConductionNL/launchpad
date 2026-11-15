<?php

/**
 * VersionApiSources
 *
 * The dashboard mapper, version service and logger of the version API.
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
use OCA\LaunchPad\Service\DashboardVersionService;
use Psr\Log\LoggerInterface;

/**
 * The dashboard mapper, version service and logger of the version API.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
class VersionApiSources {
	/**
	 * Constructor.
	 *
	 * @param DashboardMapper         $dashboardMapper Dashboard row lookup.
	 * @param DashboardVersionService $versionService Version service.
	 * @param LoggerInterface         $logger PSR logger.
	 */
	public function __construct(
		public readonly DashboardMapper $dashboardMapper,
		public readonly DashboardVersionService $versionService,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()
}//end class
