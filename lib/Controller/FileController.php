<?php

/**
 * FileController
 *
 * HTTP entry point for the link-button-widget capability's createFile
 * flow. Exposes:
 *
 * - `POST /api/files/create` (any logged-in user) — accepts JSON
 *   `{filename, dir, content}` and returns `{status, fileId, url}`
 *   where `url` deep-links into the Files app at `?openfile=<fileId>`.
 *
 * Every typed exception from {@see \OCA\LaunchPad\Service\FileService} is
 * mapped to the standardised `{status, error, message}` error envelope
 * — raw underlying exception messages are NEVER returned to the
 * caller (REQ-LBN-004).
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Exception\ResourceException;
use OCA\LaunchPad\Exception\StorageFailureException;
use OCA\LaunchPad\Service\FileService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Controller for the link-button-widget createFile flow.
 */
class FileController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest $request The HTTP request.
	 * @param FileService $fileService File-creation pipeline.
	 * @param IUserSession $userSession Session accessor.
	 * @param LoggerInterface $logger PSR logger.
	 */
	public function __construct(
		IRequest $request,
		private readonly FileService $fileService,
		private readonly IUserSession $userSession,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Handle `POST /api/files/create` (REQ-LBN-004).
	 *
	 * @param string|null $filename Leaf filename.
	 * @param string|null $dir Target subdirectory (default `/`).
	 * @param string|null $content Bytes to write (default empty).
	 *
	 * @return JSONResponse Either `{status, fileId, url}` on HTTP 200
	 *                      or `{status, error, message}` on failure.
	 *
	 * @NoCSRFRequired
	 *
	 * @spec openspec/specs/resource-uploads/spec.md
	 */
	#[NoAdminRequired]
	public function createFile(
		string|null $filename = null,
		string|null $dir = '/',
		string|null $content = '',
	): JSONResponse {
		try {
			$result = $this->fileService->createFile(
				userId: $this->resolveUserId(),
				filename: ($filename ?? ''),
				write: $this->writeOptions(dir: $dir, content: $content)
			);

			return new JSONResponse(
				data: $result,
				statusCode: Http::STATUS_OK
			);
		} catch (ForbiddenException $e) {
			return $this->unauthenticated();
		} catch (ResourceException $e) {
			return $this->resourceFailure(exception: $e);
		} catch (Throwable $e) {
			// Defence in depth — never leak raw messages on
			// truly unexpected paths.
			$this->logFailure(message: 'Unexpected file create failure', exception: $e);

			return $this->errorResponse(
				exception: new StorageFailureException(message: 'Failed to create file')
			);
		}//end try
	}//end createFile()

	/**
	 * The write options passed to the file service.
	 *
	 * @param string|null $dir     The target directory (default `/`).
	 * @param string|null $content The file content (default empty).
	 *
	 * @return array{dir: string, content: string, onExisting: string}
	 */
	private function writeOptions(string|null $dir, string|null $content): array {
		return [
			'dir'        => ($dir ?? '/'),
			'content'    => ($content ?? ''),
			'onExisting' => $this->onExisting(),
		];
	}//end writeOptions()

	/**
	 * Answer a typed failure, logging it when it is a storage failure
	 * (these usually indicate an operational problem).
	 *
	 * @param ResourceException $exception The typed failure.
	 *
	 * @return JSONResponse The error response.
	 */
	private function resourceFailure(ResourceException $exception): JSONResponse {
		if ($exception instanceof StorageFailureException) {
			$this->logFailure(message: 'File create storage failure', exception: $exception);
		}

		return $this->errorResponse(exception: $exception);
	}//end resourceFailure()

	/**
	 * The 401 envelope for a request without a session user.
	 *
	 * @return JSONResponse The error response.
	 */
	private function unauthenticated(): JSONResponse {
		return new JSONResponse(
			data: [
				'status' => 'error',
				'error' => 'forbidden',
				'message' => 'Authentication required',
			],
			statusCode: Http::STATUS_UNAUTHORIZED
		);
	}//end unauthenticated()

	/**
	 * Log one file-create failure with its exception message.
	 *
	 * @param string    $message   The log message.
	 * @param Throwable $exception The failure.
	 *
	 * @return void
	 */
	private function logFailure(string $message, Throwable $exception): void {
		$this->logger->error(
			message: $message,
			context: ['exception' => $exception->getMessage()]
		);
	}//end logFailure()

	/**
	 * How to treat an existing file of the same name, from the request.
	 *
	 * An explicit `overwrite: false` (JSON false, "false" or "0") refuses
	 * with 409 `file_exists` so the UI can warn first (#712). Absent or
	 * anything else keeps the documented replace (REQ-LBN-004 point 5).
	 *
	 * @return string One of the FileService::ON_EXISTING_* constants.
	 *
	 * @spec openspec/specs/link-button-widget/spec.md
	 */
	private function onExisting(): string {
		$overwrite = $this->request->getParam('overwrite', null);
		if ($overwrite === false || $overwrite === 'false' || $overwrite === '0' || $overwrite === 0) {
			return FileService::ON_EXISTING_REFUSE;
		}

		return FileService::ON_EXISTING_REPLACE;
	}//end onExisting()

	/**
	 * Resolve the logged-in user's ID.
	 *
	 * @return string The user's UID.
	 *
	 * @throws ForbiddenException When the request is not authenticated.
	 */
	private function resolveUserId(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new ForbiddenException();
		}

		return $user->getUID();
	}//end resolveUserId()

	/**
	 * Build the standardised error envelope from a typed exception.
	 *
	 * @param ResourceException $exception The typed exception.
	 *
	 * @return JSONResponse The error response.
	 */
	private function errorResponse(ResourceException $exception): JSONResponse {
		return new JSONResponse(
			data: [
				'status' => 'error',
				'error' => $exception->getErrorCode(),
				'message' => $exception->getDisplayMessage(),
			],
			statusCode: $exception->getHttpStatus()
		);
	}//end errorResponse()
}//end class
