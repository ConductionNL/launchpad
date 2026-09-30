<?php

/**
 * ProfileFieldsController
 *
 * HTTP entry points for custom profile fields (widgets-people-expertise-and-fields):
 * every signed-in person reads and saves their own values (REQ-PEX-002), and
 * an administrator reads and saves the field definitions (REQ-PEX-001).
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
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\ProfileFieldService;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

/**
 * Custom profile field endpoints.
 *
 * @spec openspec/specs/people-widget/spec.md
 */
class ProfileFieldsController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest $request HTTP request.
	 * @param ProfileFieldService $profileFields Profile field service.
	 * @param ActionAuthService $actionAuth ADR-023 action authorization.
	 * @param IUserSession $userSession Current user.
	 * @param IGroupManager $groupManager Admin check.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function __construct(
		IRequest $request,
		private readonly ProfileFieldService $profileFields,
		private readonly ActionAuthService $actionAuth,
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
	) {
		parent::__construct(
			appName: Application::APP_ID,
			request: $request
		);
	}//end __construct()

	/**
	 * The caller's own fields and values. Each person reads only their own.
	 *
	 * @return JSONResponse `{fields: [...]}`, 401 or 403.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	#[NoAdminRequired]
	public function getOwn(): JSONResponse {
		$user = $this->userSession->getUser();
		$guard = $this->guard(user: $user, action: 'profile-fields.get-own');
		if ($guard !== null || $user === null) {
			return $guard ?? self::unauthenticated();
		}

		return new JSONResponse(data: ['fields' => $this->profileFields->getOwnFields(userId: $user->getUID())]);
	}//end getOwn()

	/**
	 * Save the caller's own values. Each person writes only their own; LDAP
	 * fields stay read-only.
	 *
	 * @param array|null $values Map of field key to a string or a list of tags.
	 *
	 * @return JSONResponse `{fields: [...]}`, 400, 401 or 403.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	#[NoAdminRequired]
	public function saveOwn(?array $values = null): JSONResponse {
		$user = $this->userSession->getUser();
		$guard = $this->guard(user: $user, action: 'profile-fields.save-own');
		if ($guard !== null || $user === null) {
			return $guard ?? self::unauthenticated();
		}

		try {
			$fields = $this->profileFields->saveOwnValues(userId: $user->getUID(), values: ($values ?? []));
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(data: ['error' => $e->getMessage()], statusCode: Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse(data: ['fields' => $fields]);
	}//end saveOwn()

	/**
	 * The field definitions (administrators).
	 *
	 * @return JSONResponse `{fields: [...]}`, 401 or 403.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function getDefinitions(): JSONResponse {
		$guard = $this->adminGuard(action: 'profile-fields.get-definitions');
		if ($guard !== null) {
			return $guard;
		}

		return new JSONResponse(data: ['fields' => $this->profileFields->getDefinitions()]);
	}//end getDefinitions()

	/**
	 * Replace the field definitions (administrators).
	 *
	 * @param array|null $fields The definitions.
	 *
	 * @return JSONResponse `{fields: [...]}`, 400, 401 or 403.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function saveDefinitions(?array $fields = null): JSONResponse {
		$guard = $this->adminGuard(action: 'profile-fields.save-definitions');
		if ($guard !== null) {
			return $guard;
		}

		try {
			$saved = $this->profileFields->saveDefinitions(raw: ($fields ?? []));
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(data: ['error' => $e->getMessage()], statusCode: Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse(data: ['fields' => $saved]);
	}//end saveDefinitions()

	/**
	 * Signed-in and allowed to call the action.
	 *
	 * @param IUser|null $user The caller.
	 * @param string $action ADR-023 action id.
	 *
	 * @return JSONResponse|null An error response, or null when allowed.
	 */
	private function guard(?IUser $user, string $action): ?JSONResponse {
		if ($user === null) {
			return self::unauthenticated();
		}

		try {
			$this->actionAuth->requireAction($user, $action);
		} catch (OCSForbiddenException) {
			return new JSONResponse(data: ['error' => 'Forbidden'], statusCode: Http::STATUS_FORBIDDEN);
		}

		return null;
	}//end guard()

	/**
	 * Signed-in administrator allowed to call the action.
	 *
	 * @param string $action ADR-023 action id.
	 *
	 * @return JSONResponse|null An error response, or null when allowed.
	 */
	private function adminGuard(string $action): ?JSONResponse {
		$user = $this->userSession->getUser();
		if ($user !== null && $this->groupManager->isAdmin(userId: $user->getUID()) === false) {
			return new JSONResponse(data: ['error' => 'Admin required'], statusCode: Http::STATUS_FORBIDDEN);
		}

		return $this->guard(user: $user, action: $action);
	}//end adminGuard()

	/**
	 * The 401 response.
	 *
	 * @return JSONResponse
	 */
	private static function unauthenticated(): JSONResponse {
		return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
	}//end unauthenticated()
}//end class
