<?php

/**
 * LockStore
 *
 * The lock mapper and the logger of the lock service.
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
 * @spec openspec/specs/dashboard-locking/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\DashboardLockMapper;
use Psr\Log\LoggerInterface;

/**
 * The lock mapper and the logger of the lock service.
 *
 * @spec openspec/specs/dashboard-locking/spec.md
 */
class LockStore {
	/**
	 * Constructor.
	 *
	 * @param DashboardLockMapper $lockMapper The lock mapper.
	 * @param LoggerInterface     $logger PSR logger — used for the force-release audit trail (REQ-LOCK-006,
	 *                                    design D4).
	 */
	public function __construct(
		public readonly DashboardLockMapper $lockMapper,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()
}//end class
