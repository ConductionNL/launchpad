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
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
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

	/**
	 * The refusal for a caller who may not perform the action, or NULL to proceed.
	 *
	 * No session user is a 401; a denied ADR-023 action is a 403 (the controllers
	 * using this are not OCS controllers, so a thrown OCSForbiddenException
	 * would reach the client as a 500); a session without a controller user ID
	 * is a 401 after the action check.
	 *
	 * @param string|null $userId The controller's user ID.
	 * @param string      $action The action identifier to require.
	 *
	 * @return JSONResponse|null The refusal, or NULL to proceed.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function refusal(string|null $userId, string $action): ?JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$this->actionAuth->requireAction($user, $action);
		} catch (OCSForbiddenException) {
			return new JSONResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
		}

		if ($userId === null) {
			return new JSONResponse(['error' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		return null;
	}//end refusal()
}//end class
