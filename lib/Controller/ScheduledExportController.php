<?php

/**
 * ScheduledExportController
 *
 * The scheduled-exports API (tasks.md tasks 10-11): CRUD of exports, a
 * manual "Run now", a preview that renders without delivering or writing
 * a run-row (REQ-SCH-009), the run history, and a retry of a run's failed
 * deliveries. Every endpoint needs a signed-in user granted its action,
 * and an export is reachable only by its owner or an administrator
 * (anything else answers 404, like a missing export).
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
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\RequestGuard;
use OCA\LaunchPad\Service\ScheduledExport\AuditLog;
use OCA\LaunchPad\Service\ScheduledExport\ExportFire;
use OCA\LaunchPad\Service\ScheduledExport\FireTrigger;
use OCA\LaunchPad\Service\ScheduledExport\RenderException;
use OCA\LaunchPad\Service\ScheduledExport\RenderPipeline;
use OCA\LaunchPad\Service\ScheduledExport\RenderResult;
use OCA\LaunchPad\Service\ScheduledExport\ScheduledExportService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use RuntimeException;

/**
 * Scheduled exports over HTTP.
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-10
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)   One action per documented route (tasks.md task 10).
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) The route surface spans exports, runs, run now and preview.
 * @SuppressWarnings(PHPMD.StaticAccess)           FireTrigger::manual() is the named constructor of the value.
 */
class ScheduledExportController extends Controller {

	private const ROUTE_KEYS = ['id', 'runId', '_route'];

	private const SORTABLE = ['name', 'nextRunAt', 'lastRunAt'];

