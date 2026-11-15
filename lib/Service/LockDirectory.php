<?php

/**
 * LockDirectory
 *
 * The user and group managers of the lock service.
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

use OCP\IGroupManager;
use OCP\IUserManager;

/**
 * The user and group managers of the lock service.
 *
 * @spec openspec/specs/dashboard-locking/spec.md
 */
class LockDirectory {
	/**
	 * Constructor.
	 *
	 * @param IUserManager  $userManager Resolves display names for new locks.
	 * @param IGroupManager $groupManager Admin check for force-release.
	 */
	public function __construct(
		public readonly IUserManager $userManager,
		public readonly IGroupManager $groupManager,
	) {
	}//end __construct()
}//end class
