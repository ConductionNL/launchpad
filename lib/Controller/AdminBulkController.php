<?php

/**
 * AdminBulkController
 *
 * Admin-only controller exposing the four batch endpoints from the
 * `dashboard-bulk-operations` capability:
 *
 * - `POST /api/admin/dashboards/bulk-delete`
 * - `POST /api/admin/dashboards/bulk-move`
 * - `POST /api/admin/dashboards/bulk-status`
 * - `POST /api/admin/dashboards/bulk-reindex`
 *
 * Each endpoint enforces the all-or-nothing permission pre-check
 * (REQ-BULK-011), the per-request size cap (REQ-BULK-006), and
 * dispatches into {@see BulkOperationService} which owns the per-uuid
 * idempotency, dry-run, and audit-event logic.
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

use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\RequestGuard;
use OCA\LaunchPad\Service\BulkOperationService;
use OCA\LaunchPad\Service\BulkStatusRequest;
use OCA\LaunchPad\Service\PermissionDeniedException;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Bulk admin endpoints for dashboards (REQ-BULK-001..011).
 *
 * @spec openspec/specs/dashboard-bulk-operations/spec.md
 */
class AdminBulkController extends Controller {
	use RespondsWithErrors;

	/**
	 * Constructor.
	 *
	 * @param IRequest $request The current request.
	 * @param BulkOperationService $bulkService The bulk service.
	 * @param RequestGuard $guard The administrator check.
	 */
	public function __construct(
		IRequest $request,
		private readonly BulkOperationService $bulkService,
		private readonly RequestGuard $guard,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * `POST /api/admin/dashboards/bulk-delete` — REQ-BULK-001.
	 *
	 * @param mixed $dashboardUuids The UUID array.
	 * @param bool|null $dryRun When true, preview only.
	 * @param bool|null $cascade When true, cascade into children.
	 *
	 * @return JSONResponse The bulk-delete envelope.
	 *
	 * @spec openspec/specs/dashboard-bulk-operations/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function bulkDelete(
		mixed $dashboardUuids = null,
		?bool $dryRun = null,
		?bool $cascade = null,
	): JSONResponse {
		$uuids = $this->authorisedUuids(dashboardUuids: $dashboardUuids);
		if ($uuids instanceof JSONResponse) {
			return $uuids;
		}

		$userId = (string)$this->guard->userSession->getUser()?->getUID();
		$isDryRun = $this->resolveBool(value: $dryRun, queryKey: 'dryRun');
		$doCascade = $this->resolveBool(value: $cascade, queryKey: 'cascade');

		return $this->runBulk(
			operation: fn (): array => $this->bulkService->bulkDelete(
				dashboardUuids: $uuids,
				userId: $userId,
				dryRun: $isDryRun,
				cascade: $doCascade
			)
		);
	}//end bulkDelete()

	/**
	 * `POST /api/admin/dashboards/bulk-move` — REQ-BULK-002.
	 *
	 * @param mixed $dashboardUuids The UUID array.
	 * @param string|null $parentUuid The new parent UUID
	 *                                (NULL ⇒ root).
	 * @param bool|null $dryRun When true, preview only.
	 *
	 * @return JSONResponse The bulk-move envelope.
	 *
	 * @spec openspec/specs/dashboard-bulk-operations/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function bulkMove(
		mixed $dashboardUuids = null,
		?string $parentUuid = null,
		?bool $dryRun = null,
	): JSONResponse {
		$uuids = $this->authorisedUuids(dashboardUuids: $dashboardUuids);
		if ($uuids instanceof JSONResponse) {
			return $uuids;
		}

		$userId = (string)$this->guard->userSession->getUser()?->getUID();
		$isDryRun = $this->resolveBool(value: $dryRun, queryKey: 'dryRun');

		return $this->runBulk(
			operation: fn (): array => $this->bulkService->bulkMove(
				dashboardUuids: $uuids,
				parentUuid: $parentUuid,
				userId: $userId,
				dryRun: $isDryRun
			)
		);
	}//end bulkMove()

	/**
	 * `POST /api/admin/dashboards/bulk-status` — REQ-BULK-003.
	 *
	 * @param mixed $dashboardUuids The UUID array.
	 * @param string|null $publicationStatus The target status enum value.
	 * @param string|null $publishAt Future ISO-8601 timestamp.
	 * @param bool|null $dryRun When true, preview only.
	 *
	 * @return JSONResponse The bulk-status envelope.
	 *
	 * @spec openspec/specs/dashboard-bulk-operations/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function bulkStatus(
		mixed $dashboardUuids = null,
		?string $publicationStatus = null,
		?string $publishAt = null,
		?bool $dryRun = null,
	): JSONResponse {
		$uuids = $this->authorisedUuids(dashboardUuids: $dashboardUuids);
		if ($uuids instanceof JSONResponse) {
			return $uuids;
		}

		if ($publicationStatus === null || trim($publicationStatus) === '') {
			return $this->failure(message: 'publicationStatus is required', statusCode: Http::STATUS_BAD_REQUEST);
		}

		$userId = (string)$this->guard->userSession->getUser()?->getUID();
		$isDryRun = $this->resolveBool(value: $dryRun, queryKey: 'dryRun');

		return $this->runBulk(
			operation: fn (): array => $this->bulkService->bulkStatus(
				dashboardUuids: $uuids,
				request: new BulkStatusRequest(
					publicationStatus: $publicationStatus,
					publishAt: $publishAt,
					dryRun: $isDryRun
				),
				userId: $userId
			)
		);
	}//end bulkStatus()

	/**
	 * `POST /api/admin/dashboards/bulk-reindex` — REQ-BULK-004.
	 *
	 * @param mixed $dashboardUuids The UUID array.
	 * @param bool|null $dryRun When true, preview only.
	 *
	 * @return JSONResponse The bulk-reindex envelope.
	 *
	 * @spec openspec/specs/dashboard-bulk-operations/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function bulkReindex(
		mixed $dashboardUuids = null,
		?bool $dryRun = null,
	): JSONResponse {
		$uuids = $this->authorisedUuids(dashboardUuids: $dashboardUuids);
		if ($uuids instanceof JSONResponse) {
			return $uuids;
		}

		$userId = (string)$this->guard->userSession->getUser()?->getUID();
		$isDryRun = $this->resolveBool(value: $dryRun, queryKey: 'dryRun');

		return $this->runBulk(
			operation: fn (): array => $this->bulkService->bulkReindex(
				dashboardUuids: $uuids,
				userId: $userId,
				dryRun: $isDryRun
			)
		);
	}//end bulkReindex()

	/**
	 * The validated UUID list of an administrator's request, else the 403 or 400 refusal.
	 *
	 * @param mixed $dashboardUuids The raw `dashboardUuids` body field.
	 *
	 * @return string[]|JSONResponse The UUIDs, or the refusal.
	 */
	private function authorisedUuids(mixed $dashboardUuids): array|JSONResponse {
		$guard = $this->guard->refusal();
		if ($guard !== null) {
			return $guard;
		}

		$uuids = $this->extractUuids(value: $dashboardUuids);
		if ($uuids === null) {
			return $this->failure(message: 'dashboardUuids must be an array of strings', statusCode: Http::STATUS_BAD_REQUEST);
		}

		return $uuids;
	}//end authorisedUuids()

	/**
	 * Run a bulk operation: its envelope as HTTP 200, a denied dashboard as 403, a refused request as 400.
	 *
	 * @param \Closure $operation Runs the bulk service call and returns its envelope.
	 *
	 * @return JSONResponse The response.
	 */
	private function runBulk(\Closure $operation): JSONResponse {
		try {
			$result = $operation();
		} catch (PermissionDeniedException $e) {
			return new JSONResponse(
				data: [
					'error' => $e->getMessage(),
					'deniedUuids' => $e->getDeniedUuids(),
				],
				statusCode: Http::STATUS_FORBIDDEN
			);
		} catch (InvalidArgumentException $e) {
			return $this->failure(message: $e->getMessage(), statusCode: Http::STATUS_BAD_REQUEST);
		}//end try

		return new JSONResponse(data: $result, statusCode: Http::STATUS_OK);
	}//end runBulk()

	/**
	 * Validate and unwrap a `dashboardUuids` body field into a string
	 * list. Returns null when the value is not a list of strings.
	 *
	 * @param mixed $value The raw decoded value.
	 *
	 * @return string[]|null The extracted UUID list, or null on
	 *                       validation failure.
	 */
	private function extractUuids(mixed $value): ?array {
		if (is_array($value) === false) {
			return null;
		}

		$uuids = [];
		foreach ($value as $item) {
			$trimmed = $this->trimmedUuid(item: $item);
			if ($trimmed === null) {
				return null;
			}

			$uuids[] = $trimmed;
		}

		return $uuids;
	}//end extractUuids()

	/**
	 * One list item as a trimmed UUID; null when it is not a non-blank string.
	 *
	 * @param mixed $item The raw list item.
	 *
	 * @return string|null The trimmed UUID.
	 */
	private function trimmedUuid(mixed $item): string|null {
		if (is_string($item) === false) {
			return null;
		}

		$trimmed = trim($item);
		if ($trimmed === '') {
			return null;
		}

		return $trimmed;
	}//end trimmedUuid()

	/**
	 * Resolve a boolean parameter that may arrive either in the body
	 * (`bool`) or via the query string (`?dryRun=true`). The query
	 * string takes precedence when the body parameter is null.
	 *
	 * @param bool|null $value The body-parsed value.
	 * @param string $queryKey The query string key.
	 *
	 * @return bool The resolved boolean.
	 */
	private function resolveBool(?bool $value, string $queryKey): bool {
		if ($value !== null) {
			return $value;
		}

		$raw = $this->request->getParam(key: $queryKey);
		if ($raw === null || $raw === '') {
			return false;
		}

		$lower = strtolower((string)$raw);
		return in_array(needle: $lower, haystack: ['1', 'true', 'yes', 'on'], strict: true);
	}//end resolveBool()
}//end class
