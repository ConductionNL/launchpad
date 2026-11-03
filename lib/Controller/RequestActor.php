<?php

/**
 * RequestActor
 *
 * Who is calling: the user session and the action authorisation check.
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

use OCA\LaunchPad\Service\ActionAuthService;
use OCP\IUserSession;

/**
 * Who is calling: the user session and the action authorisation check.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
class RequestActor {
	/**
	 * Constructor.
	 *
	 * @param IUserSession      $userSession The user session, used to resolve the authenticated IUser for
	 *                                       ADR-023 action checks.
	 * @param ActionAuthService $actionAuth The ADR-023 action authorization service.
	 */
	public function __construct(
		public readonly IUserSession $userSession,
		public readonly ActionAuthService $actionAuth,
	) {
	}//end __construct()
}//end class
