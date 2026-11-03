<?php

/**
 * CleanupReporting
 *
 * The activity manager and the logger the cleanup service reports to.
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
 * @spec openspec/specs/orphaned-data-cleanup/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\Activity\IManager as IActivityManager;
use Psr\Log\LoggerInterface;

/**
 * The activity manager and the logger the cleanup service reports to.
 *
 * @spec openspec/specs/orphaned-data-cleanup/spec.md
 */
class CleanupReporting {
	/**
	 * Constructor.
	 *
	 * @param IActivityManager $activityManager Activity event publisher.
	 * @param LoggerInterface  $logger PSR-3 logger used for purge audit lines.
	 */
	public function __construct(
		public readonly IActivityManager $activityManager,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()
}//end class
