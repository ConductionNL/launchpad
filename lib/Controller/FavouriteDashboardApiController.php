<?php

/**
 * FavouriteDashboardApiController
 *
 * Star and unstar a dashboard for the More menu's favourites (REQ-SHELL-016):
 *   - POST   /api/dashboards/{uuid}/favourite  (idempotent)
 *   - DELETE /api/dashboards/{uuid}/favourite  (idempotent)
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

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\FavouriteDashboardService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Favourite dashboard endpoints. A favourite is the person's own preference,
 * so every signed-in person may star the dashboards they can open; one they
 * cannot open answers 404 (REQ-DASH-013).
 */
class FavouriteDashboardApiController extends Controller {
	/**
	 * Constructor
	 *
	 * @param IRequest                  $request     The request.
	 * @param FavouriteDashboardService $favourites  The favourites store.
	 * @param IUserSession              $userSession The signed-in person.
	 *
	 * @spec openspec/changes/more-menu-favourites/tasks.md#task-1
	 */
	public function __construct(
		IRequest $request,
		private readonly FavouriteDashboardService $favourites,
		private readonly IUserSession $userSession,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * POST /api/dashboards/{uuid}/favourite: star a dashboard.
	 *
	 * @param string $uuid The dashboard UUID.
	 *
	 * @return JSONResponse `{favourites: string[]}`, 404 or 401.
	 *
	 * @spec openspec/changes/more-menu-favourites/tasks.md#task-1
	 */
	#[NoAdminRequired]
	public function star(string $uuid): JSONResponse {
		return $this->change(uuid: $uuid, star: true);
	}//end star()

	/**
	 * DELETE /api/dashboards/{uuid}/favourite: unstar a dashboard.
	 *
	 * @param string $uuid The dashboard UUID.
	 *
	 * @return JSONResponse `{favourites: string[]}`, 404 or 401.
	 *
	 * @spec openspec/changes/more-menu-favourites/tasks.md#task-1
	 */
	#[NoAdminRequired]
	public function unstar(string $uuid): JSONResponse {
		return $this->change(uuid: $uuid, star: false);
	}//end unstar()

	/**
	 * Star or unstar for the signed-in person and answer the new list.
	 *
	 * @param string $uuid The dashboard UUID.
	 * @param bool   $star True to star, false to unstar.
	 *
	 * @return JSONResponse The list, 404 for a dashboard the person cannot open, 401 without a session.
	 */
	private function change(string $uuid, bool $star): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		$method = 'unstar';
		if ($star === true) {
			$method = 'star';
		}

		try {
			$favourites = $this->favourites->$method(userId: $user->getUID(), uuid: $uuid);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(data: ['error' => 'Dashboard not found'], statusCode: Http::STATUS_NOT_FOUND);
		}

		return new JSONResponse(data: ['favourites' => $favourites]);
	}//end change()
}//end class
