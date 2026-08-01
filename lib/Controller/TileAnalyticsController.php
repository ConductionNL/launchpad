<?php

/**
 * TileAnalyticsController
 *
 * HTTP entry points for the tile usage-analytics capability
 * (REQ-TANLT-001..005) — a strict downward extension of the
 * dashboard view-analytics capability at the tile/widget-placement
 * grain. Exposes:
 *   - `POST /api/tile-click/{placementId}` — record a click. Any
 *     authenticated user; `#[NoAdminRequired]`. Always returns 204.
 *   - `GET /api/tile-analytics/config` — whether tracking is
 *     currently active for the calling user, so the frontend hook can
 *     suppress the record call when analytics is globally disabled or
 *     the user has opted out, without duplicating the gate logic
 *     client-side.
 *   - `GET /api/admin/analytics/tiles/top` — top-N tiles.
 *   - `GET /api/admin/analytics/tiles/by-dashboard/{uuid}` —
 *     per-dashboard tile breakdown.
 *   - `GET /api/admin/analytics/tiles/export` — CSV export download.
 *
 * The three admin report endpoints apply a runtime admin guard via
 * ADR-023 action authorization ({@see ActionAuthService}), mirroring
 * `AnalyticsController` exactly.
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
use OCA\LaunchPad\Service\TileAnalyticsService;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Controller for tile usage-analytics endpoints.
 *
 * @spec openspec/specs/dashboard-view-analytics/spec.md
 *
 * @SuppressWarnings(PHPMD.StaticAccess) {@see ResponseHelper} is an all-static,
 *  stateless response factory shared by every controller in this app; calling
 *  it statically is its intended usage and injecting it would add a
 *  collaborator with no state to every controller.
 */
class TileAnalyticsController extends Controller
{
    /**
     * Constructor.
     *
     * @param IRequest             $request              The HTTP request.
     * @param TileAnalyticsService $tileAnalyticsService The tile analytics
     *                                                   service.
     * @param ActionAuthService    $actionAuth           ADR-023 action
     *                                                   authorization.
     * @param IUserSession         $userSession          Current user session.
     */
    public function __construct(
        IRequest $request,
        private readonly TileAnalyticsService $tileAnalyticsService,
        private readonly ActionAuthService $actionAuth,
        private readonly IUserSession $userSession,
    ) {
        parent::__construct(
            appName: Application::APP_ID,
            request: $request
        );
    }//end __construct()

