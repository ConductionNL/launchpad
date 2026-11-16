<?php

/**
 * SearchShortcutsController
 *
 * Administrator endpoints for the search shortcuts (search-ai-prefix-shortcuts,
 * REQ-SPX-001). Every user receives the list through the workspace initial
 * state; only administrators read and write it here.
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
use OCA\LaunchPad\Service\SearchShortcutService;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Search shortcut administration.
 *
 * @spec openspec/specs/tile-quick-search/spec.md
 */
class SearchShortcutsController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest $request HTTP request.
	 * @param SearchShortcutService $shortcuts Shortcut service.
	 * @param ActionAuthService $actionAuth ADR-023 action authorization.
	 * @param IUserSession $userSession Current user.
	 * @param IGroupManager $groupManager Admin check.
	 *
	 * @spec openspec/specs/tile-quick-search/spec.md
	 */
	public function __construct(
		IRequest $request,
		private readonly SearchShortcutService $shortcuts,
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
	 * The shortcut list (administrators).
	 *
	 * @return JSONResponse `{shortcuts: [...]}`, 401 or 403.
	 *
	 * @spec openspec/specs/tile-quick-search/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function index(): JSONResponse {
		$refused = $this->actionRefusal(action: 'search-shortcut.list');
		if ($refused !== null) {
			return $refused;
		}

		return new JSONResponse(data: ['shortcuts' => $this->shortcuts->getShortcuts()]);
	}//end index()

	/**
	 * Replace the shortcut list (administrators).
	 *
	 * @param array|null $shortcuts The new list.
	 *
	 * @return JSONResponse `{shortcuts: [...]}`, 400, 401 or 403.
	 *
	 * @spec openspec/specs/tile-quick-search/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function save(?array $shortcuts = null): JSONResponse {
		$refused = $this->actionRefusal(action: 'search-shortcut.save');
		if ($refused !== null) {
			return $refused;
		}

		try {
			$saved = $this->shortcuts->saveShortcuts(raw: ($shortcuts ?? []));
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(data: ['error' => $e->getMessage()], statusCode: Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse(data: ['shortcuts' => $saved]);
	}//end save()

	/**
	 * The refusal for a caller who may not run the action: 401 without a
	 * user, 403 for a non-administrator or when ADR-023 action authorization
	 * denies it; null when the caller may proceed.
	 *
	 * @param string $action The ADR-023 action id.
	 *
	 * @return JSONResponse|null The refusal, or null.
	 */
	private function actionRefusal(string $action): ?JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		if ($this->groupManager->isAdmin(userId: $user->getUID()) === false) {
			return new JSONResponse(data: ['error' => 'Admin required'], statusCode: Http::STATUS_FORBIDDEN);
		}

		try {
			$this->actionAuth->requireAction($user, $action);
		} catch (OCSForbiddenException) {
			return new JSONResponse(data: ['error' => 'Forbidden'], statusCode: Http::STATUS_FORBIDDEN);
		}

		return null;
	}//end actionRefusal()
}//end class
