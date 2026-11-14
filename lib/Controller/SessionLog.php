<?php

/**
 * SessionLog
 *
 * The session and the logger of a widget API.
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
 * @spec openspec/specs/service-health-ping/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\PermissionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The session and the logger of a widget API.
 *
 * @spec openspec/specs/service-health-ping/spec.md
 */
class SessionLog {
	/**
	 * Constructor.
	 *
	 * @param IUserSession    $userSession Session accessor.
	 * @param LoggerInterface $logger PSR logger.
	 */
	public function __construct(
		public readonly IUserSession $userSession,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The signed-in user's UID, or NULL for anonymous.
	 *
	 * @return string|null
	 *
	 * @spec openspec/specs/service-health-ping/spec.md
	 */
	public function userId(): ?string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return null;
		}

		return $user->getUID();
	}//end userId()

	/**
	 * The 401 answer for an anonymous caller.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/specs/service-health-ping/spec.md
	 */
	public function unauthorized(): JSONResponse {
		return new JSONResponse(
			data: ['status' => 'error', 'error' => 'unauthorized'],
			statusCode: Http::STATUS_UNAUTHORIZED
		);
	}//end unauthorized()

	/**
	 * The signed-in user who may view the placement, else the 401 / 403 refusal.
	 *
	 * The guard runs BEFORE any resolution or outbound fetch is attempted.
	 *
	 * @param PermissionService $permissions The placement permission gate.
	 * @param int               $placementId The widget placement id.
	 *
	 * @return string|JSONResponse The user's UID, or the refusal.
	 *
	 * @spec openspec/specs/service-health-ping/spec.md
	 */
	public function placementViewer(PermissionService $permissions, int $placementId): string|JSONResponse {
		$userId = $this->userId();
		if ($userId === null) {
			return $this->unauthorized();
		}

		if ($permissions->canViewPlacement(userId: $userId, placementId: $placementId) === false) {
			return new JSONResponse(
				data: ['status' => 'error', 'error' => 'forbidden'],
				statusCode: Http::STATUS_FORBIDDEN
			);
		}

		return $userId;
	}//end placementViewer()

	/**
	 * Log an unexpected resolution failure and answer 500.
	 *
	 * @param string    $message   The log message.
	 * @param Throwable $exception The failure.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/specs/service-health-ping/spec.md
	 */
	public function resolutionFailure(string $message, Throwable $exception): JSONResponse {
		$this->logger->error(
			message: $message,
			context: ['app' => Application::APP_ID, 'exception' => $exception->getMessage()]
		);
		return new JSONResponse(data: ['status' => 'error', 'error' => 'unknown_error'], statusCode: Http::STATUS_INTERNAL_SERVER_ERROR);
	}//end resolutionFailure()

	/**
	 * A resolved reading as HTTP 200, or its `error` with the given status.
	 *
	 * @param array<string, mixed> $reading     The resolved reading.
	 * @param int                  $errorStatus The status for a reading that carries an `error`.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/specs/service-health-ping/spec.md
	 */
	public function readingResponse(array $reading, int $errorStatus): JSONResponse {
		if (isset($reading['error']) === true) {
			return new JSONResponse(
				data: ['status' => 'error', 'error' => $reading['error']],
				statusCode: $errorStatus
			);
		}

		return new JSONResponse(
			data: $reading,
			statusCode: Http::STATUS_OK
		);
	}//end readingResponse()

	/**
	 * The answer of a config validation endpoint.
	 *
	 * @param string[] $errors The validation errors.
	 *
	 * @return JSONResponse `{valid: bool, errors: string[]}`.
	 *
	 * @spec openspec/specs/service-health-ping/spec.md
	 */
	public function validationResponse(array $errors): JSONResponse {
		return new JSONResponse(
			data: ['valid' => ($errors === []), 'errors' => $errors],
			statusCode: Http::STATUS_OK
		);
	}//end validationResponse()
}//end class