    /**
     * Handle `POST /api/tile-click/{placementId}` (REQ-TANLT-002).
     *
     * Authenticated users only — empty body. Returns HTTP 204 after
     * the daily counter has been incremented (or short-circuited to a
     * silent no-op per REQ-TANLT-003). Returns 404 when the placement
     * does not exist.
     *
     * @param string $placementId The widget-placement (tile) ID from
     *                            the URL (numeric — enforced by the
     *                            route `requirements`).
     *
     * @return JSONResponse An empty 204 response, 401 when
     *                      unauthenticated, 404 when the placement
     *                      does not exist.
     *
     * @spec openspec/specs/dashboard-view-analytics/spec.md
     */
    #[NoAdminRequired]
    public function recordClick(string $placementId): JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $this->actionAuth->requireAction($user, 'tile-analytics.record-click');
        } catch (OCSForbiddenException) {
            return new JSONResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
        }

        try {
            $this->tileAnalyticsService->recordClick(
                placementId: (int) $placementId,
                userId: $user->getUID()
            );
        } catch (DoesNotExistException) {
            return new JSONResponse(
                data: [
                    'status' => 'error',
                    'error'  => 'not_found',
                ],
                statusCode: Http::STATUS_NOT_FOUND
            );
        }

        return new JSONResponse(
            data: [],
            statusCode: Http::STATUS_NO_CONTENT
        );
    }//end recordClick()

    /**
     * Handle `GET /api/tile-analytics/config`.
     *
     * Lets the frontend hook know whether tracking is currently
     * active for the calling user, so it can suppress the fire-and-
     * forget record call when analytics is globally disabled or the
     * user has opted out — without re-implementing either gate
     * client-side (REQ-TANLT-003).
     *
     * @return JSONResponse `{"enabled": bool}`; 401 when
     *                      unauthenticated.
     *
     * @spec openspec/specs/dashboard-view-analytics/spec.md
     */
    #[NoAdminRequired]
    public function config(): JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        return ResponseHelper::success(
            data: [
                'enabled' => $this->tileAnalyticsService->isTrackingActiveFor(userId: $user->getUID()),
            ]
        );
    }//end config()

    /**
     * Handle `GET /api/admin/analytics/tiles/top` (REQ-TANLT-004).
     *
     * @param string $period The period string (`7d`, `30d`, `90d`).
     * @param int    $limit  Maximum rows.
     *
     * @return JSONResponse The response.
     *
     * @spec openspec/specs/dashboard-view-analytics/spec.md
     */
    #[AuthorizedAdminSetting(LaunchPadAdmin::class)]
    public function topTiles(
        string $period='30d',
        int $limit=10
    ): JSONResponse {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $this->actionAuth->requireAction($user, 'tile-analytics.top-tiles');
        } catch (OCSForbiddenException) {
            return new JSONResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
        }

        try {
            $rows = $this->tileAnalyticsService->getTopTiles(
                period: $period,
                limit: $limit
            );
        } catch (InvalidArgumentException $e) {
            return new JSONResponse(
                data: [
                    'status'  => 'error',
                    'error'   => 'invalid_period',
                    'message' => $e->getMessage(),
                ],
                statusCode: Http::STATUS_BAD_REQUEST
            );
        }

        return ResponseHelper::success(data: $rows);
    }//end topTiles()

    /**
     * Handle `GET /api/admin/analytics/tiles/by-dashboard/{uuid}`
     * (REQ-TANLT-004).
     *
     * @param string $uuid   The dashboard UUID from the URL.
     * @param string $period The period string.
     *
     * @return JSONResponse The response.
     *
     * @spec openspec/specs/dashboard-view-analytics/spec.md
     */
    #[AuthorizedAdminSetting(LaunchPadAdmin::class)]
    public function dashboardBreakdown(
        string $uuid,
        string $period='30d'
    ): JSONResponse {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $this->actionAuth->requireAction($user, 'tile-analytics.dashboard-breakdown');
        } catch (OCSForbiddenException) {
            return new JSONResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
        }

        try {
            $rows = $this->tileAnalyticsService->getDashboardBreakdown(
                dashboardUuid: $uuid,
                period: $period
            );
        } catch (InvalidArgumentException $e) {
            return new JSONResponse(
                data: [
                    'status'  => 'error',
                    'error'   => 'invalid_period',
                    'message' => $e->getMessage(),
                ],
                statusCode: Http::STATUS_BAD_REQUEST
            );
        }

        return ResponseHelper::success(data: $rows);
    }//end dashboardBreakdown()

    /**
     * Handle `GET /api/admin/analytics/tiles/export` (REQ-TANLT-005).
     *
     * Returns a `text/csv` attachment with the filename
     * `tile-analytics-YYYY-MM-DD.csv` (today's UTC date).
     *
     * @param string $period The period string.
     *
     * @return Response The CSV download response or a JSON error
     *                  envelope.
     *
     * @spec openspec/specs/dashboard-view-analytics/spec.md
     */
    #[AuthorizedAdminSetting(LaunchPadAdmin::class)]
    public function exportCsv(string $period='30d'): Response
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $this->actionAuth->requireAction($user, 'tile-analytics.export-csv');
        } catch (OCSForbiddenException) {
            return new JSONResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
        }

        try {
            $csv = $this->tileAnalyticsService->generateCsvExport(period: $period);
        } catch (InvalidArgumentException $e) {
            return new JSONResponse(
                data: [
                    'status'  => 'error',
                    'error'   => 'invalid_period',
                    'message' => $e->getMessage(),
                ],
                statusCode: Http::STATUS_BAD_REQUEST
            );
        }

        return new DataDownloadResponse(
            data: $csv,
            filename: $this->tileAnalyticsService->csvExportFilename(),
            contentType: 'text/csv'
        );
    }//end exportCsv()
}//end class
