<?php

/**
 * CleanupSources
 *
 * The cleanup service and the category registry of the admin cleanup API.
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
 * @spec openspec/specs/orphaned-data-cleanup/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Service\Cleanup\CategoryRegistryService;
use OCA\LaunchPad\Service\OrphanedDataCleanupService;

/**
 * The cleanup service and the category registry of the admin cleanup API.
 *
 * @spec openspec/specs/orphaned-data-cleanup/spec.md
 */
class CleanupSources {
	/**
	 * Constructor.
	 *
	 * @param OrphanedDataCleanupService $cleanupService The orchestrator.
	 * @param CategoryRegistryService    $registry Category registry (for unknown-name error messages).
	 */
	public function __construct(
		public readonly OrphanedDataCleanupService $cleanupService,
		public readonly CategoryRegistryService $registry,
	) {
	}//end __construct()
}//end class
