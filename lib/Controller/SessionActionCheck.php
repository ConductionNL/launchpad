<?php

/**
 * SessionActionCheck
 *
 * The session-user and ADR-023 action check of the analytics endpoints.
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
 * @spec openspec/specs/dashboard-view-analytics/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Service\ActionAuthService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IUserSession;

/**
 * No session user → 401; a denied ADR-023 action → 403.
 *
 * @spec openspec/specs/dashboard-view-analytics/spec.md
 */
final class SessionActionCheck {
	/**
	 * Constructor.
	 *
	 * @param IUserSession      $userSession The current user session.
	 * @param ActionAuthService $actionAuth  ADR-023 action authorization.
	 */
	public function __construct(
		private readonly IUserSession $userSession,
		private readonly ActionAuthService $actionAuth,
	) {
	}//end __construct()

	/**
	 * The refusal for a caller who may not perform the action, or NULL to proceed.
	 *
	 * @param string $action The action identifier to require.
	 *
	 * @return JSONResponse|null The 401/403 response, or NULL.
	 *
	 * @spec openspec/specs/dashboard-view-analytics/spec.md
	 */
	public function refusal(string $action): ?JSONResponse {
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
