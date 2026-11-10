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

use Symfony\Component\Console\Input\InputInterface;

/**
 * The scope, dashboard UUID and archive path of one export run.
 *
 * @spec openspec/specs/dashboard-export-import/spec.md
 */
final class ExportTarget {
	/**
	 * `site` or `dashboard`.
	 *
	 * @var string
	 */
	public readonly string $scope;

	/**
	 * Dashboard UUID (when scope=dashboard), else empty.
	 *
	 * @var string
	 */
	public readonly string $dashboardUuid;

	/**
	 * Destination file path, empty when `--output` is missing.
	 *
	 * @var string
	 */
	public readonly string $outputPath;

	/**
	 * Read the export target from the command options.
	 *
	 * @param InputInterface $input CLI input.
	 *
	 * @spec openspec/specs/dashboard-export-import/spec.md
	 */
	public function __construct(InputInterface $input) {
		$this->scope = (string)$input->getOption(name: 'scope');
		$this->outputPath = (string)($input->getOption(name: 'output') ?? '');
		$this->dashboardUuid = (string)($input->getOption(name: 'dashboard-uuid') ?? '');
	}//end __construct()
}//end class
