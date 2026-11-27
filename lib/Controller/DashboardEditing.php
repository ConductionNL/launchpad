<?php

/**
 * DashboardEditing
 *
 * What the presence endpoints work with: the editors list and the edit session's version.
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
 * @spec openspec/changes/archive/2026-11-27-versions-on-widget-edits/tasks.md#task-2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Service\DashboardPresenceService;
use OCA\LaunchPad\Service\Versions\EditSessionVersions;

/**
 * The presence lists and the edit session's version, for
 * {@see DashboardPresenceApiController}.
 *
 * @spec openspec/changes/archive/2026-11-27-versions-on-widget-edits/tasks.md#task-2
 */
class DashboardEditing {
	/**
	 * Constructor.
	 *
	 * @param DashboardPresenceService $presence The presence lists.
	 * @param EditSessionVersions|null $sessions Keeps the state at the start of editing and stores it as a version
	 *                                           when editing ends (REQ-VERS-010); none when not injected.
	 *
	 * @spec openspec/changes/archive/2026-11-27-versions-on-widget-edits/tasks.md#task-2
	 */
	public function __construct(
		public readonly DashboardPresenceService $presence,
		public readonly ?EditSessionVersions $sessions=null,
	) {
	}//end __construct()
}//end class
