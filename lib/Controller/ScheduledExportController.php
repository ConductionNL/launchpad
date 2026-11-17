<?php

/**
 * ScheduledExportController
 *
 * The scheduled-exports API (scheduled-exports task 10): list, read, create,
 * update and soft-delete exports, read their run history, and retry a run's
 * failed deliveries. Every method checks its ADR-023 action first; which
 * exports and run-rows a user sees is OpenRegister's access rules, read
 * through {@see ScheduledExportService} and {@see ScheduledExportAuditLog},
 * so an export a user may not see answers 404, never 403. Secrets come back
 * redacted.
 *
 * "Run now" and "Preview" (`POST /api/exports/{id}/run` and `/preview`) are
 * not routed yet: both render, and the render pipeline (task 4) is not built.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 *
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT:auto
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-10
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use Closure;
use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\DeliveryDispatcher;
use OCA\LaunchPad\Service\RequestGuard;
use OCA\LaunchPad\Service\RetryHandler;
use OCA\LaunchPad\Service\ScheduledExportAuditLog;
use OCA\LaunchPad\Service\ScheduledExportService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;

/**
 * Exports, their runs, and the manual retry of a run.
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-10
 */
class ScheduledExportController extends Controller {

	/**
	 * The action that reads exports and their runs.
	 */
	public const ACTION_READ = 'scheduled-export.read';

	/**
	 * The action that writes, deletes and retries.
	 */
	public const ACTION_MANAGE = 'scheduled-export.manage';

	/**
	 * The properties a caller may send.
	 */
	private const FIELDS = [
		'name',
		'description',
		'subjectType',
		'subjectId',
		'enabled',
		'recurrence',
		'renderTargets',
		'recipients',
		'retention',
		'runTimezone',
	];

	/**
	 * The most run-rows one page returns.
	 */
	private const MAX_LIMIT = 200;

