<?php

/**
 * HealthPingController
 *
 * HTTP entry point for the service-health-ping capability. Exposes:
 *
 * - `GET /api/health-ping/{placementId}` — the cached (or freshly
 *   resolved) health badge for one placement, view-time ACL guarded so a
 *   caller who cannot view the underlying dashboard never triggers a ping
 *   (REQ-HPING-003 "Caller authorization").
 * - `POST /api/health-ping/validate` — validates a candidate health-ping
 *   config (host allow-list) before the author saves the placement
 *   (REQ-HPING-001 "rejected at save time").
 *
 * The badge response is exactly `{state, checkedAt, latencyMs, stale}` —
 * never the health URL, request headers, or upstream response body
 * (REQ-HPING-003).
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
use OCA\LaunchPad\Service\HealthPingService;
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
 * Controller for the service-health-ping capability.
 *
 * @spec openspec/specs/service-health-ping/spec.md
 */
class HealthPingController extends Controller
{

    /**
     * Constructor.
     *
     * @param IRequest           $request            HTTP request.
     * @param HealthPingService  $healthPingService  Resolves + caches + validates health-ping badges.
     * @param PermissionService  $permissionService  Dashboard/placement permission gate.
     * @param IUserSession       $userSession        Session accessor.
     * @param LoggerInterface    $logger             PSR logger.
     */
    public function __construct(
        IRequest $request,
        private readonly HealthPingService $healthPingService,
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
     * `GET /api/health-ping/{placementId}`
     *
     * Returns the health badge for one placement. Returns 401 when
     * anonymous, 403 when the caller may not view the underlying
     * dashboard (REQ-HPING-003 "Caller authorization" — the ping is NEVER
     * performed in that case), 404 when the placement does not exist or
     * has no ping configured, else 200 with the badge (possibly
     * `stale: true`).
     *
     * @param integer $placementId The widget placement id.
     *
     * @return JSONResponse
     *
     * @spec openspec/specs/service-health-ping/spec.md
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

        // REQ-HPING-003 "Caller authorization" — the auth guard runs
        // BEFORE any resolution/ping is attempted.
        if ($this->permissionService->canViewPlacement(userId: $userId, placementId: $placementId) === false) {
            return new JSONResponse(
                data: ['status' => 'error', 'error' => 'forbidden'],
                statusCode: Http::STATUS_FORBIDDEN
            );
        }

        try {
            $badge = $this->healthPingService->resolveForPlacement(placementId: $placementId);
        } catch (Throwable $exception) {
            $this->logger->error(
                message: 'Unexpected health-ping resolution failure',
                context: ['app' => Application::APP_ID, 'exception' => $exception->getMessage()]
            );
            return new JSONResponse(
                data: ['status' => 'error', 'error' => 'unknown_error'],
                statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

        if (isset($badge['error']) === true) {
            return new JSONResponse(
                data: ['status' => 'error', 'error' => $badge['error']],
                statusCode: Http::STATUS_NOT_FOUND
            );
        }

        return new JSONResponse(
            data: $badge,
            statusCode: Http::STATUS_OK
        );
    }//end show()

    /**
     * `POST /api/health-ping/validate`
     *
     * Validates a candidate health-ping config before the author saves
     * the placement (REQ-HPING-001 "rejected at save time" — host
     * allow-list, fail-closed). Performs NO ping — only the allow-list
     * check that `resolveForPlacement()` would apply.
     *
     * @return JSONResponse `{valid: bool, errors: string[]}`.
     *
     * @spec openspec/specs/service-health-ping/spec.md
     */
    #[NoAdminRequired]
    public function validate(): JSONResponse
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

        $errors = $this->healthPingService->validateConfig(config: $config);

        return new JSONResponse(
            data: ['valid' => ($errors === []), 'errors' => $errors],
            statusCode: Http::STATUS_OK
        );
    }//end validate()

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
