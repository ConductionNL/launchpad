<?php

/**
 * PeopleWidgetController
 *
 * HTTP entry point for the People widget user-listing API. Thin adapter
 * that parses query parameters, delegates to {@see PeopleWidgetService},
 * and converts thrown {@see InvalidArgumentException}s to HTTP 400.
 *
 * Route: `GET /api/people` — declared in {@see appinfo/routes.php}.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Exception\InvalidPeopleRequestException;
use OCA\LaunchPad\Service\PeopleQuery;
use OCA\LaunchPad\Service\PeopleWidgetService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;

/**
 * REST controller exposing the paginated People widget directory.
 *
 * @spec openspec/specs/people-widget/spec.md
 */
class PeopleWidgetController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest $request HTTP request.
	 * @param PeopleWidgetService $service People widget service.
	 * @param ActorResponses $context Holds the actionAuth, userSession, userId and responseHelper.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	public function __construct(
		IRequest $request,
		private readonly PeopleWidgetService $service,
		private readonly ActorResponses $context,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * GET /api/people
	 *
	 * REQ-PPL-003: returns `{users, total, hasMore}`, offset-based
	 * pagination, capped at {@see PeopleWidgetService::MAX_LIMIT}.
	 *
	 * Request parameters:
	 *  - `filters`         JSON-encoded array of FilterObject entries
	 *                      (see REQ-PPL-002 / REQ-PPL-006).
	 *  - `excludeDisabled` 1/0 boolean (default 1).
	 *  - `showBirthdays`   1/0 boolean (default 1).
	 *  - `sortBy`          One of `displayName` (default), `group`,
	 *                      `recent-activity` (rejected with 400).
	 *  - `limit`           1..100 (default 50).
	 *  - `offset`          >= 0 (default 0).
	 *
	 * @param string|null $filters JSON-encoded filter list.
	 * @param int|null $excludeDisabled 1 to exclude disabled users.
	 * @param int|null $showBirthdays 1 to include birthdate field.
	 * @param string|null $sortBy Sort key.
	 * @param int|string|null $limit Page size. Not declared `int`: Nextcloud
	 *                              casts an int `limit` itself and answers a
	 *                              non-number with a 500 before this runs.
	 * @param int|null $offset Page offset.
	 * @param string|null $q Search text; 2 or more characters search the whole directory by name, email and profile fields (REQ-PEX-003).
	 *
	 * @return JSONResponse
	 *
	 * @SuppressWarnings(PHPMD.ShortVariable) `q` is the query parameter's public name.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getUsers(
		?string $filters = null,
		?int $excludeDisabled = 1,
		?int $showBirthdays = 1,
		?string $sortBy = 'displayName',
		int|string|null $limit = PeopleWidgetService::DEFAULT_LIMIT,
		?int $offset = 0,
		?string $q = null,
	): JSONResponse {
		if ($this->context->userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}

		$user = $this->context->actor->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$this->context->actor->actionAuth->requireAction($user, 'people-widget.get-users');
		} catch (OCSForbiddenException) {
			return new JSONResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
		}


		$pageSize = $this->parseLimit(raw: $limit);
		if ($pageSize === null) {
			return new JSONResponse(['error' => 'limit must be an integer'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$parsedFilters = $this->parseFilters(raw: $filters);
		} catch (InvalidArgumentException $e) {
			return $this->badRequest(exception: $e, message: 'Invalid filters parameter');
		}

		try {
			$payload = $this->service->listUsers(
				request: new PeopleQuery(
					filters: $parsedFilters,
					sortBy: ($sortBy ?? 'displayName'),
					search: $q,
					page: PeopleQuery::window(
						excludeDisabled: ($excludeDisabled ?? 1) === 1,
						showBirthdays: ($showBirthdays ?? 1) === 1,
						limit: $pageSize,
						offset: ($offset ?? 0)
					)
				),
				viewerId: $user->getUID(),
			);
		} catch (InvalidPeopleRequestException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (InvalidArgumentException $e) {
			return $this->badRequest(exception: $e, message: 'Invalid request parameters');
		}

		return $this->context->responseHelper->success(data: $payload);
	}//end getUsers()

	/**
	 * The page size a `limit` parameter asks for: the default when absent,
	 * NULL when it is not a whole number.
	 *
	 * @param int|string|null $raw The `limit` parameter.
	 *
	 * @return int|null The page size, or NULL when it is not an integer.
	 */
	private function parseLimit(int|string|null $raw): ?int {
		if ($raw === null || $raw === '') {
			return PeopleWidgetService::DEFAULT_LIMIT;
		}

		if (is_int(value: $raw) === true) {
			return $raw;
		}

		if (preg_match(pattern: '/^-?\d+$/', subject: trim(string: $raw)) !== 1) {
			return null;
		}

		return (int) trim(string: $raw);
	}//end parseLimit()

	/**
	 * A logged HTTP 400 for a rejected request parameter.
	 *
	 * @param InvalidArgumentException $exception The rejection.
	 * @param string                   $message   The log message.
	 *
	 * @return JSONResponse The error envelope.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	private function badRequest(InvalidArgumentException $exception, string $message): JSONResponse {
		return $this->context->responseHelper->error(
			exception: $exception,
			statusCode: Http::STATUS_BAD_REQUEST,
			message: $message
		);
	}//end badRequest()

	/**
	 * Parse the JSON-encoded `filters` query parameter. Returns `[]` when
	 * the parameter is absent or an empty string. Throws on malformed
	 * JSON or on a top-level non-array.
	 *
	 * Per-entry shape validation is intentionally permissive: the service
	 * layer ignores unknown filter keys, so extra fields are tolerated.
	 *
	 * @param string|null $raw The raw query string value.
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @throws InvalidArgumentException When the JSON is malformed or the
	 *                                  decoded value is not an array.
	 */
	private function parseFilters(?string $raw): array {
		if ($raw === null || $raw === '') {
			return [];
		}

		$decoded = json_decode(json: $raw, associative: true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new InvalidArgumentException(
				message: 'filters: malformed JSON (' . json_last_error_msg() . ')'
			);
		}

		if (is_array(value: $decoded) === false) {
			throw new InvalidArgumentException(
				message: 'filters: expected JSON array'
			);
		}

		// Re-key so PHPStan is happy with the array<int, ...> shape.
		return array_values(array: $decoded);
	}//end parseFilters()
}//end class
