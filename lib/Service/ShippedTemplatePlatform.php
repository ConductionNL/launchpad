<?php

/**
 * ShippedTemplatePlatform
 *
 * The dashboard widget manager and the group manager of the shipped template service.
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
 * @spec openspec/specs/admin-templates/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\Dashboard\IManager;
use OCP\IGroupManager;

/**
 * The dashboard widget manager and the group manager of the shipped template service.
 *
 * @spec openspec/specs/admin-templates/spec.md
 */
class ShippedTemplatePlatform {
	/**
	 * Constructor.
	 *
	 * @param IManager      $dashboardMgr Nextcloud's dashboard widget registry.
	 * @param IGroupManager $groupManager Checks that a named group exists.
	 */
	public function __construct(
		public readonly IManager $dashboardMgr,
		public readonly IGroupManager $groupManager,
	) {
	}//end __construct()
}//end class
