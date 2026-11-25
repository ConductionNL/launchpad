<?php

/**
 * DemoSeedParts
 *
 * The helpers a demo seed writes through, grouped so DemoSeedService takes
 * them as one dependency.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 * @spec      openspec/specs/demo-data-showcases/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * Users and groups, dashboards, the first visit and the team folder of a seed.
 *
 * @spec openspec/specs/demo-data-showcases/spec.md
 */
class DemoSeedParts {
	/**
	 * Constructor.
	 *
	 * @param DemoSeedAccounts   $accounts   Demo users and groups.
	 * @param DemoSeedDashboards $dashboards Seeded dashboards, placements and shares.
	 * @param DemoSeedFirstRun   $firstRun   The first visit of the first-run user; tour progress of the others.
	 * @param DemoSeedFiles      $files      The shared team folder.
	 * @param DemoSeedStartDates $startDates The start date field and the new colleagues' start dates.
	 */
	public function __construct(
		public readonly DemoSeedAccounts $accounts,
		public readonly DemoSeedDashboards $dashboards,
		public readonly DemoSeedFirstRun $firstRun,
		public readonly DemoSeedFiles $files,
		public readonly DemoSeedStartDates $startDates,
	) {
	}//end __construct()
}//end class
