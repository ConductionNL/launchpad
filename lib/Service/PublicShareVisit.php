<?php

/**
 * PublicShareVisit
 *
 * The token and client IP address of one anonymous visit to a public
 * dashboard share.
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
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-5
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * The token and client IP address of one public-share visit.
 *
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-5
 */
final class PublicShareVisit {
	/**
	 * Constructor.
	 *
	 * @param string $token     The share token.
	 * @param string $ipAddress The client IP address.
	 */
	public function __construct(
		public readonly string $token,
		public readonly string $ipAddress,
	) {
	}//end __construct()
}//end class
