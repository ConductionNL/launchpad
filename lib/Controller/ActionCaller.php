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
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
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

	/**
	 * The refusal for a caller who may not perform the action, or NULL to proceed.
	 *
	 * No user ID or no session user is a 401; a denied ADR-023 action is a 403.
	 *
	 * @param string|null $userId The controller's user ID.
	 * @param string      $action The action identifier to require.
	 *
	 * @return JSONResponse|null The 401/403 response, or NULL to proceed.
	 *
	 * @spec openspec/specs/dashboard-reactions/spec.md
	 */
	public function refusal(string|null $userId, string $action): ?JSONResponse {
		if ($userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}

		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$this->actionAuth->requireAction($user, $action);
		} catch (OCSForbiddenException) {
			return new JSONResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
		}

		return null;
	}//end refusal()
}//end class
