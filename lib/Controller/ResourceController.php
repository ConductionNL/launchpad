<?php

/**
 * ResourceController
 *
 * HTTP entry point for the resource-uploads capability. Exposes:
 *
 * - `POST /api/resources` (admin-only) — accepts a raw JSON body of
 *   the shape `{base64: 'data:image/<type>;base64,...'}` and returns
 *   a standardised `{status, url, name, size}` envelope.
 *
 * Read/listing routes (`GET /resource/{filename}`, `GET /api/resources`)
 * are served by {@see ResourceServeController}.
 *
 * All errors are mapped to a `{status: 'error', error: <stable_code>,
 * message: <display>}` envelope — raw exception messages are NEVER
 * returned to the client.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Exception\FileMissingException;
use OCA\LaunchPad\Exception\FileTooLargeException;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Exception\ResourceException;
use OCA\LaunchPad\Exception\StorageFailureException;
use OCA\LaunchPad\Service\ResourceService;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Resource upload + serving controller.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 *      Constructor wiring only: IRequest, ResourceService,
 *      ResourceUploadRequestParser, IUserSession, IGroupManager and a logger.
 *      Upload validation is already delegated to the parser and the service;
 *      what remains is the admin-guard and response surface
 *      (AuthorizedAdminSetting, JSONResponse, Http, Throwable).
 * @spec                                           openspec/specs/resource-uploads/spec.md
 */
class ResourceController extends Controller
{
    /**
     * Constructor.
     *
     * @param IRequest                    $request         The HTTP request.
     * @param ResourceService             $resourceService The upload pipeline.
     * @param ResourceUploadRequestParser $parser          Parses request body.
     * @param IUserSession                $userSession     Session accessor.
     * @param IGroupManager               $groupManager    Admin checker.
     * @param LoggerInterface             $logger          PSR logger.
     */
    public function __construct(
        IRequest $request,
        private readonly ResourceService $resourceService,
        private readonly ResourceUploadRequestParser $parser,
        private readonly IUserSession $userSession,
        private readonly IGroupManager $groupManager,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct(
            appName: 'launchpad',
            request: $request
        );
    }//end __construct()

    /**
     * Handle `POST /api/resources`.
     *
     * Reads the raw JSON body from `php://input`, asserts an admin
     * caller, delegates to ResourceService, and maps the response
     * (success or any typed exception) to the standardised envelope.
     *
     * @return JSONResponse Either the success envelope with
     *                      `{status, url, name, size}` or the error
     *                      envelope with `{status, error, message}`.
     *
     * @NoCSRFRequired
         *
     * @spec openspec/specs/resource-uploads/spec.md
 */
    #[AuthorizedAdminSetting(LaunchPadAdmin::class)]
    public function upload(): JSONResponse
    {
        try {
            $this->assertAdmin();
            $base64 = $this->parser->extractBase64(
                request: $this->request,
                rawBody: $this->readRequestBody()
            );

            $result = $this->resourceService->upload(
                base64DataUrl: $base64
            );

            return new JSONResponse(
                data: [
                    'status' => 'success',
                    'url'    => $result['url'],
                    'name'   => $result['name'],
                    'size'   => $result['size'],
                ],
                statusCode: Http::STATUS_OK
            );
        } catch (ResourceException $e) {
            // Log storage failures — these usually indicate an
            // operational problem (disk, permissions, etc.).
            if ($e instanceof StorageFailureException) {
                $this->logger->error(
                    message: 'Resource upload storage failure',
                    context: ['exception' => $e->getMessage()]
                );
            }

            return $this->errorResponse(exception: $e);
        } catch (Throwable $e) {
            // Defence in depth — never leak raw messages on truly
            // unexpected paths.
            $this->logger->error(
                message: 'Unexpected resource upload failure',
                context: ['exception' => $e->getMessage()]
            );

            $fallback = new StorageFailureException(
                message: 'Failed to store resource'
            );

            return $this->errorResponse(exception: $fallback);
        }//end try
    }//end upload()

    /**
     * Handle `POST /api/resources/upload` — raw multipart upload.
     *
     * Accepts `multipart/form-data` with a single `file` entry and stores
     * the raw bytes directly — no base64, so large images and GIFs never
     * become a huge in-memory string in the browser. Admin-only, enforced
     * both by the `AuthorizedAdminSetting` attribute and a defensive
     * `assertAdmin()`. The response mirrors the base64 endpoint's
     * `{status, url, name, size}` envelope.
     *
     * CSRF stays enforced (no `@NoCSRFRequired`): admin-only is not
     * CSRF-safe — a logged-in admin lured to a hostile page could otherwise
     * be made to POST a resource. The frontend uses `@nextcloud/axios`,
     * which auto-sends the `requesttoken` header, so this is zero-cost.
     *
     * @return JSONResponse The success or error envelope.
     *
     * @spec openspec/specs/resource-uploads/spec.md
     */
    #[AuthorizedAdminSetting(LaunchPadAdmin::class)]
    public function uploadMultipart(): JSONResponse
    {
        try {
            $this->assertAdmin();

            $file = $this->readUploadedFile();
            if ($file === null) {
                throw new FileMissingException();
            }

            $result = $this->resourceService->uploadRaw(
                bytes: $file['bytes'],
                declaredType: $this->declaredTypeFromName(name: $file['name'])
            );

            return new JSONResponse(
                data: [
                    'status' => 'success',
                    'url'    => $result['url'],
                    'name'   => $result['name'],
                    'size'   => $result['size'],
                ],
                statusCode: Http::STATUS_OK
            );
        } catch (ResourceException $e) {
            if ($e instanceof StorageFailureException) {
                $this->logger->error(
                    message: 'Resource multipart upload storage failure',
                    context: ['exception' => $e->getMessage()]
                );
            }

            return $this->errorResponse(exception: $e);
        } catch (Throwable $e) {
            $this->logger->error(
                message: 'Unexpected resource multipart upload failure',
                context: ['exception' => $e->getMessage()]
            );

            return $this->errorResponse(
                exception: new StorageFailureException(
                    message: 'Failed to store resource'
                )
            );
        }//end try
    }//end uploadMultipart()

