<?php

/**
 * CleanupJobTools
 *
 * The app config and the logger of the orphaned data cleanup job.
 *
 * @category  BackgroundJob
 * @package   OCA\LaunchPad\BackgroundJob
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

namespace OCA\LaunchPad\BackgroundJob;

use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

/**
 * The app config and the logger of the orphaned data cleanup job.
 *
 * @spec openspec/specs/orphaned-data-cleanup/spec.md
 */
class CleanupJobTools {
	/**
	 * Constructor.
	 *
	 * @param IAppConfig      $appConfig App config to read the admin-chosen auto-purge set.
	 * @param LoggerInterface $logger PSR-3 logger.
	 */
	public function __construct(
		public readonly IAppConfig $appConfig,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()
}//end class