	/**
	 * Constructor.
	 *
	 * @param IRequest                $request    The request.
	 * @param ScheduledExportService  $exports    The exports, read under the user's access rules.
	 * @param ScheduledExportAuditLog $auditLog   The run-rows.
	 * @param DeliveryDispatcher      $dispatcher Attempts a run's deliveries again.
	 * @param RetryHandler            $retry      Re-arms a failed delivery and rolls up a run's status.
	 * @param RequestGuard            $guard      The session user and the action check.
	 * @param ITimeFactory            $time       The clock.
	 */
	public function __construct(
		IRequest $request,
		private readonly ScheduledExportService $exports,
		private readonly ScheduledExportAuditLog $auditLog,
		private readonly DeliveryDispatcher $dispatcher,
		private readonly RetryHandler $retry,
		private readonly RequestGuard $guard,
		private readonly ITimeFactory $time,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * `GET /api/exports`: the exports the user may see, `limit`/`offset` paged and sorted by name.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-10
	 */
	#[NoAdminRequired]
	public function index(): JSONResponse {
		return $this->guard->actionRefusal(action: 'scheduled-export.read') ?? $this->asUser(
			run: function (string $userId): JSONResponse {
				$exports = $this->exports->findVisible(userId: $userId);
				usort(
					$exports,
					static fn (array $a, array $b): int => strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''))
				);
				[$limit, $offset] = $this->page();

				return new JSONResponse(
					[
						'results' => array_slice($exports, $offset, $limit),
						'total' => count($exports),
						'limit' => $limit,
						'offset' => $offset,
					]
				);
			}
		);
	}//end index()

	/**
	 * `GET /api/exports/{id}`: one export; 404 when the user may not see it.
	 *
	 * @param string $id The export uuid.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-10
	 */
	#[NoAdminRequired]
	public function show(string $id): JSONResponse {
		return $this->guard->actionRefusal(action: 'scheduled-export.read') ?? $this->asUser(
			run: function (string $userId) use ($id): JSONResponse {
				$export = $this->exports->findOne(id: $id, userId: $userId);
				if ($export === null) {
					return self::notFound();
				}

				return new JSONResponse($export);
			}
		);
	}//end show()

	/**
	 * `POST /api/exports`: create an export; 201 with it, 400 naming what is wrong.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-10
	 */
	#[NoAdminRequired]
	public function create(): JSONResponse {
		return $this->guard->actionRefusal(action: 'scheduled-export.manage') ?? $this->asUser(
			run: function (): JSONResponse {
				try {
					$saved = $this->exports->save(export: $this->fields(), id: null, now: $this->time->now());
				} catch (InvalidArgumentException $e) {
					return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
				}

				return new JSONResponse($saved, Http::STATUS_CREATED);
			}
		);
	}//end create()

	/**
	 * `PATCH /api/exports/{id}`: change the fields sent; the next run is recomputed.
	 *
	 * 404 when the user may not see the export, 400 naming what is wrong.
	 *
	 * @param string $id The export uuid.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-10
	 */
	#[NoAdminRequired]
	public function update(string $id): JSONResponse {
		return $this->guard->actionRefusal(action: 'scheduled-export.manage') ?? $this->asUser(
			run: function (string $userId) use ($id): JSONResponse {
				if ($this->exports->findOne(id: $id, userId: $userId) === null) {
					return self::notFound();
				}

				try {
					$saved = $this->exports->save(export: $this->fields(), id: $id, now: $this->time->now());
				} catch (InvalidArgumentException $e) {
					return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
				}

				return new JSONResponse($saved);
			}
		);
	}//end update()

	/**
	 * `DELETE /api/exports/{id}`: soft-delete; 404 when the user may not see it.
	 *
	 * @param string $id The export uuid.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-10
	 */
	#[NoAdminRequired]
	public function destroy(string $id): JSONResponse {
		return $this->guard->actionRefusal(action: 'scheduled-export.manage') ?? $this->asUser(
			run: function (string $userId) use ($id): JSONResponse {
				if ($this->exports->delete(id: $id, userId: $userId) === false) {
					return self::notFound();
				}

				return new JSONResponse(['deleted' => $id]);
			}
		);
	}//end destroy()

	/**
	 * `GET /api/exports/{id}/runs`: the export's run-rows, newest first, `limit`/`offset` paged.
	 *
	 * @param string $id The export uuid.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-008-audit-row-per-run-with-hash-and-delivery-outcome
	 */
	#[NoAdminRequired]
	public function runs(string $id): JSONResponse {
		return $this->guard->actionRefusal(action: 'scheduled-export.read') ?? $this->asUser(
			run: function (string $userId) use ($id): JSONResponse {
				if ($this->exports->findOne(id: $id, userId: $userId) === null) {
					return self::notFound();
				}

				[$limit, $offset] = $this->page();
				return new JSONResponse(
					[
						'results' => $this->auditLog->runsOf(exportId: $id, limit: $limit, offset: $offset),
						'limit' => $limit,
						'offset' => $offset,
					]
				);
			}
		);
	}//end runs()

	/**
	 * `GET /api/exports/{id}/runs/{runId}`: one run-row with every delivery.
	 *
	 * 404 when the export or the run is not visible, or the run is another export's.
	 *
	 * @param string $id    The export uuid.
	 * @param string $runId The run-row uuid.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-008-audit-row-per-run-with-hash-and-delivery-outcome
	 */
	#[NoAdminRequired]
	public function run(string $id, string $runId): JSONResponse {
		return $this->guard->actionRefusal(action: 'scheduled-export.read') ?? $this->asUser(
			run: function (string $userId) use ($id, $runId): JSONResponse {
				if ($this->exports->findOne(id: $id, userId: $userId) === null) {
					return self::notFound();
				}

				$run = $this->auditLog->runOf(exportId: $id, runId: $runId);
				if ($run === null) {
					return self::notFound();
				}

				return new JSONResponse($run);
			}
		);
	}//end run()

	/**
	 * `POST /api/exports/{id}/runs/{runId}/retry`: retry the run's deliveries that failed for good.
	 *
	 * Each one gets a fresh round of attempts under the export's retry policy,
	 * the first one now; the run's status is rolled up again from its
	 * deliveries. 404 when the export or the run is not visible, 409 when the
	 * run has no delivery that failed for good.
	 *
	 * @param string $id    The export uuid.
	 * @param string $runId The run-row uuid.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-006-retry-with-exponential-backoff-and-dead-letter
	 */
	#[NoAdminRequired]
	public function retry(string $id, string $runId): JSONResponse {
		return $this->guard->actionRefusal(action: 'scheduled-export.manage') ?? $this->asUser(
			run: function (string $userId) use ($id, $runId): JSONResponse {
				if ($this->exports->findOne(id: $id, userId: $userId) === null) {
					return self::notFound();
				}

				$run    = $this->auditLog->runOf(exportId: $id, runId: $runId);
				$export = $this->exports->findForRun(id: $id);
				if ($run === null || $export === null) {
					return self::notFound();
				}

				$now      = $this->time->now();
				$rearmed  = 0;
				$deliveries = [];
				foreach ((array)($run['deliveries'] ?? []) as $delivery) {
					if (is_array($delivery) === true && ($delivery['status'] ?? null) === RetryHandler::STATUS_FAILED_PERMANENT) {
						$delivery = $this->retry->rearm(delivery: $delivery, now: $now);
						$rearmed++;
					}

					$deliveries[] = $delivery;
				}

				if ($rearmed === 0) {
					return new JSONResponse(['error' => 'This run has no delivery that failed for good.'], Http::STATUS_CONFLICT);
				}

				$run['deliveries'] = $deliveries;
				$run = $this->dispatcher->retryDue(export: $export, run: $run, now: $now);
				$run = $this->auditLog->finish(
					run: $run,
					status: $this->retry->rollUp(deliveries: array_values(array_filter((array)($run['deliveries'] ?? []), 'is_array'))),
					now: $now
				);

				return new JSONResponse(['retried' => $rearmed, 'run' => $run]);
			}
		);
	}//end retry()

	/**
	 * Run the handler for the signed-in user; each caller checks its action first.
	 *
	 * @param Closure $run Takes the user id, returns the response.
	 *
	 * @return JSONResponse
	 */
	private function asUser(Closure $run): JSONResponse {
		$user = $this->guard->sessionUser();
		if ($user instanceof JSONResponse) {
			return $user;
		}

		return $run($user->getUID());
	}//end asUser()

	/**
	 * The export properties the request carries, and nothing else.
	 *
	 * @return array<string, mixed>
	 */
	private function fields(): array {
		$params = $this->request->getParams();
		return array_intersect_key($params, array_flip(self::FIELDS));
	}//end fields()

	/**
	 * The page asked for: `limit` (1 to 200, default 50) and `offset` (from 0).
	 *
	 * @return array{0: int, 1: int}
	 */
	private function page(): array {
		$limit  = (int)$this->request->getParam('limit', 50);
		$offset = (int)$this->request->getParam('offset', 0);
		return [max(1, min(self::MAX_LIMIT, $limit)), max(0, $offset)];
	}//end page()

	/**
	 * The answer for an export or run the user may not see.
	 *
	 * @return JSONResponse
	 */
	private static function notFound(): JSONResponse {
		return new JSONResponse(['error' => 'Scheduled export not found'], Http::STATUS_NOT_FOUND);
	}//end notFound()
}//end class
