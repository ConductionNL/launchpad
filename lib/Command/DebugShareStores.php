<?php

/**
 * DebugShareStores
 *
 * The share, lock, version and view mappers the dashboard debug-share command reads.
 *
 * @category  Command
 * @package   OCA\LaunchPad\Command
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/cli-commands/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Command;

use OCA\LaunchPad\Db\DashboardLockMapper;
use OCA\LaunchPad\Db\DashboardShareMapper;
use OCA\LaunchPad\Db\DashboardVersionMapper;
use OCA\LaunchPad\Db\DashboardViewMapper;

/**
 * The share, lock, version and view mappers the dashboard debug-share command reads.
 *
 * @spec openspec/specs/cli-commands/spec.md
 */
class DebugShareStores {
	/**
	 * Constructor.
	 *
	 * @param DashboardShareMapper   $shareMapper   Share mapper.
	 * @param DashboardLockMapper    $lockMapper    Edit-lock mapper.
	 * @param DashboardVersionMapper $versionMapper Version mapper.
	 * @param DashboardViewMapper    $viewMapper    View-count mapper.
	 */
	public function __construct(
		public readonly DashboardShareMapper $shareMapper,
		public readonly DashboardLockMapper $lockMapper,
		public readonly DashboardVersionMapper $versionMapper,
		public readonly DashboardViewMapper $viewMapper,
	) {
	}//end __construct()
}//end class
