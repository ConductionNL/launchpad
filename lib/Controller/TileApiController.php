<?php

/**
 * TileApiController
 *
 * Controller for tile API endpoints.
 *
 * Per REQ-TILE-001 (DEPRECATED) the write endpoints (`create`, `update`,
 * `destroy`) MUST return HTTP 410 Gone with the documented envelope so
 * legacy clients are explicitly directed at the unified add-widget flow
 * (REQ-WDG-022 / REQ-TILE-PLACEMENT). The read endpoint (`index`) keeps
 * working so admin tooling and migration scripts can still inspect the
 * existing `oc_launchpad_tiles` rows during the deprecation window.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\ServiceDiscoveryService;
use OCA\LaunchPad\Service\TileService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Controller for tile API endpoints.
 */
class TileApiController extends Controller {
	/**
	 * Replacement pointer surfaced in the HTTP 410 Gone envelope. Kept as
	 * a class constant so the controller, its tests, and any future
	 * migration tooling reference the same string.
	 *
	 * @var string
	 */
	public const REPLACEMENT_HINT = 'POST /api/dashboards/{uuid}/widgets with type:tile';

	/**
	 * Constructor
	 *
	 * @param IRequest $request The request.
	 * @param TileService $tileService The tile service.
	 * @param ActionAuthService $actionAuth ADR-023 action authorization.
	 * @param IUserSession $userSession User session (IUser resolution).
	 * @param IL10N $l10n The localisation helper.
	 * @param ServiceDiscoveryService $discovery The apps in the user's app menu.
	 * @param string|null $userId The user ID.
	 */
	public function __construct(
		IRequest $request,
		private readonly TileService $tileService,
		private readonly ActionAuthService $actionAuth,
		private readonly IUserSession $userSession,
		private readonly IL10N $l10n,
		private readonly ServiceDiscoveryService $discovery,
		private readonly ?string $userId,
	) {
		parent::__construct(
			appName: Application::APP_ID,
			request: $request
		);
	}//end __construct()

	/**
	 * List all tiles for the current user.
	 *
	 * Read-only endpoint kept for backwards compatibility per the
	 * DEPRECATED REQ-TILE-001 spec — admin tooling and migration scripts
	 * may still inspect `oc_launchpad_tiles` rows during the deprecation
	 * window. The write endpoints return HTTP 410 Gone instead.
	 *
	 * @return JSONResponse The list of tiles.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-29
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}

		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$this->actionAuth->requireAction($user, 'tile.index');
		} catch (OCSForbiddenException) {
			return new JSONResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
		}

		$tiles = $this->tileService->getUserTiles(userId: $this->userId);

		return ResponseHelper::success(
			data: ResponseHelper::serializeList(entities: $tiles)
		);
	}//end index()

	/**
	 * The apps this server runs for the caller, to place as tiles.
	 *
	 * Answers only the caller's own app menu: there is no id parameter, so
	 * nothing to point at someone else (REQ-DISC-001). Placing a tile still
	 * goes through the dashboard's own add-tile check.
	 *
	 * @return JSONResponse `{apps: [{appId, name, href, icon}]}`.
	 *
	 * @spec openspec/changes/launcher-discover-server-services/specs/tiles/spec.md#requirement-the-add-flow-lists-the-apps-this-server-runs-for-the-user-req-disc-001
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function discover(): JSONResponse {
		$user = $this->userSession->getUser();
		if ($this->userId === null || $user === null) {
			return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$this->actionAuth->requireAction($user, 'tile.discover');
		} catch (OCSForbiddenException) {
			return new JSONResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
		}

		return new JSONResponse(['apps' => $this->discovery->discover()]);
	}//end discover()

	/**
	 * Create a new tile.
	 *
	 * DEPRECATED — returns HTTP 410 Gone. New tile placements MUST be
	 * created via the unified add-widget flow with `type: tile`
	 * (REQ-WDG-022 / REQ-TILE-PLACEMENT). Parameters retained for
	 * route binding compatibility only and intentionally ignored.
	 *
	 * @return JSONResponse The HTTP 410 Gone envelope.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-28
	 */
	#[NoAdminRequired]
	public function create(): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}

		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$this->actionAuth->requireAction($user, 'tile.create');
		} catch (OCSForbiddenException) {
			return new JSONResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
		}

		return $this->goneResponse();
	}//end create()

	/**
	 * Update a tile.
	 *
	 * DEPRECATED — returns HTTP 410 Gone. Tile placement edits MUST go
	 * through the standard widget-placement update endpoint
	 * (REQ-WDG-022 / REQ-TILE-PLACEMENT). The legacy parameter list is
	 * preserved so existing routing wiring continues to bind without
	 * raising an `InvalidArgumentException`.
	 *
	 * @return JSONResponse The HTTP 410 Gone envelope.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-30
	 */
	#[NoAdminRequired]
	public function update(): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}

		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$this->actionAuth->requireAction($user, 'tile.update');
		} catch (OCSForbiddenException) {
			return new JSONResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
		}

		return $this->goneResponse();
	}//end update()

	/**
	 * Delete a tile.
	 *
	 * DEPRECATED — returns HTTP 410 Gone. Tile placement removal goes
	 * through the standard widget-placement delete endpoint going
	 * forward (REQ-WDG-022 / REQ-TILE-PLACEMENT).
	 *
	 * @return JSONResponse The HTTP 410 Gone envelope.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-31
	 */
	#[NoAdminRequired]
	public function destroy(): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}

		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$this->actionAuth->requireAction($user, 'tile.destroy');
		} catch (OCSForbiddenException) {
			return new JSONResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
		}

		return $this->goneResponse();
	}//end destroy()

	/**
	 * Build the HTTP 410 Gone envelope shared by every write endpoint.
	 *
	 * @return JSONResponse The envelope `{status, message, replacement}`.
	 */
	private function goneResponse(): JSONResponse {
		$message = $this->l10n->t(
			'The reusable tile API is no longer available. Use the unified add-widget flow with type:tile instead.'
		);

		return new JSONResponse(
			data: [
				'status' => 'gone',
				'message' => $message,
				'replacement' => self::REPLACEMENT_HINT,
			],
			statusCode: Http::STATUS_GONE
		);
	}//end goneResponse()
}//end class