    /**
     * Read the single uploaded file from the `file` multipart field.
     *
     * Extracted so PHPUnit can override the `$_FILES` source. Returns the
     * original filename and the raw bytes, or `null` when no usable file
     * was uploaded (missing entry or a non-zero PHP upload error).
     *
     * @return array{name: string, bytes: string}|null The uploaded file, or null.
     *
     * @throws FileTooLargeException When the reported upload size exceeds the cap.
     *
     * @SuppressWarnings(PHPMD.Superglobals) — required for multipart uploads.
     *
     * @spec openspec/specs/resource-uploads/spec.md
     */
    protected function readUploadedFile(): ?array
    {
        // @phpstan-ignore-next-line — superglobal access is mixed.
        $raw = ($_FILES['file'] ?? null);
        if (is_array(value: $raw) === false) {
            return null;
        }

        // A multi-file `file[]` submission makes each entry an array
        // (`error`/`tmp_name` become arrays). This endpoint takes a single
        // file, so reject that shape up front — casting an array to int/string
        // below would otherwise emit an "Array to string conversion" warning.
        if (is_array(value: ($raw['error'] ?? null)) === true) {
            return null;
        }

        $error = (int) ($raw['error'] ?? UPLOAD_ERR_NO_FILE);
        $tmp   = (string) ($raw['tmp_name'] ?? '');
        if ($error !== UPLOAD_ERR_OK || $tmp === '') {
            return null;
        }

        // Cheap pre-read cap using PHP's reported upload size, so a large file
        // (allowed by a generous upload_max_filesize) is rejected before it is
        // pulled into memory by file_get_contents(). The authoritative check on
        // the actual bytes still runs in ResourceService::storeImageBytes().
        if ((int) ($raw['size'] ?? 0) > ResourceService::MAX_BYTES) {
            throw new FileTooLargeException();
        }

        // Assert the temp file came through a real PHP upload to prevent
        // local-file-inclusion via a crafted tmp_name (mirrors the defence in
        // FilesWidgetService). Without this, file_get_contents() below would
        // read any path the superglobal points at.
        if (is_uploaded_file(filename: $tmp) === false) {
            return null;
        }

        $bytes = (string) file_get_contents(filename: $tmp);

        return [
            'name'  => (string) ($raw['name'] ?? ''),
            'bytes' => $bytes,
        ];
    }//end readUploadedFile()

    /**
     * Derive a declared image type from an uploaded filename's extension.
     *
     * The bytes are cross-checked against this type downstream (raster MIME
     * validation / SVG sanitisation), so a mislabelled extension is caught
     * there rather than trusted here. An empty / extension-less name yields
     * an empty string, which the service rejects as an invalid format.
     *
     * @param string $name The original uploaded filename.
     *
     * @return string The lowercased extension (without the dot), or ''.
     *
     * @spec openspec/specs/resource-uploads/spec.md
     */
    private function declaredTypeFromName(string $name): string
    {
        return strtolower(string: (string) pathinfo(path: $name, flags: PATHINFO_EXTENSION));
    }//end declaredTypeFromName()

    /**
     * Throw if the current session user is not an admin.
     *
     * Delegates to `IGroupManager::isAdmin` — both an unauthenticated
     * request and an authenticated non-admin produce HTTP 403.
     *
     * @return void
     *
     * @throws ForbiddenException When the caller is not an admin.
     */
    private function assertAdmin(): void
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            throw new ForbiddenException();
        }

        if ($this->groupManager->isAdmin(userId: $user->getUID()) === false) {
            throw new ForbiddenException();
        }
    }//end assertAdmin()

    /**
     * Read the raw request body.
     *
     * Extracted so tests can override the source — `php://input` is
     * not realistically pluggable in PHPUnit otherwise. Production
     * code reads from the standard PHP input stream.
     *
     * @return string The raw request body.
     *
     * @spec openspec/specs/resource-uploads/spec.md
     */
    protected function readRequestBody(): string
    {
        return (string) file_get_contents(filename: 'php://input');
    }//end readRequestBody()

    /**
     * Build the standardised error envelope from a typed exception.
     *
     * @param ResourceException $exception The typed exception.
     *
     * @return JSONResponse The error response.
     */
    private function errorResponse(ResourceException $exception): JSONResponse
    {
        return new JSONResponse(
            data: [
                'status'  => 'error',
                'error'   => $exception->getErrorCode(),
                'message' => $exception->getDisplayMessage(),
            ],
            statusCode: $exception->getHttpStatus()
        );
    }//end errorResponse()
}//end class
