<?php

/**
 * LockApiCaller
 *
 * The lock sources with the session and the ADR-023 action check of the lock API.
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
 * @spec openspec/specs/dashboard-locking/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Service\RequestGuard;

/**
 * The lock sources with the session and the ADR-023 action check of the lock API.
 *
 * @spec openspec/specs/dashboard-locking/spec.md
 */
class LockApiCaller {
	/**
	 * Constructor.
	 *
	 * @param LockSources  $sources The lock service and dashboard mapper.
	 * @param RequestGuard $guard   The session and ADR-023 action check.
	 */
	public function __construct(
		public readonly LockSources $sources,
		public readonly RequestGuard $guard,
	) {
	}//end __construct()
}//end class
