<?php

/**
 * AnalyticsOptOutController
 *
 * The signed-in person's own analytics opt-out (REQ-ANLT-UI-001): read and
 * write `analytics_optout` for the caller and nobody else. A request that
 * names another user is refused, so the route can never set a colleague's
 * preference.
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
 * @spec openspec/changes/analytics-personal-opt-out/specs/dashboard-view-analytics/spec.md#requirement-a-person-turns-view-counting-off-in-their-own-settings-req-anlt-ui-001
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\AnalyticsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Read and write the caller's own analytics opt-out.
 *
 * @spec openspec/changes/analytics-personal-opt-out/specs/dashboard-view-analytics/spec.md#requirement-a-person-turns-view-counting-off-in-their-own-settings-req-anlt-ui-001
 */
class AnalyticsOptOutController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest         $request     The request.
	 * @param IUserSession     $userSession Names the caller.
	 * @param AnalyticsService $analytics   Reads and writes the opt-out.
	 */
	public function __construct(
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly AnalyticsService $analytics,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Whether analytics is on for everyone, and whether the caller opted out.
	 *
	 * @return JSONResponse `{enabled: bool, optedOut: bool}`, or 401 signed out.
	 *
	 * @spec openspec/changes/analytics-personal-opt-out/specs/dashboard-view-analytics/spec.md#requirement-a-person-turns-view-counting-off-in-their-own-settings-req-anlt-ui-001
	 */
	#[NoAdminRequired]
	public function show(): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(data: ['message' => 'Not logged in'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		return new JSONResponse(
			data: [
				'enabled' => $this->analytics->isGloballyEnabled(),
				'optedOut' => $this->analytics->isUserOptedOut(userId: $user->getUID()),
			]
		);
	}//end show()

	/**
	 * Store the caller's own opt-out.
	 *
	 * @param bool        $optedOut `true` to stop counting the caller's visits.
	 * @param string|null $userId   A user id the request names; anything but the caller is refused.
	 *
	 * @return JSONResponse `{enabled, optedOut}`; 401 signed out, 403 for another user.
	 *
	 * @spec openspec/changes/analytics-personal-opt-out/specs/dashboard-view-analytics/spec.md#requirement-a-person-turns-view-counting-off-in-their-own-settings-req-anlt-ui-001
	 */
	#[NoAdminRequired]
	public function update(bool $optedOut = false, ?string $userId = null): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(data: ['message' => 'Not logged in'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		if ($userId !== null && $userId !== $user->getUID()) {
			return new JSONResponse(
				data: ['message' => 'You can only change your own analytics setting'],
				statusCode: Http::STATUS_FORBIDDEN
			);
		}

		return new JSONResponse(
			data: [
				'enabled' => $this->analytics->isGloballyEnabled(),
				'optedOut' => $this->analytics->setUserOptedOut(userId: $user->getUID(), optedOut: $optedOut),
			]
		);
	}//end update()
}//end class
