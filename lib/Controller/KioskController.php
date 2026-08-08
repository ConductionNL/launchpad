<?php

/**
 * KioskController
 *
 * Controller for the 5 kiosk endpoints: 4 owner-or-admin playlist CRUD
 * endpoints and 1 public chrome-less render endpoint. The public render
 * route shares the `launchpad_share_access` brute-force bucket with
 * dashboard-public-share renders (design D3) so token scanning costs the
 * attacker the same budget across both surfaces.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-4
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use Exception;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Exception\PlaylistNotFoundException;
use OCA\LaunchPad\Service\KioskService;
use OCA\LaunchPad\Service\PublicShareContext;
use OCA\LaunchPad\Service\PublicShareService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Controller for kiosk-playlist CRUD and anonymous render endpoints.
 *
 * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-4
 */
class KioskController extends Controller
{
    /**
     * Constructor.
     *
     * @param IRequest           $request      The incoming request.
     * @param KioskService       $kioskService The kiosk-playlist service.
     * @param PublicShareContext $shareContext Request-scoped read-only bearer marker.
     * @param LoggerInterface    $logger       PSR-3 logger.
     * @param string|null        $userId       Authenticated user ID (null on public route).
     */
    public function __construct(
        IRequest $request,
        private readonly KioskService $kioskService,
        private readonly PublicShareContext $shareContext,
        private readonly LoggerInterface $logger,
        private readonly ?string $userId,
    ) {
        parent::__construct(
            appName: Application::APP_ID,
            request: $request
        );
    }//end __construct()

