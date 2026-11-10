<?php

/**
 * ExportTarget
 *
 * What an export run writes: its scope, the dashboard UUID (for a single-dashboard export) and the archive path.
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
 * @spec openspec/specs/dashboard-export-import/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Command;

/**
 * The scope, dashboard UUID and archive path of one export run.
 *
 * @spec openspec/specs/dashboard-export-import/spec.md
 */
final class ExportTarget {
	/**
	 * Constructor.
	 *
	 * @param string $scope         `site` or `dashboard`.
	 * @param string $dashboardUuid Dashboard UUID (when scope=dashboard), else empty.
	 * @param string $outputPath    Destination file path.
	 */
	public function __construct(
		public readonly string $scope,
		public readonly string $dashboardUuid,
		public readonly string $outputPath,
	) {
	}//end __construct()
}//end class
