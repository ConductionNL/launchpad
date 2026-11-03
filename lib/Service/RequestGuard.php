<?php

/**
 * RequestGuard
 *
 * The administrator and action checks of the API controllers.
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
 * @spec openspec/specs/admin-templates/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;

/**
 * Checks the signed-in user of a request, and answers the refusal the API
 * returns otherwise: 401 `Not authenticated` without a session user, 403
 * `Admin required` for a user outside the admin group, 403 `Forbidden` for
 * a user the action is not granted to.
 *
 * @spec openspec/specs/admin-templates/spec.md
 */
class RequestGuard {
	/**
	 * Constructor.
	 *
	 * @param IUserSession      $userSession  The session of the request.
	 * @param IGroupManager     $groupManager Answers whether a user is an administrator.
	 * @param ActionAuthService $actionAuth   Answers whether an action is granted to a user.
	 */
	public function __construct(
		public readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
		private readonly ActionAuthService $actionAuth,
	) {
	}//end __construct()

	/**
	 * The refusal for a caller who is not a signed-in administrator.
	 *
	 * @return JSONResponse|null The 401 or 403 to return, or null for an administrator.
	 *
	 * @spec openspec/specs/admin-templates/spec.md
	 */
	public function refusal(): ?JSONResponse {
		$adminId = $this->adminId();
		if ($adminId instanceof JSONResponse) {
			return $adminId;
		}

		return null;
	}//end refusal()

	/**
	 * The signed-in user, or the refusal.
	 *
	 * @return IUser|JSONResponse The user, or the 401 to return.
	 *
	 * @spec openspec/specs/admin-roles/spec.md
	 */
	public function sessionUser(): IUser|JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(
				data: ['error' => 'Not authenticated'],
				statusCode: Http::STATUS_UNAUTHORIZED
			);
		}

		return $user;
	}//end sessionUser()

	/**
	 * The signed-in administrator's id, or the refusal.
	 *
	 * @return string|JSONResponse The user id, or the 401/403 to return.
	 *
	 * @spec openspec/specs/admin-templates/spec.md
	 */
	public function adminId(): string|JSONResponse {
		$user = $this->sessionUser();
		if ($user instanceof JSONResponse) {
			return $user;
		}

		if ($this->groupManager->isAdmin(userId: $user->getUID()) === false) {
			return new JSONResponse(
				data: ['error' => 'Admin required'],
				statusCode: Http::STATUS_FORBIDDEN
			);
		}

		return $user->getUID();
	}//end adminId()

	/**
	 * The refusal for a caller the action is not granted to.
	 *
	 * @param string $action The action key, such as `dashboard-lock.acquire`.
	 *
	 * @return JSONResponse|null The 401 or 403 to return, or null when granted.
	 *
	 * @spec openspec/specs/admin-roles/spec.md
	 */
	public function actionRefusal(string $action): ?JSONResponse {
		$user = $this->sessionUser();
		if ($user instanceof JSONResponse) {
			return $user;
		}

		try {
			$this->actionAuth->requireAction($user, $action);
		} catch (OCSForbiddenException) {
			return new JSONResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
		}

		return null;
	}//end actionRefusal()
}//end class