    /**
     * Create a kiosk playlist.
     *
     * Owner-or-admin per referenced dashboard (REQ-KIOSK-002).
     *
     * @param string|null $name           Playlist name.
     * @param array|null  $entries        Entries [{dashboardUuid, dwellSeconds}, ...].
     * @param int|null    $refreshSeconds Requested refresh interval.
     *
     * @return DataResponse HTTP 201 with playlist payload, 403, or 401.
     *
     * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-4
     */
    #[NoAdminRequired]
    public function create(
        ?string $name=null,
        ?array $entries=null,
        ?int $refreshSeconds=null
    ): DataResponse {
        if ($this->userId === null) {
            return new DataResponse(
                data: ['error' => 'Not logged in'],
                statusCode: Http::STATUS_UNAUTHORIZED
            );
        }

        try {
            $playlist = $this->kioskService->createPlaylist(
                name: (string) ($name ?? ''),
                entries: ($entries ?? []),
                refresh: (int) ($refreshSeconds ?? KioskService::REFRESH_DEFAULT),
                callerId: $this->userId
            );
            return new DataResponse(
                data: $playlist->jsonSerialize(),
                statusCode: Http::STATUS_CREATED
            );
        } catch (OCSForbiddenException) {
            return new DataResponse(
                data: ['error' => 'Not authorized'],
                statusCode: Http::STATUS_FORBIDDEN
            );
        } catch (Exception $e) {
            $this->logError(message: $e->getMessage());
            return new DataResponse(
                data: ['error' => 'Internal error'],
                statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }//end try
    }//end create()

    /**
     * List playlists visible to the caller.
     *
     * Own playlists for users, all playlists for admins (REQ-KIOSK-002).
     *
     * @return DataResponse HTTP 200 array of playlists or 401.
     *
     * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-4
     */
    #[NoAdminRequired]
    public function index(): DataResponse
    {
        if ($this->userId === null) {
            return new DataResponse(
                data: ['error' => 'Not logged in'],
                statusCode: Http::STATUS_UNAUTHORIZED
            );
        }

        $playlists = $this->kioskService->listPlaylists(callerId: $this->userId);
        return new DataResponse(
            data: array_map(
                callback: static fn ($playlist) => $playlist->jsonSerialize(),
                array: $playlists
            )
        );
    }//end index()

    /**
     * Update a kiosk playlist.
     *
     * Owner-or-admin, re-validates every referenced dashboard (REQ-KIOSK-002).
     *
     * @param int         $id             Playlist primary key.
     * @param string|null $name           Playlist name.
     * @param array|null  $entries        Entries [{dashboardUuid, dwellSeconds}, ...].
     * @param int|null    $refreshSeconds Requested refresh interval.
     *
     * @return DataResponse HTTP 200 with playlist payload, 403, 404, or 401.
     *
     * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-4
     */
    #[NoAdminRequired]
    public function update(
        int $id,
        ?string $name=null,
        ?array $entries=null,
        ?int $refreshSeconds=null
    ): DataResponse {
        if ($this->userId === null) {
            return new DataResponse(
                data: ['error' => 'Not logged in'],
                statusCode: Http::STATUS_UNAUTHORIZED
            );
        }

        try {
            $playlist = $this->kioskService->updatePlaylist(
                id: $id,
                name: (string) ($name ?? ''),
                entries: ($entries ?? []),
                refresh: (int) ($refreshSeconds ?? KioskService::REFRESH_DEFAULT),
                callerId: $this->userId
            );
            return new DataResponse(data: $playlist->jsonSerialize());
        } catch (PlaylistNotFoundException) {
            return new DataResponse(
                data: ['error' => 'Not found'],
                statusCode: Http::STATUS_NOT_FOUND
            );
        } catch (OCSForbiddenException) {
            return new DataResponse(
                data: ['error' => 'Not authorized'],
                statusCode: Http::STATUS_FORBIDDEN
            );
        } catch (Exception $e) {
            $this->logError(message: $e->getMessage());
            return new DataResponse(
                data: ['error' => 'Internal error'],
                statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }//end try
    }//end update()

    /**
     * Soft-revoke a kiosk playlist.
     *
     * Owner-or-admin only, idempotent (REQ-KIOSK-002).
     *
     * @param int $id Playlist primary key.
     *
     * @return DataResponse HTTP 204, 403, 404, or 401.
     *
     * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-4
     */
    #[NoAdminRequired]
    public function destroy(int $id): DataResponse
    {
        if ($this->userId === null) {
            return new DataResponse(
                data: ['error' => 'Not logged in'],
                statusCode: Http::STATUS_UNAUTHORIZED
            );
        }

        try {
            $this->kioskService->revokePlaylist(id: $id, callerId: $this->userId);
            return new DataResponse(data: [], statusCode: Http::STATUS_NO_CONTENT);
        } catch (PlaylistNotFoundException) {
            return new DataResponse(
                data: ['error' => 'Not found'],
                statusCode: Http::STATUS_NOT_FOUND
            );
        } catch (OCSForbiddenException) {
            return new DataResponse(
                data: ['error' => 'Not authorized'],
                statusCode: Http::STATUS_FORBIDDEN
            );
        } catch (Exception $e) {
            $this->logError(message: $e->getMessage());
            return new DataResponse(
                data: ['error' => 'Internal error'],
                statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }//end try
    }//end destroy()

    /**
     * Anonymously render a kiosk playlist via its token.
     *
     * Returns the playlist descriptor and the read-only render payload for
     * every entry whose dashboard still exists. Unknown or revoked tokens
     * return HTTP 404 with an identical shape (no existence leak). Shares the
     * `launchpad_share_access` brute-force bucket with public-share renders.
     *
     * @param string $token The playlist token from the URL.
     *
     * @return DataResponse HTTP 200 render payload, 404 if invalid, 429 when throttled.
     *
     * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-4
     */
    #[PublicPage]
    #[NoCSRFRequired]
    #[AnonRateLimit(limit: 60, period: 60)]
    #[BruteForceProtection(action: PublicShareService::ACTION_SHARE_ACCESS)]
    public function render(string $token): DataResponse
    {
        try {
            $result = $this->kioskService->renderPlaylist(token: $token);

            // Mark the request as a read-only bearer so any mutation service
            // touched during render-payload hydration trips
            // ShareReadOnlyException, mirroring public-share REQ-PSHR-006.
            $this->shareContext->markBearer(token: $token);

            return new DataResponse(data: $result);
        } catch (PlaylistNotFoundException) {
            $response = new DataResponse(
                data: ['error' => 'Not found'],
                statusCode: Http::STATUS_NOT_FOUND
            );
            // Register a brute-force attempt on the shared bucket so token
            // scanning the kiosk route counts toward the same throttle.
            $response->throttle(['action' => PublicShareService::ACTION_SHARE_ACCESS]);
            return $response;
        } catch (Exception $e) {
            $this->logError(message: $e->getMessage());
            return new DataResponse(
                data: ['error' => 'Internal error'],
                statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }//end try
    }//end render()

    /**
     * Log a non-sensitive error message.
     *
     * @param string $message The error message.
     *
     * @return void
     */
    private function logError(string $message): void
    {
        $this->logger->warning(
            message: 'KioskController error: '.$message,
            context: ['app' => Application::APP_ID]
        );
    }//end logError()
}//end class
