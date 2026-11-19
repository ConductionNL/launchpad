<?php

/**
 * ResponseHelper
 *
 * Helper for building common JSON responses in controllers.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-05-24-retrofit-dashboards/tasks.md#task-3
 * @spec openspec/changes/archive/2026-05-24-retrofit-dashboards/tasks.md#task-4
 * @spec openspec/changes/responsehelper-instance-api/tasks.md#task-2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Exception\QuotaExceededException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use Psr\Log\LoggerInterface;

/**
 * Helper for building common JSON responses in controllers.
 *
 * Injected into controllers by the DI container, which also supplies the
 * logger that records every exception passed to error().
 */
class ResponseHelper {
	/**
	 * Constructor.
	 *
	 * @param LoggerInterface $logger Records the exceptions passed to error().
	 *
	 * @spec openspec/changes/responsehelper-instance-api/tasks.md#task-2
	 */
	public function __construct(
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Create an unauthorized response.
	 *
	 * @return JSONResponse The unauthorized response.
	 *
	 * @spec openspec/changes/archive/2026-05-24-retrofit-dashboards/tasks.md#task-3
	 * @spec openspec/changes/responsehelper-instance-api/tasks.md#task-2
	 */
	public function unauthorized(): JSONResponse {
		return new JSONResponse(
			data: ['error' => 'Not logged in'],
			statusCode: Http::STATUS_UNAUTHORIZED
		);
	}//end unauthorized()

	/**
	 * Create a forbidden response.
	 *
	 * @param string $message The error message.
	 *
	 * @return JSONResponse The forbidden response.
	 *
	 * @spec openspec/changes/archive/2026-05-24-retrofit-dashboards/tasks.md#task-3
	 * @spec openspec/changes/responsehelper-instance-api/tasks.md#task-2
	 */
	public function forbidden(
		string $message = 'Access denied',
	): JSONResponse {
		return new JSONResponse(
			data: ['error' => $message],
			statusCode: Http::STATUS_FORBIDDEN
		);
	}//end forbidden()

	/**
	 * Create an error response from an exception.
	 *
	 * Per ADR-005: never return the raw exception message to the client.
	 * The exception is logged at ERROR level through the injected logger;
	 * the client only ever sees the generic `$message` (defaulting to
	 * "Operation failed").
	 *
	 * @param \Exception $exception The exception.
	 * @param int $statusCode The HTTP status code.
	 * @param string $message Generic client-facing message.
	 *
	 * @return JSONResponse The error response.
	 *
	 * @spec openspec/changes/archive/2026-05-24-retrofit-dashboards/tasks.md#task-4
	 * @spec openspec/changes/responsehelper-instance-api/tasks.md#task-2
	 */
	public function error(
		\Exception $exception,
		int $statusCode = Http::STATUS_BAD_REQUEST,
		string $message = 'Operation failed',
	): JSONResponse {
		$this->logger->error(
			message: $exception->getMessage(),
			context: ['exception' => $exception]
		);

		return new JSONResponse(
			data: ['error' => $message],
			statusCode: $statusCode
		);
	}//end error()

	/**
	 * Build the HTTP 409 response for a quota-exceeded rejection
	 * (dashboard-quota-limits REQ-QUOTA-002 / REQ-QUOTA-003 / design D4).
	 *
	 * Returns the stable machine-readable body
	 * `{"error": "quota_exceeded", "quota": <kind>, "limit": N, "current": N}`
	 * so the frontend and API clients can render a precise message without
	 * string-parsing.
	 *
	 * @param QuotaExceededException $exception The quota exception carrying
	 *                                          the kind, limit, and current
	 *                                          count.
	 *
	 * @return JSONResponse The HTTP 409 Conflict response.
	 *
	 * @spec openspec/changes/dashboard-quota-limits/specs/dashboard-quota-limits/spec.md#req-quota-002-dashboard-count-enforcement
	 * @spec openspec/changes/responsehelper-instance-api/tasks.md#task-2
	 */
	public function quotaExceeded(
		QuotaExceededException $exception,
	): JSONResponse {
		return new JSONResponse(
			data: $exception->toResponseBody(),
			statusCode: Http::STATUS_CONFLICT
		);
	}//end quotaExceeded()

	/**
	 * Build the HTTP 400 invalid-argument envelope.
	 *
	 * @param string $message The validation message.
	 *
	 * @return JSONResponse The 400 response.
	 *
	 * @spec openspec/changes/archive/2026-05-24-retrofit-dashboards/tasks.md#task-3
	 * @spec openspec/changes/responsehelper-instance-api/tasks.md#task-2
	 */
	public function invalidArgument(string $message): JSONResponse {
		return new JSONResponse(
			data: [
				'status' => 'error',
				'error' => 'invalid_argument',
				'message' => $message,
			],
			statusCode: Http::STATUS_BAD_REQUEST
		);
	}//end invalidArgument()

	/**
	 * Create a success response.
	 *
	 * @param array $data The response data.
	 * @param int $statusCode The HTTP status code.
	 *
	 * @return JSONResponse The success response.
	 *
	 * @spec openspec/changes/archive/2026-05-24-retrofit-dashboards/tasks.md#task-3
	 * @spec openspec/changes/responsehelper-instance-api/tasks.md#task-2
	 */
	public function success(
		array $data,
		int $statusCode = Http::STATUS_OK,
	): JSONResponse {
		return new JSONResponse(
			data: $data,
			statusCode: $statusCode
		);
	}//end success()

	/**
	 * Serialize an array of entities.
	 *
	 * @param array $entities The entities to serialize.
	 *
	 * @return array The serialized entities.
	 *
	 * @spec openspec/changes/archive/2026-05-24-retrofit-dashboards/tasks.md#task-3
	 * @spec openspec/changes/responsehelper-instance-api/tasks.md#task-2
	 */
	public function serializeList(array $entities): array {
		$serialized = [];
		foreach ($entities as $entity) {
			$serialized[] = $entity->jsonSerialize();
		}

		return $serialized;
	}//end serializeList()
}//end class
