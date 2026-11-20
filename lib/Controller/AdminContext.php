<?php

/**
 * AdminContext
 *
 * Collaborators AdminController takes as one value, so its constructor stays small.
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

/**
 * Collaborators AdminController takes as one value.
 */
class AdminContext {
	/**
	 * Constructor.
	 *
	 * @param AdminCallerAndRoles $caller The user session, the action check and the role and feed refresh services.
	 * @param ResponseHelper      $responseHelper Builds the JSON response envelopes. @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	public function __construct(
		public readonly AdminCallerAndRoles $caller,
		public readonly ResponseHelper $responseHelper,
	) {
	}//end __construct()
}//end class
