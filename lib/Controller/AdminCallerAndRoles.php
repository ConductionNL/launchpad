<?php

/**
 * AdminCallerAndRoles
 *
 * The caller (session and action check) and the role and feed-refresh
 * services of the admin API.
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
 * @spec openspec/specs/admin-settings/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\FeedRefreshService;
use OCA\LaunchPad\Service\RoleService;
use OCP\IUserSession;

/**
 * The caller (session and action check) and the role and feed-refresh
 * services of the admin API.
 *
 * @spec openspec/specs/admin-settings/spec.md
 */
class AdminCallerAndRoles {
	/**
	 * Constructor.
	 *
	 * @param RoleService        $roleService The LaunchPad role service (REQ-ROLE-001..011).
	 * @param FeedRefreshService $feedRefresh The background feed refresh service used by the on-demand admin
	 *                                        `refreshFeeds` action (REQ-BGJOB-FEED-005).
	 * @param IUserSession       $userSession The current user session.
	 * @param ActionAuthService  $actionAuth  ADR-023 action authorization.
	 */
	public function __construct(
		public readonly RoleService $roleService,
		public readonly FeedRefreshService $feedRefresh,
		public readonly IUserSession $userSession,
		public readonly ActionAuthService $actionAuth,
	) {
	}//end __construct()
}//end class
