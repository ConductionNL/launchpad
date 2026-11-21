<?php

/**
 * DashboardApiAccess
 *
 * Collaborators DashboardApiController takes as one value, so its constructor stays small.
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
 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\GroupDashboardCreation;

/**
 * Collaborators DashboardApiController takes as one value.
 */
class DashboardApiAccess {
	/**
	 * Constructor.
	 *
	 * @param PermissionService   $permissionService The permission service.
	 * @param DashboardApiHistory $history The tree, version and analytics services and the logger.
	 * @param GroupDashboardCreation|null $groupCreation Decides who may create a group dashboard (REQ-DASH-014).
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	public function __construct(
		public readonly PermissionService $permissionService,
		public readonly DashboardApiHistory $history,
		public readonly ?GroupDashboardCreation $groupCreation = null,
	) {
	}//end __construct()
}//end class
