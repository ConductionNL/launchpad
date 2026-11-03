<?php

/**
 * ShareGrant
 *
 * One requested dashboard share: who it is for and at which permission
 * level (REQ-SHARE-001, REQ-SHARE-009).
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
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * Share type, recipient and permission level of a share being added or
 * kept. DashboardShareService validates the values before it writes.
 *
 * @spec openspec/specs/dashboard-sharing/spec.md
 */
final class ShareGrant {
	/**
	 * Constructor
	 *
	 * @param string $shareType       The share type ('user' or 'group').
	 * @param string $shareWith       The recipient user or group id.
	 * @param string $permissionLevel The permission level.
	 */
	public function __construct(
		public readonly string $shareType,
		public readonly string $shareWith,
		public readonly string $permissionLevel,
	) {
	}//end __construct()
}//end class
