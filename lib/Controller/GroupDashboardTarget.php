<?php

/**
 * GroupDashboardTarget
 *
 * The group and UUID of the group-shared dashboard a group action addresses.
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
 * @spec openspec/specs/dashboards/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

/**
 * The group and UUID of the group-shared dashboard a group action addresses.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
final class GroupDashboardTarget {
	/**
	 * Constructor.
	 *
	 * @param string $groupId The group ID from the URL.
	 * @param string $uuid    The dashboard UUID from the URL.
	 */
	public function __construct(
		public readonly string $groupId,
		public readonly string $uuid,
	) {
	}//end __construct()
}//end class
