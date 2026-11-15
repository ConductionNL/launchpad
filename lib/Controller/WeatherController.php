<?php

/**
 * WeatherController
 *
 * HTTP entry point for the weather-widget capability. Exposes:
 *
 * - `GET /api/weather/{placementId}` — the cached (or freshly resolved)
 *   weather reading for one placement, view-time ACL guarded so a caller
 *   who cannot view the underlying dashboard never triggers a fetch
 *   (REQ-WEATHER-001).
 *
 * The response is exactly `{location, tempValue, units, condition,
 * conditionText, language, fetchedAt, stale}` — never the provider API key
 * or raw provider URL (REQ-WEATHER-001).
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
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\WeatherService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Throwable;

/**
 * Controller for the weather-widget capability.
 *
 * @spec openspec/specs/clock-weather-widgets/spec.md
 */
class WeatherController extends Controller {
	use RespondsWithErrors;

	/**
	 * Constructor.
	 *
	 * @param IRequest          $request HTTP request.
	 * @param WeatherService    $weatherService Resolves + caches the weather reading.
	 * @param PermissionService $permissionService Dashboard/placement permission gate.
	 * @param SessionLog        $session The session and logger.
	 */
	public function __construct(
		IRequest $request,
		private readonly WeatherService $weatherService,
		private readonly PermissionService $permissionService,
		private readonly SessionLog $session,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * `GET /api/weather/{placementId}`
	 *
	 * Returns the weather reading for one placement. Returns 401 when
	 * anonymous, 403 when the caller may not view the underlying
	 * dashboard (REQ-WEATHER-001 "Caller authorization" — the fetch is
	 * NEVER performed in that case), 502 when resolution failed with no
	 * cached reading available, else 200 with the reading (possibly
	 * `stale: true`).
	 *
	 * @param integer $placementId The widget placement id.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/specs/clock-weather-widgets/spec.md
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function show(int $placementId): JSONResponse {
		// REQ-WEATHER-001 "Caller authorization" — the auth guard runs
		// BEFORE any resolution/fetch is attempted.
		$userId = $this->session->placementViewer(permissions: $this->permissionService, placementId: $placementId);
		if ($userId instanceof JSONResponse) {
			return $userId;
		}

		try {
			$reading = $this->weatherService->resolveForPlacement(
				placementId: $placementId,
				userId: $userId
			);
		} catch (Throwable $exception) {
			return $this->session->resolutionFailure(message: 'Unexpected weather resolution failure', exception: $exception);
		}

		return $this->session->readingResponse(reading: $reading, errorStatus: Http::STATUS_BAD_GATEWAY);
	}//end show()
}//end class
