<?php

/**
 * FeedRefreshRunTools
 *
 * What one feed refresh run uses: the refresh service and the lock that keeps runs apart.
 *
 * @category  Job
 * @package   OCA\LaunchPad\Job
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/widgets/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Job;

use OCA\LaunchPad\Service\FeedRefreshService;
use OCP\Lock\ILockingProvider;

/**
 * What one feed refresh run uses: the refresh service and the lock that keeps runs apart.
 *
 * @spec openspec/specs/widgets/spec.md
 */
class FeedRefreshRunTools {
	/**
	 * Constructor.
	 *
	 * @param FeedRefreshService $refreshService The refresh worker.
	 * @param ILockingProvider   $lockingProvider The cluster lock provider.
	 */
	public function __construct(
		public readonly FeedRefreshService $refreshService,
		public readonly ILockingProvider $lockingProvider,
	) {
	}//end __construct()
}//end class
