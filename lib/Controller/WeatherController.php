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
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Controller for the weather-widget capability.
 *
 * @spec openspec/specs/clock-weather-widgets/spec.md
 */
class WeatherController extends Controller
{
    /**
     * Constructor.
     *
     * @param IRequest          $request           HTTP request.
     * @param WeatherService    $weatherService    Resolves + caches the weather reading.
     * @param PermissionService $permissionService Dashboard/placement permission gate.
     * @param IUserSession      $userSession       Session accessor.
     * @param LoggerInterface   $logger            PSR logger.
     */
    public function __construct(
        IRequest $request,
        private readonly WeatherService $weatherService,
        private readonly PermissionService $permissionService,
        private readonly IUserSession $userSession,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct(
            appName: Application::APP_ID,
            request: $request
        );
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
    public function show(int $placementId): JSONResponse
    {
        $userId = $this->resolveUserId();
        if ($userId === null) {
            return new JSONResponse(
                data: ['status' => 'error', 'error' => 'unauthorized'],
                statusCode: Http::STATUS_UNAUTHORIZED
            );
        }

        // REQ-WEATHER-001 "Caller authorization" — the auth guard runs
        // BEFORE any resolution/fetch is attempted.
        if ($this->permissionService->canViewPlacement(userId: $userId, placementId: $placementId) === false) {
            return new JSONResponse(
                data: ['status' => 'error', 'error' => 'forbidden'],
                statusCode: Http::STATUS_FORBIDDEN
            );
        }

        try {
            $reading = $this->weatherService->resolveForPlacement(
                placementId: $placementId,
                userId: $userId
            );
        } catch (Throwable $exception) {
            $this->logger->error(
                message: 'Unexpected weather resolution failure',
                context: ['app' => Application::APP_ID, 'exception' => $exception->getMessage()]
            );
            return new JSONResponse(
                data: ['status' => 'error', 'error' => 'unknown_error'],
                statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

        if (isset($reading['error']) === true) {
            return new JSONResponse(
                data: ['status' => 'error', 'error' => $reading['error']],
                statusCode: Http::STATUS_BAD_GATEWAY
            );
        }

        return new JSONResponse(
            data: $reading,
            statusCode: Http::STATUS_OK
        );
    }//end show()

    /**
     * Resolve the active user's UID, or `null` for anonymous.
     *
     * @return string|null
     */
    private function resolveUserId(): ?string
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return null;
        }

        return $user->getUID();
    }//end resolveUserId()
}//end class
