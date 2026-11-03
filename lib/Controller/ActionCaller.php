<?php

/**
 * ActionCaller
 *
 * The caller: the session and the ADR-023 action check.
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
 * @spec openspec/specs/dashboard-reactions/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Service\ActionAuthService;
use OCP\IUserSession;

/**
 * The caller: the session and the ADR-023 action check.
 *
 * @spec openspec/specs/dashboard-reactions/spec.md
 */
class ActionCaller {
	/**
	 * Constructor.
	 *
	 * @param ActionAuthService $actionAuth ADR-023 action authorization.
	 * @param IUserSession      $userSession The current user session.
	 */
	public function __construct(
		public readonly ActionAuthService $actionAuth,
		public readonly IUserSession $userSession,
	) {
	}//end __construct()
}//end class
