<?php

/**
 * LiveTileController
 *
 * HTTP entry point for the live-data tile capability. Exposes:
 *
 * - `GET /api/livetile/{placementId}` — the cached (or freshly resolved)
 *   value for one placement, view-time ACL guarded so a caller who cannot
 *   view the underlying dashboard never triggers a fetch
 *   (REQ-LIVETILE-003 "Caller authorization").
 * - `GET /api/livetile/connector/status` — whether the OpenConnector
 *   `dashboard-http-datasource` capability is currently available, so the
 *   config form can hide/disable `connector` source mode
 *   (REQ-LIVETILE-005).
 * - `POST /api/livetile/validate-source` — validates a candidate source
 *   config (host allow-list, refresh bounds) before the author saves the
 *   placement (REQ-LIVETILE-002 "rejected at save time").
 *
 * The value-fetch response is exactly `{value, formatted, badge,
 * fetchedAt, stale}` — never the source URL, headers, or credentials
 * (REQ-LIVETILE-003).
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
use OCA\LaunchPad\Service\LiveTileService;
use OCA\LaunchPad\Service\PermissionService;
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
 * Controller for the live-data tile capability.
 *
 * @spec openspec/specs/live-data-tile-widget/spec.md
 */
class LiveTileController extends Controller
{

    /**
     * Constructor.
     *
     * @param IRequest           $request           HTTP request.
     * @param LiveTileService    $liveTileService    Resolves + caches + validates live-tile values.
     * @param PermissionService  $permissionService  Dashboard/placement permission gate.
     * @param IUserSession       $userSession        Session accessor.
     * @param LoggerInterface    $logger             PSR logger.
     */
    public function __construct(
        IRequest $request,
        private readonly LiveTileService $liveTileService,
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
     * `GET /api/livetile/{placementId}`
     *
     * Returns the live-tile value for one placement. Returns 401 when
     * anonymous, 403 when the caller may not view the underlying
     * dashboard (REQ-LIVETILE-003 "Caller authorization" — the fetch is
     * NEVER performed in that case), 404 when the placement does not
     * exist, else 200 with the value (possibly `stale: true`).
     *
     * @param integer $placementId The widget placement id.
     *
     * @return JSONResponse
     *
     * @spec openspec/specs/live-data-tile-widget/spec.md
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

        // REQ-LIVETILE-003 "Caller authorization" — the auth guard runs
        // BEFORE any resolution/fetch is attempted.
        if ($this->permissionService->canViewPlacement(userId: $userId, placementId: $placementId) === false) {
            return new JSONResponse(
                data: ['status' => 'error', 'error' => 'forbidden'],
                statusCode: Http::STATUS_FORBIDDEN
            );
        }

        try {
            $reading = $this->liveTileService->resolveForPlacement(placementId: $placementId);
        } catch (Throwable $exception) {
            $this->logger->error(
                message: 'Unexpected live-tile resolution failure',
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
                statusCode: Http::STATUS_NOT_FOUND
            );
        }

        return new JSONResponse(
            data: $reading,
            statusCode: Http::STATUS_OK
        );
    }//end show()

    /**
     * `GET /api/livetile/connector/status`
     *
     * Reports whether the OpenConnector `dashboard-http-datasource`
     * capability is currently available, so the config form can hide or
     * disable `connector` source mode (REQ-LIVETILE-005). Requires only
     * an authenticated caller — carries no placement-specific data.
     *
     * @return JSONResponse `{available: bool}`.
     *
     * @spec openspec/specs/live-data-tile-widget/spec.md
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function connectorStatus(): JSONResponse
    {
        if ($this->resolveUserId() === null) {
            return new JSONResponse(
                data: ['status' => 'error', 'error' => 'unauthorized'],
                statusCode: Http::STATUS_UNAUTHORIZED
            );
        }

        return new JSONResponse(
            data: ['available' => $this->liveTileService->isConnectorAvailable()],
            statusCode: Http::STATUS_OK
        );
    }//end connectorStatus()

    /**
     * `POST /api/livetile/validate-source`
     *
     * Validates a candidate source config before the author saves the
     * placement (REQ-LIVETILE-002 "rejected at save time" — host
     * allow-list, fail-closed). Performs NO fetch — only the allow-list
     * / capability-probe checks that `resolveForPlacement()` would apply.
     *
     * @return JSONResponse `{valid: bool, errors: string[]}`.
     *
     * @spec openspec/specs/live-data-tile-widget/spec.md
     */
    #[NoAdminRequired]
    public function validateSource(): JSONResponse
    {
        if ($this->resolveUserId() === null) {
            return new JSONResponse(
                data: ['status' => 'error', 'error' => 'unauthorized'],
                statusCode: Http::STATUS_UNAUTHORIZED
            );
        }

        $config = $this->request->getParam(key: 'config');
        if (is_array(value: $config) === false) {
            $config = [];
        }

        $errors = $this->liveTileService->validateSourceConfig(config: $config);

        return new JSONResponse(
            data: ['valid' => ($errors === []), 'errors' => $errors],
            statusCode: Http::STATUS_OK
        );
    }//end validateSource()

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