	/**
	 * Constructor.
	 *
	 * @param IRequest               $request The request.
	 * @param RequestGuard           $guard   The signed-in user and the action check.
	 * @param ScheduledExportService $exports The exports.
	 * @param AuditLog               $log     The run-rows.
	 * @param ExportFire             $fire    Run now and retry.
	 * @param RenderPipeline         $render  Preview.
	 */
	public function __construct(
		IRequest $request,
		private readonly RequestGuard $guard,
		private readonly ScheduledExportService $exports,
		private readonly AuditLog $log,
		private readonly ExportFire $fire,
		private readonly RenderPipeline $render,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * `GET /api/exports`: the caller's exports, sorted and paged.
	 *
	 * Query parameters: `limit` (page size, 1-200, default 50), `offset`
	 * (rows to skip), `sort` (`name`, `nextRunAt` or `lastRunAt`) and
	 * `order` (`asc` or `desc`).
	 *
	 * @return Response `{results, total, limit, offset}`.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-10
	 */
	#[NoAdminRequired]
	public function index(): Response {
		$refusal = $this->guard->actionRefusal(action: 'scheduled-exports.list');
		return $this->guarded(refusal: $refusal, work: function (string $uid): JSONResponse {
			$rows = $this->sorted(rows: $this->exports->findVisible(userId: $uid));

			return $this->page(rows: $rows, limit: (int)$this->request->getParam('limit', 50), offset: (int)$this->request->getParam('offset', 0));
		});
	}//end index()

	/**
	 * The rows sorted by the asked `sort` key (`name` for any other) in the asked `order`.
	 *
	 * @param list<array<string, mixed>> $rows The exports.
	 *
	 * @return list<array<string, mixed>> The sorted exports.
	 */
	private function sorted(array $rows): array {
		$key = (string)$this->request->getParam('sort', 'name');
		if (in_array($key, self::SORTABLE, true) === false) {
			$key = 'name';
		}

		usort($rows, static fn (array $a, array $b): int => strcmp((string)($a[$key] ?? ''), (string)($b[$key] ?? '')));
		if (strtolower((string)$this->request->getParam('order', 'asc')) === 'desc') {
			$rows = array_reverse($rows);
		}

		return $rows;
	}//end sorted()

	/**
	 * `POST /api/exports`: create an export.
	 *
	 * @return Response 201 with the export; 400 with the reasons.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-10
	 */
	#[NoAdminRequired]
	public function create(): Response {
		$refusal = $this->guard->actionRefusal(action: 'scheduled-exports.create');
		return $this->guarded(refusal: $refusal, work: function (string $uid): JSONResponse {
			$export = $this->body();
			unset($export['id']);
			return new JSONResponse($this->exports->save(export: $export, userId: $uid), Http::STATUS_CREATED);
		});
	}//end create()

	/**
	 * `GET /api/exports/{id}`: one export.
	 *
	 * @param string $id The export uuid.
	 *
	 * @return Response The export; 404 when it is not the caller's.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-10
	 */
	#[NoAdminRequired]
	public function show(string $id): Response {
		$refusal = $this->guard->actionRefusal(action: 'scheduled-exports.read');
		return $this->guarded(refusal: $refusal, work: fn (string $uid): JSONResponse => new JSONResponse($this->exports->findOne(id: $id, userId: $uid)));
	}//end show()

	/**
	 * `PATCH /api/exports/{id}`: update fields of an export; a changed recurrence is rescheduled.
	 *
	 * @param string $id The export uuid.
	 *
	 * @return Response The export; 400 with the reasons; 404.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-10
	 */
	#[NoAdminRequired]
	public function update(string $id): Response {
		$refusal = $this->guard->actionRefusal(action: 'scheduled-exports.update');
		$save    = fn (string $uid): JSONResponse => new JSONResponse($this->exports->save(export: ['id' => $id] + $this->body(), userId: $uid));
		return $this->guarded(refusal: $refusal, work: $save);
	}//end update()

	/**
	 * `DELETE /api/exports/{id}`: soft-delete an export.
	 *
	 * @param string $id The export uuid.
	 *
	 * @return Response 204-like `{deleted: true}`; 404.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-10
	 */
	#[NoAdminRequired]
	public function destroy(string $id): Response {
		$refusal = $this->guard->actionRefusal(action: 'scheduled-exports.delete');
		return $this->guarded(refusal: $refusal, work: function (string $uid) use ($id): JSONResponse {
			$this->exports->delete(id: $id, userId: $uid);
			return new JSONResponse(['deleted' => true]);
		});
	}//end destroy()

	/**
	 * `POST /api/exports/{id}/run`: fire now as the caller; the schedule is not moved.
	 *
	 * @param string $id The export uuid.
	 *
	 * @return Response 201 with the run-row; 404.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-009-manual-run-now-and-preview-actions
	 */
	#[NoAdminRequired]
	public function run(string $id): Response {
		$refusal = $this->guard->actionRefusal(action: 'scheduled-exports.run');
		return $this->guarded(refusal: $refusal, work: function (string $uid) use ($id): JSONResponse {
			$export = $this->exports->findOne(id: $id, userId: $uid);
			return new JSONResponse($this->fire->fire(export: $export, trigger: FireTrigger::manual(userId: $uid)), Http::STATUS_CREATED);
		});
	}//end run()

	/**
	 * `POST /api/exports/{id}/preview`: render for one recipient's filter
	 * context without delivering and without a run-row. With a `format` the
	 * file itself is returned; without, every target as base64 JSON.
	 *
	 * Request parameters: `recipientId` (the recipient whose filter context
	 * applies; empty for none) and `format` (`pdf`, `png`, `csv` or `xlsx`;
	 * empty for every target).
	 *
	 * @param string $id The export uuid.
	 *
	 * @return Response The file, or `{artefacts}`; 404; 422 when the render fails.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-009-manual-run-now-and-preview-actions
	 */
	#[NoAdminRequired]
	public function preview(string $id): Response {
		$refusal = $this->guard->actionRefusal(action: 'scheduled-exports.preview');
		return $this->guarded(refusal: $refusal, work: function (string $uid) use ($id): Response {
			$export  = $this->exports->findOne(id: $id, userId: $uid);
			$format  = (string)$this->request->getParam('format', '');
			$context = self::recipientContext(export: $export, recipientId: (string)$this->request->getParam('recipientId', ''));
			if ($format !== '') {
				$export['renderTargets'] = [self::targetFor(export: $export, format: $format)];
			}

			$results = $this->render->preview(export: $export, filterContext: $context);
			if ($format !== '' && count($results) === 1) {
				return new DataDownloadResponse($results[0]->content, $results[0]->filename, $results[0]->mimeType());
			}

			$encode = static fn (RenderResult $r): array => [
				'filename' => $r->filename,
				'mimeType' => $r->mimeType(),
				'content' => base64_encode($r->content),
			] + $r->toAudit();
			return new JSONResponse(['artefacts' => array_map($encode, $results)]);
		});
	}//end preview()

	/**
	 * The filter context of the export's recipient with this id (or index); [] for none.
	 *
	 * @param array<string, mixed> $export      The export.
	 * @param string               $recipientId The recipient id; empty for none.
	 *
	 * @return array<string, mixed> The filter context.
	 */
	private static function recipientContext(array $export, string $recipientId): array {
		$context = [];
		foreach ((array)($export['recipients'] ?? []) as $index => $recipient) {
			if ($recipientId !== '' && (string)($recipient['id'] ?? $index) === $recipientId) {
				$context = (array)($recipient['filterContext'] ?? []);
			}
		}

		return $context;
	}//end recipientContext()

	/**
	 * The export's first render target of this format, or a bare target of it.
	 *
	 * @param array<string, mixed> $export The export.
	 * @param string               $format The format asked for.
	 *
	 * @return array<string, mixed> The render target.
	 */
	private static function targetFor(array $export, string $format): array {
		foreach ((array)($export['renderTargets'] ?? []) as $target) {
			if (($target['format'] ?? null) === $format) {
				return $target;
			}
		}

		return ['format' => $format];
	}//end targetFor()

	/**
	 * `GET /api/exports/{id}/runs`: the run history, newest first, paged.
	 *
	 * @param string $id     The export uuid.
	 * @param int    $limit  Page size, 1-200.
	 * @param int    $offset Rows to skip.
	 *
	 * @return Response `{results, total, limit, offset}`; 404.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-10
	 */
	#[NoAdminRequired]
	public function runs(string $id, int $limit = 50, int $offset = 0): Response {
		$refusal = $this->guard->actionRefusal(action: 'scheduled-exports.runs');
		return $this->guarded(refusal: $refusal, work: function (string $uid) use ($id, $limit, $offset): JSONResponse {
			$this->exports->findOne(id: $id, userId: $uid);
			return $this->page(rows: $this->log->runsOf(exportId: $id), limit: $limit, offset: $offset);
		});
	}//end runs()

	/**
	 * `GET /api/exports/{id}/runs/{runId}`: one run-row with every delivery.
	 *
	 * @param string $id    The export uuid.
	 * @param string $runId The run uuid.
	 *
	 * @return Response The run-row; 404.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-10
	 */
	#[NoAdminRequired]
	public function showRun(string $id, string $runId): Response {
		$refusal = $this->guard->actionRefusal(action: 'scheduled-exports.runs');
		$show    = fn (string $uid): JSONResponse => new JSONResponse($this->visibleRun(id: $id, runId: $runId, userId: $uid));
		return $this->guarded(refusal: $refusal, work: $show);
	}//end showRun()

	/**
	 * `POST /api/exports/{id}/runs/{runId}/retry`: retry every delivery of the run that failed for good.
	 *
	 * @param string $id    The export uuid.
	 * @param string $runId The run uuid.
	 *
	 * @return Response The updated run-row; 404.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-006-retry-with-exponential-backoff-and-dead-letter
	 */
	#[NoAdminRequired]
	public function retry(string $id, string $runId): Response {
		$refusal = $this->guard->actionRefusal(action: 'scheduled-exports.retry');
		$retry   = fn (string $uid): JSONResponse => new JSONResponse(
			$this->fire->retryRun(run: $this->visibleRun(id: $id, runId: $runId, userId: $uid))
		);
		return $this->guarded(refusal: $refusal, work: $retry);
	}//end retry()

	/**
	 * A run of an export the user may see.
	 *
	 * @param string $id     The export uuid.
	 * @param string $runId  The run uuid.
	 * @param string $userId The user.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws DoesNotExistException When the export or the run is not the user's to see.
	 */
	private function visibleRun(string $id, string $runId, string $userId): array {
		$this->exports->findOne(id: $id, userId: $userId);
		$run = $this->log->runOf(exportId: $id, runId: $runId);
		if ($run === null) {
			throw new DoesNotExistException('Run not found');
		}

		return $run;
	}//end visibleRun()

	/**
	 * Run the work for a signed-in user granted the action, mapping the service's refusals onto HTTP.
	 *
	 * @param JSONResponse|null $refusal The action check's refusal, or null when granted.
	 * @param callable          $work    Gets the user id, returns the response.
	 *
	 * @return Response
	 */
	private function guarded(?JSONResponse $refusal, callable $work): Response {
		if ($refusal !== null) {
			return $refusal;
		}

		$user = $this->guard->sessionUser();
		if ($user instanceof JSONResponse) {
			return $user;
		}

		try {
			return $work($user->getUID());
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(['error' => 'invalid_argument', 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (RenderException $e) {
			return new JSONResponse(['error' => 'render_failed', 'message' => $e->getMessage()], Http::STATUS_UNPROCESSABLE_ENTITY);
		} catch (RuntimeException) {
			return new JSONResponse(['error' => 'Scheduled exports are not available'], Http::STATUS_SERVICE_UNAVAILABLE);
		}
	}//end guarded()

	/**
	 * One page of rows.
	 *
	 * @param list<array<string, mixed>> $rows   The rows.
	 * @param int                        $limit  Page size, clamped to 1-200.
	 * @param int                        $offset Rows to skip, at least 0.
	 *
	 * @return JSONResponse
	 */
	private function page(array $rows, int $limit, int $offset): JSONResponse {
		$limit  = max(1, min(200, $limit));
		$offset = max(0, $offset);

		return new JSONResponse(
			['results' => array_slice($rows, $offset, $limit), 'total' => count($rows), 'limit' => $limit, 'offset' => $offset]
		);
	}//end page()

	/**
	 * The request body without the route parameters.
	 *
	 * @return array<string, mixed>
	 */
	private function body(): array {
		return array_diff_key($this->request->getParams(), array_flip(self::ROUTE_KEYS));
	}//end body()
}//end class
