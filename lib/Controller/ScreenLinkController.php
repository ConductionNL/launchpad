<?php

/**
 * ScreenLinkController
 *
 * The screen link of one dashboard, from its Sharing tab (REQ-KIOSK-006):
 * read, create (idempotent) and revoke. A screen link is a kiosk playlist of
 * that one dashboard, played at `/kiosk/{token}` like every playlist.
 * `#[NoAdminRequired]`: KioskService checks on every request that the caller
 * may share the dashboard, and for a create that screen links are on
 * (REQ-KIOSK-007).
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-4
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use Closure;
use Exception;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\KioskService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Read, create and revoke the screen link of a dashboard.
 *
 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-4
 */
class ScreenLinkController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest        $request      The incoming request.
	 * @param KioskService    $kioskService The kiosk-playlist service.
	 * @param LoggerInterface $logger       PSR-3 logger.
	 * @param string|null     $userId       Authenticated user ID.
	 */
	public function __construct(
		IRequest $request,
		private readonly KioskService $kioskService,
		private readonly LoggerInterface $logger,
		private readonly ?string $userId,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * The active screen link of the dashboard: 200 with the link, 204 when
	 * there is none, 403 for a caller who is neither owner nor admin.
	 *
	 * @param string $uuid The dashboard UUID.
	 *
	 * @return DataResponse The screen link, 204, 401 or 403.
	 *
	 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-4
	 */
	#[NoAdminRequired]
	public function show(string $uuid): DataResponse {
		return $this->respond(
			operation: function (string $callerId) use ($uuid): DataResponse {
				$link = $this->kioskService->getScreenLink(dashboardUuid: $uuid, callerId: $callerId);
				if ($link === null) {
					return new DataResponse(data: [], statusCode: Http::STATUS_NO_CONTENT);
				}

				return new DataResponse(data: $link->jsonSerialize());
			}
		);
	}//end show()

	/**
	 * Create the screen link, or return the existing one: 201 when made
	 * now, 200 when it existed, 403 when the caller may not share the
	 * dashboard or screen links are switched off.
	 *
	 * @param string $uuid The dashboard UUID.
	 *
	 * @return DataResponse The screen link, 401 or 403.
	 *
	 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-4
	 */
	#[NoAdminRequired]
	public function create(string $uuid): DataResponse {
		return $this->respond(
			operation: function (string $callerId) use ($uuid): DataResponse {
				$result = $this->kioskService->createScreenLink(dashboardUuid: $uuid, callerId: $callerId);
				$status = Http::STATUS_OK;
				if ($result['created'] === true) {
					$status = Http::STATUS_CREATED;
				}

				return new DataResponse(data: $result['playlist']->jsonSerialize(), statusCode: $status);
			}
		);
	}//end create()

	/**
	 * Revoke the screen link: 204, also when there was none; 403 for a
	 * caller who is neither owner nor admin.
	 *
	 * @param string $uuid The dashboard UUID.
	 *
	 * @return DataResponse 204, 401 or 403.
	 *
	 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-4
	 */
	#[NoAdminRequired]
	public function destroy(string $uuid): DataResponse {
		return $this->respond(
			operation: function (string $callerId) use ($uuid): DataResponse {
				$this->kioskService->revokeScreenLink(dashboardUuid: $uuid, callerId: $callerId);
				return new DataResponse(data: [], statusCode: Http::STATUS_NO_CONTENT);
			}
		);
	}//end destroy()

	/**
	 * Run a screen link request for the signed-in caller: no session → 401,
	 * not allowed → 403, anything else logged → 500.
	 *
	 * @param Closure $operation Takes the caller's user ID and builds the success response.
	 *
	 * @return DataResponse The response.
	 */
	private function respond(Closure $operation): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(data: ['error' => 'Not logged in'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		try {
			return $operation($this->userId);
		} catch (OCSForbiddenException) {
			return new DataResponse(data: ['error' => 'Not authorized'], statusCode: Http::STATUS_FORBIDDEN);
		} catch (Exception $e) {
			$this->logger->warning(
				message: 'ScreenLinkController error: ' . $e->getMessage(),
				context: ['app' => Application::APP_ID]
			);
			return new DataResponse(data: ['error' => 'Internal error'], statusCode: Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}//end respond()
}//end class
