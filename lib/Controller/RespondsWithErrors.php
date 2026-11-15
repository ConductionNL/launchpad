<?php

/**
 * RespondsWithErrors
 *
 * Builds the JSON error envelopes that controllers answer with.
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
 * @spec openspec/changes/archive/2026-05-24-retrofit-dashboards/tasks.md#task-3
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;

/**
 * The error envelopes shared by the controllers.
 */
trait RespondsWithErrors {
	/**
	 * Create an error response carrying one message under `error`.
	 *
	 * @param string $message    The message shown to the client.
	 * @param int    $statusCode The HTTP status code.
	 *
	 * @return JSONResponse The response `{"error": message}`.
	 *
	 * @spec openspec/changes/archive/2026-05-24-retrofit-dashboards/tasks.md#task-3
	 */
	protected function failure(string $message, int $statusCode): JSONResponse {
		return new JSONResponse(data: ['error' => $message], statusCode: $statusCode);
	}//end failure()

	/**
	 * Create an error response carrying a machine-readable error code.
	 *
	 * @param string $code       The error code, such as `not_found`.
	 * @param int    $statusCode The HTTP status code.
	 *
	 * @return JSONResponse The response `{"status": "error", "error": code}`.
	 *
	 * @spec openspec/changes/archive/2026-05-24-retrofit-dashboards/tasks.md#task-3
	 */
	protected function errorCode(string $code, int $statusCode): JSONResponse {
		return new JSONResponse(data: ['status' => 'error', 'error' => $code], statusCode: $statusCode);
	}//end errorCode()

	/**
	 * Create an HTTP 400 response carrying an error code and a message.
	 *
	 * @param string $code    The error code, such as `invalid_period`.
	 * @param string $message The message shown to the client.
	 *
	 * @return JSONResponse The response `{"status": "error", "error": code, "message": message}`.
	 *
	 * @spec openspec/changes/archive/2026-05-24-retrofit-dashboards/tasks.md#task-3
	 */
	protected function badRequestWithCode(string $code, string $message): JSONResponse {
		return new JSONResponse(
			data: ['status' => 'error', 'error' => $code, 'message' => $message],
			statusCode: Http::STATUS_BAD_REQUEST
		);
	}//end badRequestWithCode()
}//end trait
