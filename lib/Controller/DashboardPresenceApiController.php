<?php

/**
 * DashboardPresenceApiController
 *
 * HTTP entry point for who is editing a dashboard
 * (building-edit-a-dashboard-together, task 1.1):
 *
 *  - `POST   /api/dashboards/{uuid}/presence` announce or renew; answers the colleagues editing it
 *  - `DELETE /api/dashboards/{uuid}/presence` leave
 *
 * Both carry `#[NoAdminRequired]`: announcing needs edit rights on the
 * dashboard, checked in the service; leaving only removes the caller's own
 * entry.
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
 * @spec openspec/changes/building-edit-a-dashboard-together/specs/dashboard-co-editing/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Service\DashboardPresenceService;
use OCA\LaunchPad\Service\RequestGuard;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Announces and withdraws an editor's presence on a dashboard.
 *
 * @spec openspec/changes/building-edit-a-dashboard-together/specs/dashboard-co-editing/spec.md
 */
class DashboardPresenceApiController extends Controller {
	use RespondsWithErrors;

	/**
	 * Constructor.
	 *
	 * @param IRequest                 $request  The request.
	 * @param DashboardPresenceService $presence The presence lists.
	 * @param RequestGuard             $guard    The action gate.
	 * @param string|null              $userId   The signed-in user.
	 *
	 * @spec openspec/changes/building-edit-a-dashboard-together/tasks.md#task-1.1
	 */
	public function __construct(
		IRequest $request,
		private readonly DashboardPresenceService $presence,
		private readonly RequestGuard $guard,
		private readonly ?string $userId,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Announce or renew the caller's editing of a dashboard.
	 *
	 * 200 with `{editors: [{displayName, since}]}`, the colleagues editing it;
	 * 401 without a user; 403 without edit rights; 404 for an unknown dashboard.
	 *
	 * @param string $uuid The dashboard UUID.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/building-edit-a-dashboard-together/tasks.md#task-1.1
	 */
	#[NoAdminRequired]
	public function join(string $uuid): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard-presence.join');
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			$editors = $this->presence->join(dashboardUuid: $uuid, userId: (string)$this->userId);
		} catch (DoesNotExistException) {
			return $this->failure(message: 'Dashboard not found', statusCode: Http::STATUS_NOT_FOUND);
		} catch (ForbiddenException $e) {
			return $this->failure(message: $e->getMessage(), statusCode: Http::STATUS_FORBIDDEN);
		}

		return new JSONResponse(data: ['editors' => $editors], statusCode: Http::STATUS_OK);
	}//end join()

	/**
	 * Withdraw the caller from a dashboard's editors. Idempotent: 204.
	 *
	 * @param string $uuid The dashboard UUID.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/building-edit-a-dashboard-together/tasks.md#task-1.1
	 */
	#[NoAdminRequired]
	public function leave(string $uuid): JSONResponse {
		$refusal = $this->actionRefusal(action: 'dashboard-presence.leave');
		if ($refusal !== null) {
			return $refusal;
		}

		$this->presence->leave(dashboardUuid: $uuid, userId: (string)$this->userId);
		return new JSONResponse(data: [], statusCode: Http::STATUS_NO_CONTENT);
	}//end leave()

	/**
	 * 401 without a user, else the action gate's answer.
	 *
	 * @param string $action The action key.
	 *
	 * @return JSONResponse|null
	 */
	private function actionRefusal(string $action): ?JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}

		return $this->guard->actionRefusal(action: $action);
	}//end actionRefusal()
}//end class
