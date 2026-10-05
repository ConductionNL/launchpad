<?php

/**
 * TileLaunchSettingsController
 *
 * Administrator endpoints for the program schemes tiles may use and the
 * single sign-on launch templates tiles pick from
 * (launcher-tile-launch-types, REQ-TLT-001, REQ-TLT-003).
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
use OCA\LaunchPad\Service\TileLaunchSettingsService;
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
 * Tile launch settings administration.
 *
 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
 */
class TileLaunchSettingsController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest $request HTTP request.
	 * @param TileLaunchSettingsService $launchSettings Schemes and templates.
	 * @param ActionAuthService $actionAuth ADR-023 action authorization.
	 * @param IUserSession $userSession Current user.
	 * @param IGroupManager $groupManager Admin check.
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
	 */
	public function __construct(
		IRequest $request,
		private readonly TileLaunchSettingsService $launchSettings,
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
	 * The allowed schemes and the launch templates (administrators).
	 *
	 * @return JSONResponse `{allowedSchemes, ssoTemplates}`, 401 or 403.
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function index(): JSONResponse {
		$denial = $this->denial(action: 'tile-launch.list');
		if ($denial !== null) {
			return $denial;
		}

		return new JSONResponse(data: [
			'allowedSchemes' => $this->launchSettings->getAllowedSchemes(),
			'ssoTemplates' => $this->launchSettings->getSsoTemplates(),
		]);
	}//end index()

	/**
	 * Replace the allowed schemes and the launch templates (administrators).
	 * Both are checked before either is stored; a list left out is unchanged.
	 *
	 * @param array|null $allowedSchemes The schemes program tiles may use.
	 * @param array|null $ssoTemplates   The launch templates, each `{name, urlTemplate, key?}`.
	 *
	 * @return JSONResponse `{allowedSchemes, ssoTemplates}`, 400, 401 or 403.
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function save(?array $allowedSchemes = null, ?array $ssoTemplates = null): JSONResponse {
		$denial = $this->denial(action: 'tile-launch.save');
		if ($denial !== null) {
			return $denial;
		}

		try {
			$saved = $this->launchSettings->saveAll(schemes: $allowedSchemes, templates: $ssoTemplates);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(data: ['error' => $e->getMessage()], statusCode: Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse(data: $saved);
	}//end save()

	/**
	 * The refusal for a caller who is signed out, no administrator, or not
	 * allowed the action; null lets the caller through.
	 *
	 * @param string $action The ADR-023 action.
	 *
	 * @return JSONResponse|null
	 */
	private function denial(string $action): ?JSONResponse {
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
	}//end denial()
}//end class
