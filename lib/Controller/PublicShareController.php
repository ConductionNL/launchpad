<?php

/**
 * PublicShareController
 *
 * Controller for the 5 public-share endpoints (3 owner-only, 2 public).
 * Brute-force protection on both public endpoints per design decisions D1/D2.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-6
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use Exception;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Exception\ShareNotFoundException;
use OCA\LaunchPad\Exception\SharePasswordRequiredException;
use OCA\LaunchPad\Service\PublicShareContext;
use OCA\LaunchPad\Service\PublicShareService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\Security\Bruteforce\MaxDelayReached;
use Psr\Log\LoggerInterface;

/**
 * Controller for public-share CRUD and anonymous render endpoints.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 *
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-6
 */
class PublicShareController extends Controller
{
    /**
     * Constructor.
     *
     * @param IRequest           $request      The incoming request.
     * @param PublicShareService $shareService The public-share service.
     * @param PublicShareContext $shareContext Request-scoped bearer marker (Task 7).
     * @param LoggerInterface    $logger       PSR-3 logger.
     * @param string|null        $userId       Authenticated user ID (null for public endpoints).
     */
    public function __construct(
        IRequest $request,
        private readonly PublicShareService $shareService,
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
     * Create a new public share for a dashboard.
     *
     * Owner-or-admin only (REQ-PSHR-001).
     *
     * @param string      $uuid      Dashboard UUID.
     * @param string|null $password  Optional plaintext password.
     * @param string|null $expiresAt Optional ISO 8601 expiry.
     *
     * @return DataResponse HTTP 201 with share payload or 403/404 on failure.
     *
     * @spec openspec/changes/dashboard-public-share/tasks.md#task-6
     */
    #[NoAdminRequired]
    public function create(
        string $uuid,
        ?string $password=null,
        ?string $expiresAt=null
    ): DataResponse {
        if ($this->userId === null) {
            return new DataResponse(
                data: ['error' => 'Not logged in'],
                statusCode: Http::STATUS_UNAUTHORIZED
            );
        }

        try {
            $share = $this->shareService->createPublicShare(
                dashboardUuid: $uuid,
                callerId: $this->userId,
                password: $password,
                expiresAt: $expiresAt
            );
            return new DataResponse(
                data: $share->jsonSerialize(),
                statusCode: Http::STATUS_CREATED
            );
        } catch (DoesNotExistException) {
            return new DataResponse(
                data: ['error' => 'Dashboard not found'],
                statusCode: Http::STATUS_NOT_FOUND
            );
        } catch (OCSForbiddenException) {
            return new DataResponse(
                data: ['error' => 'Not authorized'],
                statusCode: Http::STATUS_FORBIDDEN
            );
        } catch (Exception $e) {
            return new DataResponse(
                data: ['error' => $e->getMessage()],
                statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }//end try
    }//end create()

    /**
     * List active public shares for a dashboard.
     *
     * Owner-or-admin only (REQ-PSHR-002).
     *
     * @param string $uuid Dashboard UUID.
     *
     * @return DataResponse HTTP 200 array of active shares or 403/404.
     *
     * @spec openspec/changes/dashboard-public-share/tasks.md#task-6
     */
    #[NoAdminRequired]
    public function index(string $uuid): DataResponse
    {
        if ($this->userId === null) {
            return new DataResponse(
                data: ['error' => 'Not logged in'],
                statusCode: Http::STATUS_UNAUTHORIZED
            );
        }

        try {
            $shares = $this->shareService->listActiveShares(
                dashboardUuid: $uuid,
                callerId: $this->userId
            );
            return new DataResponse(
                data: array_map(
                    callback: static fn ($share) => $share->jsonSerialize(),
                    array: $shares
                )
            );
        } catch (DoesNotExistException) {
            return new DataResponse(
                data: ['error' => 'Dashboard not found'],
                statusCode: Http::STATUS_NOT_FOUND
            );
        } catch (OCSForbiddenException) {
            return new DataResponse(
                data: ['error' => 'Not authorized'],
                statusCode: Http::STATUS_FORBIDDEN
            );
        } catch (Exception $e) {
            return new DataResponse(
                data: ['error' => $e->getMessage()],
                statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }//end try
    }//end index()

    /**
     * Soft-revoke a public share.
     *
     * Owner-or-admin only (REQ-PSHR-003). Idempotent — revoking an already-revoked
     * share returns 204.
     *
     * @param string $uuid Dashboard UUID.
     * @param int    $id   Share ID.
     *
     * @return DataResponse HTTP 204 or 403/404.
     *
     * @spec openspec/changes/dashboard-public-share/tasks.md#task-6
     */
    #[NoAdminRequired]
    public function destroy(string $uuid, int $id): DataResponse
    {
        if ($this->userId === null) {
            return new DataResponse(
                data: ['error' => 'Not logged in'],
                statusCode: Http::STATUS_UNAUTHORIZED
            );
        }

        try {
            $this->shareService->revokeShare(
                dashboardUuid: $uuid,
                shareId: $id,
                callerId: $this->userId
            );
            return new DataResponse(data: [], statusCode: Http::STATUS_NO_CONTENT);
        } catch (DoesNotExistException | ShareNotFoundException) {
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
            return new DataResponse(
                data: ['error' => $e->getMessage()],
                statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }//end try
    }//end destroy()

    /**
     * Anonymously render a dashboard via public share token.
     *
     * Returns dashboard data (read-only). Returns 401 if password is required.
     * Returns 404 if the token is invalid, revoked, or expired.
     *
     * Rate-limited at 60 requests / 60 seconds per IP (REQ-PSHR-009 D1).
     *
     * @param string      $token    The share token from the URL.
     * @param string|null $password Optional password via query param or X-Share-Password header.
     *
     * @return DataResponse HTTP 200 dashboard payload, 401 if password required, 404 if invalid.
     *
     * @spec openspec/changes/dashboard-public-share/tasks.md#task-6
     *
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    #[PublicPage]
    #[NoCSRFRequired]
    #[AnonRateLimit(limit: 60, period: 60)]
    #[BruteForceProtection(action: PublicShareService::ACTION_SHARE_ACCESS)]
    public function show(string $token, ?string $password=null): DataResponse
    {
        $ip = $this->request->getRemoteAddress();

        // Accept password from X-Share-Password header if not in query.
        if ($password === null || $password === '') {
            $headerPassword = $this->request->getHeader('X-Share-Password');
            if ($headerPassword !== '') {
                $password = $headerPassword;
            }
        }

        try {
            $result = $this->shareService->renderShareContent(
                token: $token,
                ip: $ip,
                password: $password
            );

            // Task-7 — flag the request as a public-share bearer so any
            // mutation service called during the render path (e.g. widget
            // content hydration) trips ShareReadOnlyException.
            $this->shareContext->markBearer(token: $token);

            $shareData = $result['share']->jsonSerialize();
            unset($shareData['createdBy']);
            return new DataResponse(
                data: [
                    'share'     => $shareData,
                    'dashboard' => $result['dashboard']->jsonSerialize(),
                ]
            );
        } catch (SharePasswordRequiredException) {
            return new DataResponse(
                data: ['passwordRequired' => true],
                statusCode: Http::STATUS_UNAUTHORIZED
            );
        } catch (ShareNotFoundException) {
            return new DataResponse(
                data: ['error' => 'Not found'],
                statusCode: Http::STATUS_NOT_FOUND
            );
        } catch (Exception $e) {
            $this->logError(message: $e->getMessage());
            return new DataResponse(
                data: ['error' => 'Internal error'],
                statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }//end try
    }//end show()

    /**
     * Verify password for a password-protected share.
     *
     * Returns {access: true} on success, {access: false} on wrong password.
     * Rate-limited at 10 attempts / 60 seconds per IP (REQ-PSHR-009 D2).
     *
     * @param string      $token    The share token.
     * @param string|null $password The supplied password.
     *
     * @return DataResponse HTTP 200 with {access: bool}, 429 when throttled, 404 when share invalid.
     *
     * @spec openspec/changes/dashboard-public-share/tasks.md#task-6
     *
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    #[PublicPage]
    #[NoCSRFRequired]
    #[AnonRateLimit(limit: 10, period: 60)]
    #[BruteForceProtection(action: PublicShareService::ACTION_SHARE_PASSWORD)]
    public function unlock(string $token, ?string $password=null): DataResponse
    {
        $ip = $this->request->getRemoteAddress();

        if ($password === null || $password === '') {
            return new DataResponse(
                data: ['access' => false],
                statusCode: Http::STATUS_UNAUTHORIZED
            );
        }

        try {
            $isSuccess = $this->shareService->unlockShare(
                token: $token,
                password: $password,
                ip: $ip
            );
            $status    = Http::STATUS_UNAUTHORIZED;
            if ($isSuccess === true) {
                $status = Http::STATUS_OK;
            }

            return new DataResponse(data: ['access' => $isSuccess], statusCode: $status);
        } catch (MaxDelayReached) {
            return new DataResponse(
                data: ['error' => 'Too many attempts'],
                statusCode: Http::STATUS_TOO_MANY_REQUESTS
            );
        } catch (ShareNotFoundException) {
            return new DataResponse(
                data: ['error' => 'Not found'],
                statusCode: Http::STATUS_NOT_FOUND
            );
        } catch (Exception $e) {
            $this->logError(message: $e->getMessage());
            return new DataResponse(
                data: ['error' => 'Internal error'],
                statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }//end try
    }//end unlock()

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
            message: 'PublicShareController error: '.$message,
            context: ['app' => Application::APP_ID]
        );
    }//end logError()
}//end class
