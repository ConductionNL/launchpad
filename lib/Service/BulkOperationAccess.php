<?php

/**
 * BulkOperationAccess
 *
 * Who may run a bulk operation and on what: permissions, groups and app config.
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
 * @spec openspec/specs/dashboards/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\IAppConfig;
use OCP\IGroupManager;

/**
 * Who may run a bulk operation and on what: permissions, groups and app config.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
class BulkOperationAccess {
	/**
	 * Constructor.
	 *
	 * @param PermissionService $permissionService Permission resolver used for the per-uuid admin pre-check
	 *                                             (REQ-BULK-011).
	 * @param IGroupManager     $groupManager Used for the "is the caller an NC admin" short-circuit on the
	 *                                        permission pre-check.
	 * @param IAppConfig        $appConfig App config for reading the per-request cap.
	 */
	public function __construct(
		public readonly PermissionService $permissionService,
		public readonly IGroupManager $groupManager,
		public readonly IAppConfig $appConfig,
	) {
	}//end __construct()
}//end class
