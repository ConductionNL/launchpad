<?php

/**
 * AdminCleanupController
 *
 * HTTP surface for the orphaned-data-cleanup pipeline. Two endpoints,
 * both admin-only:
 *
 *  - `GET  /api/admin/cleanup/scan`  — REQ-CLN-004
 *  - `POST /api/admin/cleanup/purge` — REQ-CLN-005
 *
 * Admin enforcement is done at request time via
 * {@see \OCA\LaunchPad\Service\RequestGuard}. Both unauthenticated and
 * non-admin callers receive HTTP 403: the two are deliberately not told
 * apart, so the admin surface does not leak which users exist.
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
use OCA\LaunchPad\Service\RequestGuard;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Admin endpoints for scan + purge.
 */
class AdminCleanupController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest       $request The request.
	 * @param CleanupSources $cleanup The cleanup service and category registry.
	 * @param RequestGuard $guard The session and administrator check.
	 */
	public function __construct(
		IRequest $request,
		private readonly CleanupSources $cleanup,
		private readonly RequestGuard $guard,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Inline admin guard.
	 *
	 * @return JSONResponse|null Non-null = caller must be rejected.
	 */
	private function assertAdmin(): ?JSONResponse {
		$refusal = $this->guard->refusal();
		if ($refusal !== null) {
			$refusal->setStatus(Http::STATUS_FORBIDDEN);
		}

		return $refusal;
	}//end assertAdmin()

	/**
	 * `GET /api/admin/cleanup/scan` — REQ-CLN-004.
	 *
	 * Returns a JSON envelope describing the per-category orphan
	 * counts. Reads from the distributed cache when available
	 * (REQ-CLN-010) and surfaces `cached`/`cachedAt` hints so the UI
	 * can display "last refreshed" badges.
	 *
	 * @return JSONResponse The scan result.
	 *
	 * @spec openspec/specs/orphaned-data-cleanup/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function scan(): JSONResponse {
		$guard = $this->assertAdmin();
		if ($guard !== null) {
			return $guard;
		}

		$cached = $this->cleanup->cleanupService->getCachedScanResult();
		if ($cached !== null) {
			return new JSONResponse(
				data: array_merge(
					$cached->jsonSerialize(),
					[
						'cached' => true,
						'cachedAt' => $cached->getScannedAt(),
					]
				),
				statusCode: Http::STATUS_OK
			);
		}

		$result = $this->cleanup->cleanupService->scan();

		return new JSONResponse(
			data: array_merge(
				$result->jsonSerialize(),
				[
					'cached' => false,
					'cachedAt' => null,
				]
			),
			statusCode: Http::STATUS_OK
		);
	}//end scan()

	/**
	 * `POST /api/admin/cleanup/purge` — REQ-CLN-005.
	 *
	 * Body shape:
	 *  {
	 *      "categories": ["expired_locks", ...],   // optional, []=all
	 *      "dryRun":     true|false                // optional, false default
	 *  }
	 *
	 * Returns the per-category breakdown plus total, duration, and
	 * dryRun flag. Unknown categories receive HTTP 400 with the list
	 * of valid names so the caller can correct the request.
	 *
	 * @param array<int, mixed>|null $categories Per-category filter.
	 * @param bool|null $dryRun Dry-run flag.
	 *
	 * @return JSONResponse The purge result.
	 *
	 * @spec openspec/specs/orphaned-data-cleanup/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function purge(?array $categories = null, ?bool $dryRun = null): JSONResponse {
		$guard = $this->assertAdmin();
		if ($guard !== null) {
			return $guard;
		}

		$names = $this->normaliseCategories(input: ($categories ?? []));
		if ($names === null) {
			return new JSONResponse(
				data: [
					'error' => 'Unknown cleanup category in request',
					'validCategories' => $this->cleanup->registry->getCategoryNames(),
				],
				statusCode: Http::STATUS_BAD_REQUEST
			);
		}

		$userId = '';
		$user = $this->guard->userSession->getUser();
		if ($user !== null) {
			$userId = $user->getUID();
		}

		$result = $this->cleanup->cleanupService->purge(
			categoryNames: $names,
			dryRun: ($dryRun ?? false),
			userId: $userId,
			source: 'api',
		);

		return new JSONResponse(
			data: [
				'purgedByCategory' => $result->getByCategory(),
				'totalRows' => $result->getTotalRows(),
				'durationMs' => $result->getDurationMs(),
				'dryRun' => $result->isDryRun(),
				'skipped' => $result->getSkipped(),
			],
			statusCode: Http::STATUS_OK
		);
	}//end purge()

	/**
	 * Normalise the API-supplied categories list.
	 *
	 * Filters non-string entries silently (defence in depth — the
	 * controller is reached after framework JSON parsing) and
	 * returns `null` if any of the remaining names are not registered.
	 * An empty list is returned as `[]` (which the orchestrator
	 * treats as "all categories").
	 *
	 * @param array<int, mixed> $input The raw input list.
	 *
	 * @return array<int, string>|null The validated names or null.
	 */
	private function normaliseCategories(array $input): ?array {
		$known = $this->cleanup->registry->getCategoryNames();
		$normalised = [];

		foreach ($input as $value) {
			if (is_string(value: $value) === false || $value === '') {
				continue;
			}

			if (in_array(needle: $value, haystack: $known, strict: true) === false) {
				return null;
			}

			$normalised[] = $value;
		}

		return $normalised;
	}//end normaliseCategories()
}//end class
