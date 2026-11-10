<?php

/**
 * BulkOperationService
 *
 * Orchestrates batched admin operations on dashboards: bulk delete,
 * bulk move (re-parent), bulk publication-status update, and bulk
 * search-reindex. Implements REQ-BULK-001..011 from the
 * `dashboard-bulk-operations` capability.
 *
 * Per REQ-BULK-005 the permission pre-check is all-or-nothing
 * (a single unauthorised UUID rejects the entire batch with HTTP 403)
 * while DB-level mutations run per dashboard with continue-on-error
 * semantics — partial success is reported in `errors`. Per REQ-BULK-006
 * the maximum batch size defaults to 500 and is admin-tunable via the
 * `bulk_operation_max_per_request` app config key.
 *
 * Audit events fire exactly once per bulk operation through
 * {@see ActivityPublisher::publishGlobal()} (REQ-BULK-009): one summary
 * Activity row per request, regardless of dashboard count or dry-run
 * mode.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
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

namespace OCA\LaunchPad\Service;

use DateTime;
use DateTimeImmutable;
use InvalidArgumentException;
use OCA\LaunchPad\Activity\ActivityPublisher;
use OCA\LaunchPad\Activity\ActivityRow;
use OCA\LaunchPad\Activity\ActivitySubject;
use OCA\LaunchPad\Activity\Extension;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use OCA\LaunchPad\Exception\DashboardHasChildrenException;
use OCP\AppFramework\Db\DoesNotExistException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Orchestrates dashboard bulk operations (REQ-BULK-001..011).
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)   The four bulk endpoints
 *  share the same permission, audit, and idempotency scaffolding; splitting
 *  the service would duplicate the all-or-nothing guard logic.
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) Branching mirrors the
 *  per-operation idempotency rules pinned in REQ-BULK-007.
 * @spec                                             openspec/specs/dashboard-bulk-operations/spec.md
 */
class BulkOperationService {
	/**
	 * App config key controlling the per-request cap (REQ-BULK-006).
	 *
	 * @var string
	 */
	public const CONFIG_KEY_MAX_PER_REQUEST = 'bulk_operation_max_per_request';

	/**
	 * Default value when no admin-tuned override is present (REQ-BULK-006).
	 *
	 * @var int
	 */
	public const DEFAULT_MAX_PER_REQUEST = 500;

	/**
	 * Operation labels used in the audit payload + error envelopes
	 * (REQ-BULK-009).
	 */
	public const OP_DELETE = 'delete';
	public const OP_MOVE = 'move';
	public const OP_STATUS = 'status';
	public const OP_REINDEX = 'reindex';

	/**
	 * Stable error reasons returned in the per-uuid `errors` array.
	 */
	public const REASON_ALREADY_DELETED = 'already_deleted';
	public const REASON_PARENT_ALREADY_MATCH = 'parent_already_matches';
	public const REASON_STATUS_ALREADY_MATCH = 'status_already_matches';
	public const REASON_CYCLE_DETECTED = 'cycle_detected';
	public const REASON_TRANSACTION_FAILED = 'transaction_failed';
	public const REASON_REINDEX_FAILED = 'reindex_failed';
	public const REASON_NOT_FOUND = 'not_found';
	public const REASON_INVALID_PARENT = 'invalid_parent';

	/**
	 * Constructor.
	 *
	 * @param BulkDashboardStores $stores The dashboard and placement mappers, tree service and event dispatcher.
	 * @param BulkOperationAccess $access The permission service, group manager and app config.
	 * @param ActivityPublisher   $activityPublisher Single-event audit emitter (REQ-BULK-009).
	 * @param LoggerInterface     $logger Diagnostic logger.
	 */
	public function __construct(
		private readonly BulkDashboardStores $stores,
		private readonly BulkOperationAccess $access,
		private readonly ActivityPublisher $activityPublisher,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Hard-delete multiple dashboards (REQ-BULK-001).
	 *
	 * Returns `[deletedCount, skippedCount, errors]` (or the `wouldX`
	 * variants when `$dryRun` is true). Cascade to child dashboards is
	 * opt-in: when `$cascade` is false and any dashboard has children,
	 * that dashboard is rejected with `dashboard_has_children` and a
	 * `childCount` field in the per-uuid error.
	 *
	 * @param string[] $dashboardUuids The dashboard UUIDs to delete.
	 * @param string $userId The acting NC user ID.
	 * @param bool $dryRun When true, preview only.
	 * @param bool $cascade When true, recurse into children.
	 *
	 * @return array<string, mixed> The result envelope.
	 *
	 * @throws InvalidArgumentException When the request exceeds the cap.
	 * @throws PermissionDeniedException When any UUID is unauthorised.
	 *
	 * @spec openspec/specs/dashboard-bulk-operations/spec.md
	 */
	public function bulkDelete(
		array $dashboardUuids,
		string $userId,
		bool $dryRun = false,
		bool $cascade = false,
	): array {
		$start = microtime(as_float: true);
		$this->assertPermissions(uuids: $dashboardUuids, userId: $userId);
		$this->assertWithinCap(uuids: $dashboardUuids);

		$delete = new BulkDeleteRun();
		$delete->userId = $userId;
		$delete->dryRun = $dryRun;
		$delete->cascade = $cascade;
		$outcomes = [];
		foreach ($dashboardUuids as $uuid) {
			$outcomes[] = $this->deleteOneDashboard(uuid: (string)$uuid, delete: $delete);
		}

		[$deleted, $skipped, $errors] = (new BulkOutcome(countKey: 'deleted'))->tally(outcomes: $outcomes);

		$payload = [
			$this->countKey(dryRun: $dryRun, real: 'deletedCount', preview: 'wouldDeleteCount') => $deleted,
			$this->countKey(dryRun: $dryRun, real: 'skippedCount', preview: 'wouldSkipCount') => $skipped,
			'errors' => $errors,
			'dryRun' => $dryRun,
		];

		$run = ['dashboardUuids' => $dashboardUuids, 'userId' => $userId, 'start' => $start, 'dryRun' => $dryRun];
		$this->emitAuditEvent(operation: self::OP_DELETE, run: $run);

		return $payload;
	}//end bulkDelete()

	/**
	 * Re-parent multiple dashboards in the dashboard hierarchy
	 * (REQ-BULK-002).
	 *
	 * Cycle detection is delegated to {@see DashboardTreeService} so the
	 * single source of truth for tree invariants stays in one place.
	 *
	 * @param string[] $dashboardUuids The dashboards to re-parent.
	 * @param string|null $parentUuid The new parent UUID
	 *                                (NULL ⇒ root).
	 * @param string $userId The acting NC user ID.
	 * @param bool $dryRun When true, preview only.
	 *
	 * @return array<string, mixed> The result envelope.
	 *
	 * @throws InvalidArgumentException When the request exceeds the cap
	 *                                  or the parent does not exist.
	 * @throws PermissionDeniedException When any UUID is unauthorised.
	 *
	 * @spec openspec/specs/dashboard-bulk-operations/spec.md
	 */
	public function bulkMove(
		array $dashboardUuids,
		?string $parentUuid,
		string $userId,
		bool $dryRun = false,
	): array {
		$start = microtime(as_float: true);
		$this->assertPermissions(uuids: $dashboardUuids, userId: $userId);
		$this->assertWithinCap(uuids: $dashboardUuids);

		$this->assertParentExists(parentUuid: $parentUuid);

		$move = new BulkMoveRun();
		$move->parentUuid = $parentUuid;
		$move->now = (new DateTime())->format(format: 'Y-m-d H:i:s');
		$move->dryRun = $dryRun;
		$outcomes = [];
		foreach ($dashboardUuids as $uuid) {
			$outcomes[] = $this->moveOneDashboard(uuid: (string)$uuid, move: $move);
		}

		[$moved, $skipped, $errors] = (new BulkOutcome(countKey: 'moved'))->tally(outcomes: $outcomes);

		$payload = [
			$this->countKey(dryRun: $dryRun, real: 'movedCount', preview: 'wouldMoveCount') => $moved,
			$this->countKey(dryRun: $dryRun, real: 'skippedCount', preview: 'wouldSkipCount') => $skipped,
			'errors' => $errors,
			'dryRun' => $dryRun,
		];

		$run = ['dashboardUuids' => $dashboardUuids, 'userId' => $userId, 'start' => $start, 'dryRun' => $dryRun];
		$this->emitAuditEvent(operation: self::OP_MOVE, run: $run);

		return $payload;
	}//end bulkMove()

	/**
	 * Update the publication status of multiple dashboards (REQ-BULK-003).
	 *
	 * Status enum is the canonical {@see Dashboard::STATUS_DRAFT} /
	 * {@see Dashboard::STATUS_PUBLISHED} / {@see Dashboard::STATUS_SCHEDULED}
	 * triple. `publishAt` is required when the target status is
	 * `scheduled` and is rejected when in the past.
	 *
	 * @param string[]          $dashboardUuids The dashboards to mutate.
	 * @param BulkStatusRequest $request        Target status, future ISO-8601
	 *                                          `publishAt` (required for
	 *                                          `scheduled`) and dry-run flag.
	 * @param string            $userId         The acting NC user ID.
	 *
	 * @return array<string, mixed> The result envelope.
	 *
	 * @throws InvalidArgumentException When the status is not in the
	 *                                  enum, when `publishAt` is missing
	 *                                  for `scheduled`, when the cap is
	 *                                  exceeded, or when `publishAt` is
	 *                                  in the past.
	 * @throws PermissionDeniedException When any UUID is unauthorised.
	 *
	 * @spec openspec/specs/dashboard-bulk-operations/spec.md
	 */
	public function bulkStatus(
		array $dashboardUuids,
		BulkStatusRequest $request,
		string $userId,
	): array {
		$publicationStatus = $request->publicationStatus;
		$publishAt         = $request->publishAt;
		$dryRun            = $request->dryRun;
		$start = microtime(as_float: true);
		$this->assertPermissions(uuids: $dashboardUuids, userId: $userId);
		$this->assertWithinCap(uuids: $dashboardUuids);

		$resolvedPublishAt = $this->validatedPublishAt(publicationStatus: $publicationStatus, publishAt: $publishAt);

		$change = new BulkStatusChange(
			publicationStatus: $publicationStatus,
			publishAt: $resolvedPublishAt,
			now: (new DateTime())->format(format: 'Y-m-d H:i:s'),
			dryRun: $dryRun
		);
		$outcomes = [];
		foreach ($dashboardUuids as $uuid) {
			$outcomes[] = $this->statusOneDashboard(uuid: (string)$uuid, change: $change);
		}

		[$updated, $skipped, $errors] = (new BulkOutcome(countKey: 'updated'))->tally(outcomes: $outcomes);

		$payload = [
			$this->countKey(dryRun: $dryRun, real: 'updatedCount', preview: 'wouldUpdateCount') => $updated,
			$this->countKey(dryRun: $dryRun, real: 'skippedCount', preview: 'wouldSkipCount') => $skipped,
			'errors' => $errors,
			'dryRun' => $dryRun,
		];

		$run = ['dashboardUuids' => $dashboardUuids, 'userId' => $userId, 'start' => $start, 'dryRun' => $dryRun];
		$this->emitAuditEvent(operation: self::OP_STATUS, run: $run + ['extra' => ['publicationStatus' => $publicationStatus]]);

		return $payload;
	}//end bulkStatus()

	/**
	 * Re-index multiple dashboards for unified search (REQ-BULK-004).
	 *
	 * The unified-search integration is provided by a sibling capability;
	 * this service touches the dashboard's `updated_at` to mark the row
	 * dirty so any downstream search-indexer pipeline (cron / queue) will
	 * pick it up on its next pass. Per-uuid failures are reported in
	 * `errors` and the batch continues.
	 *
	 * @param string[] $dashboardUuids The dashboards to mark for reindex.
	 * @param string $userId The acting NC user ID.
	 * @param bool $dryRun When true, preview only.
	 *
	 * @return array<string, mixed> The result envelope.
	 *
	 * @throws InvalidArgumentException When the request exceeds the cap.
	 * @throws PermissionDeniedException When any UUID is unauthorised.
	 *
	 * @spec openspec/specs/dashboard-bulk-operations/spec.md
	 */
	public function bulkReindex(
		array $dashboardUuids,
		string $userId,
		bool $dryRun = false,
	): array {
		$start = microtime(as_float: true);
		$this->assertPermissions(uuids: $dashboardUuids, userId: $userId);
		$this->assertWithinCap(uuids: $dashboardUuids);

		$reindex = new BulkReindexRun();
		$reindex->now = (new DateTime())->format(format: 'Y-m-d H:i:s');
		$reindex->dryRun = $dryRun;
		$outcomes = [];
		foreach ($dashboardUuids as $uuid) {
			$outcomes[] = $this->reindexOneDashboard(uuid: (string)$uuid, reindex: $reindex);
		}

		[$reindexed, , $errors] = (new BulkOutcome(countKey: 'reindexed'))->tally(outcomes: $outcomes);

		$payload = [
			$this->countKey(dryRun: $dryRun, real: 'reindexedCount', preview: 'wouldReindexCount') => $reindexed,
			'errors' => $errors,
			'dryRun' => $dryRun,
		];

		$run = ['dashboardUuids' => $dashboardUuids, 'userId' => $userId, 'start' => $start, 'dryRun' => $dryRun];
		$this->emitAuditEvent(operation: self::OP_REINDEX, run: $run);

		return $payload;
	}//end bulkReindex()

	/**
	 * Resolve the per-request size cap from app config, falling back to
	 * the {@see self::DEFAULT_MAX_PER_REQUEST} when the configured value
	 * is missing, zero, or negative (REQ-BULK-006 fallback scenario).
	 *
	 * @return int The effective cap.
	 *
	 * @spec openspec/specs/dashboard-bulk-operations/spec.md
	 */
	public function getEffectiveCap(): int {
		$value = $this->access->appConfig->getValueInt(
			Application::APP_ID,
			self::CONFIG_KEY_MAX_PER_REQUEST,
			self::DEFAULT_MAX_PER_REQUEST
		);

		if ($value <= 0) {
			$this->logger->warning(
				message: 'Invalid bulk operation cap; falling back to default',
				context: ['raw' => (string)$value]
			);

			return self::DEFAULT_MAX_PER_REQUEST;
		}

		return $value;
	}//end getEffectiveCap()

	/**
	 * REQ-BULK-011 — all-or-nothing permission pre-check.
	 *
	 * The permission pre-check has higher priority than the size cap
	 * (REQ-BULK-011 scenario "Permission check happens before size
	 * validation"). Non-admins are rejected wholesale; for admins, every
	 * UUID is verified individually so a single unauthorised dashboard
	 * fails the batch.
	 *
	 * @param string[] $uuids The dashboard UUIDs in the batch.
	 * @param string $userId The acting NC user ID.
	 *
	 * @return void
	 *
	 * @throws PermissionDeniedException When the caller is not an NC
	 *                                   admin or any UUID is unauthorised.
	 */
	private function assertPermissions(array $uuids, string $userId): void {
		if ($this->access->groupManager->isAdmin(userId: $userId) === false) {
			throw new PermissionDeniedException(
				message: 'Administrator privileges required.',
				deniedUuids: []
			);
		}

		$denied = [];
		foreach ($uuids as $uuid) {
			$uuidString = (string)$uuid;
			try {
				$dashboard = $this->stores->dashboardMapper->findByUuid(uuid: $uuidString);
			} catch (DoesNotExistException) {
				// Missing rows are not a permission failure — they are
				// routed to the per-uuid `not_found`/`already_deleted`
				// handler downstream.
				continue;
			}

			$level = $this->access->permissionService->resolveAccessLevel(
				userId: $userId,
				dashboard: $dashboard
			);
			if ($level === null) {
				$denied[] = $uuidString;
			}
		}

		if ($denied !== []) {
			throw new PermissionDeniedException(
				message: 'Insufficient permissions on one or more dashboards.',
				deniedUuids: $denied
			);
		}
	}//end assertPermissions()

	/**
	 * Delete a single dashboard as part of a {@see self::bulkDelete()} batch.
	 *
	 * Extracted so the batch loop stays a pure accumulator: every branch
	 * that used to `continue` now returns an outcome triple instead. The
	 * `deleted`/`skipped` members are added to the batch counters and a
	 * non-NULL `error` is appended to the batch error list.
	 *
	 * @param string           $uuid   The dashboard UUID to delete.
	 * @param BulkDeleteRun $delete The delete of the batch.
	 *
	 * @return array{deleted: int, skipped: int, error: array<string, mixed>|null}
	 *
	 * @spec openspec/specs/dashboard-bulk-operations/spec.md
	 */
	private function deleteOneDashboard(string $uuid, BulkDeleteRun $delete): array {
		$outcome = new BulkOutcome(countKey: 'deleted');
		try {
			$dashboard = $this->stores->dashboardMapper->findByUuid(uuid: $uuid);
		} catch (DoesNotExistException) {
			// REQ-BULK-007: hard delete on a missing row is silent.
			return $outcome->skipped(error: $outcome->uuidError(uuid: $uuid, reason: self::REASON_ALREADY_DELETED));
		}

		$rowUuid = (string)$dashboard->getUuid();
		$childCount = $this->childCountOf(rowUuid: $rowUuid);
		if ($childCount > 0 && $delete->cascade === false) {
			return $outcome->skipped(error: $this->hasChildrenError(rowUuid: $rowUuid, childCount: $childCount));
		}

		if ($delete->dryRun === true) {
			return $outcome->counted(count: $this->previewDeleteCount(delete: $delete, childCount: $childCount));
		}

		try {
			return $outcome->counted(
				count: $this->performDelete(
					dashboard: $dashboard,
					userId: $delete->userId,
					cascade: $delete->cascade,
					childCount: $childCount
				)
			);
		} catch (Throwable $t) {
			return $this->deleteFailed(rowUuid: $rowUuid, failure: $t);
		}//end try
	}//end deleteOneDashboard()

	/**
	 * The per-uuid error for a dashboard that has children while cascading is off.
	 *
	 * @param string $rowUuid    The dashboard's UUID.
	 * @param int    $childCount Its direct child count.
	 *
	 * @return array{uuid: string, reason: string, childCount: int}
	 */
	private function hasChildrenError(string $rowUuid, int $childCount): array {
		return [
			'uuid' => $rowUuid,
			'reason' => DashboardHasChildrenException::ERROR_CODE,
			'childCount' => $childCount,
		];
	}//end hasChildrenError()

	/**
	 * Log a failed delete and answer its outcome.
	 *
	 * @param string    $rowUuid The dashboard's UUID.
	 * @param Throwable $failure The failure.
	 *
	 * @return array{deleted: int, skipped: int, error: array<string, mixed>}
	 */
	private function deleteFailed(string $rowUuid, Throwable $failure): array {
		$this->logger->error(
			message: 'BulkOperationService::bulkDelete failed for uuid',
			context: ['uuid' => $rowUuid, 'exception' => $failure]
		);

		$outcome = new BulkOutcome(countKey: 'deleted');
		return $outcome->failed(error: $outcome->transactionError(uuid: $rowUuid, failure: $failure));
	}//end deleteFailed()

	/**
	 * Re-parent a single dashboard as part of a {@see self::bulkMove()}
	 * batch.
	 *
	 * @param string         $uuid The dashboard UUID to move.
	 * @param BulkMoveRun $move The move of the batch.
	 *
	 * @return array{moved: int, skipped: int, error: array<string, mixed>|null}
	 *
	 * @spec openspec/specs/dashboard-bulk-operations/spec.md
	 */
	private function moveOneDashboard(string $uuid, BulkMoveRun $move): array {
		$outcome = new BulkOutcome(countKey: 'moved');
		try {
			$dashboard = $this->stores->dashboardMapper->findByUuid(uuid: $uuid);
		} catch (DoesNotExistException) {
			return $outcome->failed(error: $outcome->uuidError(uuid: $uuid, reason: self::REASON_NOT_FOUND));
		}

		if (($dashboard->getParentUuid() ?? '') === ($move->parentUuid ?? '')) {
			// REQ-BULK-007 idempotency: already at target parent.
			return $outcome->skipped(error: $outcome->uuidError(uuid: $uuid, reason: self::REASON_PARENT_ALREADY_MATCH));
		}

		$cycle = $this->cycleError(uuid: $uuid, parentUuid: $move->parentUuid);
		if ($cycle !== null) {
			return $outcome->failed(error: $cycle);
		}

		if ($move->dryRun === true) {
			return $outcome->counted(count: 1);
		}

		try {
			$dashboard->setParentUuid($move->parentUuid);
			$dashboard->setUpdatedAt($move->now);
			$this->stores->dashboardMapper->update(entity: $dashboard);

			return $outcome->counted(count: 1);
		} catch (Throwable $t) {
			$this->logger->error(
				message: 'BulkOperationService::bulkMove failed for uuid',
				context: ['uuid' => $uuid, 'exception' => $t]
			);

			return $outcome->failed(error: $outcome->transactionError(uuid: $uuid, failure: $t));
		}//end try
	}//end moveOneDashboard()

	/**
	 * Apply a publication-status change to a single dashboard as part of a
	 * {@see self::bulkStatus()} batch.
	 *
	 * @param string           $uuid   The dashboard UUID to mutate.
	 * @param BulkStatusChange $change The validated change of the batch.
	 *
	 * @return array{updated: int, skipped: int, error: array<string, mixed>|null}
	 *
	 * @spec openspec/specs/dashboard-bulk-operations/spec.md
	 */
	private function statusOneDashboard(string $uuid, BulkStatusChange $change): array {
		$outcome = new BulkOutcome(countKey: 'updated');
		try {
			$dashboard = $this->stores->dashboardMapper->findByUuid(uuid: $uuid);
		} catch (DoesNotExistException) {
			return $outcome->failed(error: $outcome->uuidError(uuid: $uuid, reason: self::REASON_NOT_FOUND));
		}

		// REQ-BULK-007 idempotency: already at target status.
		if ($this->statusAlreadyMatches(dashboard: $dashboard, change: $change) === true) {
			return $outcome->skipped(error: $outcome->uuidError(uuid: $uuid, reason: self::REASON_STATUS_ALREADY_MATCH));
		}

		if ($change->dryRun === true) {
			return $outcome->counted(count: 1);
		}

		try {
			$this->applyStatusChange(dashboard: $dashboard, change: $change);

			return $outcome->counted(count: 1);
		} catch (Throwable $t) {
			$this->logger->error(
				message: 'BulkOperationService::bulkStatus failed for uuid',
				context: ['uuid' => $uuid, 'exception' => $t]
			);

			return $outcome->failed(error: $outcome->transactionError(uuid: $uuid, failure: $t));
		}//end try
	}//end statusOneDashboard()

	/**
	 * Refuse a move under a parent dashboard that does not exist.
	 *
	 * @param string|null $parentUuid The new parent UUID (NULL or '' ⇒ root).
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When the parent does not exist.
	 */
	private function assertParentExists(string|null $parentUuid): void {
		if ($parentUuid === null || $parentUuid === '') {
			return;
		}

		try {
			$this->stores->dashboardMapper->findByUuid(uuid: $parentUuid);
		} catch (DoesNotExistException) {
			throw new InvalidArgumentException(
				message: DashboardTreeService::ERR_PARENT_NOT_FOUND
			);
		}
	}//end assertParentExists()

	/**
	 * Validate the target status and resolve its publish time (only `scheduled` has one).
	 *
	 * @param string      $publicationStatus The target status.
	 * @param string|null $publishAt         The requested ISO-8601 publish time.
	 *
	 * @return string|null The parsed publish time, or NULL.
	 *
	 * @throws InvalidArgumentException When the status is not in the enum, or a
	 *                                  `scheduled` status has no future publish time.
	 */
	private function validatedPublishAt(string $publicationStatus, string|null $publishAt): string|null {
		$allowed = [
			Dashboard::STATUS_DRAFT,
			Dashboard::STATUS_PUBLISHED,
			Dashboard::STATUS_SCHEDULED,
		];

		if (in_array(needle: $publicationStatus, haystack: $allowed, strict: true) === false) {
			throw new InvalidArgumentException(
				message: 'publicationStatus must be one of: draft, published, scheduled'
			);
		}

		if ($publicationStatus !== Dashboard::STATUS_SCHEDULED) {
			return null;
		}

		if ($publishAt === null || trim($publishAt) === '') {
			throw new InvalidArgumentException(
				message: 'publishAt is required when publicationStatus is "scheduled"'
			);
		}

		return $this->parseFutureDate(publishAt: $publishAt);
	}//end validatedPublishAt()

	/**
	 * Mark one dashboard dirty for re-indexing as part of a {@see self::bulkReindex()} batch.
	 *
	 * @param string         $uuid    The dashboard UUID.
	 * @param BulkReindexRun $reindex The reindex of the batch.
	 *
	 * @return array{reindexed: int, skipped: int, error: array<string, mixed>|null}
	 */
	private function reindexOneDashboard(string $uuid, BulkReindexRun $reindex): array {
		$outcome = new BulkOutcome(countKey: 'reindexed');
		try {
			$dashboard = $this->stores->dashboardMapper->findByUuid(uuid: $uuid);
		} catch (DoesNotExistException) {
			return $outcome->failed(error: $outcome->uuidError(uuid: $uuid, reason: self::REASON_NOT_FOUND));
		}

		if ($reindex->dryRun === true) {
			return $outcome->counted(count: 1);
		}

		try {
			// Mark the dashboard dirty so the unified-search provider
			// re-ingests it on the next pass. The provider lives in
			// a sibling capability; touching `updatedAt` is the
			// documented contract surface for "rebuild this row".
			$dashboard->setUpdatedAt($reindex->now);
			$this->stores->dashboardMapper->update(entity: $dashboard);
			return $outcome->counted(count: 1);
		} catch (Throwable $t) {
			$this->logger->error(
				message: 'BulkOperationService::bulkReindex failed for uuid',
				context: ['uuid' => $uuid, 'exception' => $t]
			);
			return $outcome->failed(
				error: [
					'uuid' => $uuid,
					'reason' => self::REASON_REINDEX_FAILED,
					'detail' => $t->getMessage(),
				]
			);
		}//end try
	}//end reindexOneDashboard()

	/**
	 * Whether the dashboard already has the target status (and, for
	 * `scheduled`, the target publish time).
	 *
	 * @param Dashboard        $dashboard The dashboard.
	 * @param BulkStatusChange $change    The change of the batch.
	 *
	 * @return bool
	 */
	private function statusAlreadyMatches(Dashboard $dashboard, BulkStatusChange $change): bool {
		$publicationStatus = $change->publicationStatus;
		return $dashboard->getPublicationStatus() === $publicationStatus
			&& ($publicationStatus !== Dashboard::STATUS_SCHEDULED
			|| $dashboard->getPublishAt() === $change->publishAt);
	}//end statusAlreadyMatches()

	/**
	 * The cycle / depth refusal for re-parenting, or NULL when the move is allowed.
	 *
	 * @param string      $uuid       The dashboard UUID to move.
	 * @param string|null $parentUuid The new parent UUID (NULL ⇒ root).
	 *
	 * @return array<string, mixed>|null The per-uuid error.
	 */
	private function cycleError(string $uuid, string|null $parentUuid): array|null {
		try {
			$this->stores->treeService->validateParent(
				movingUuid: $uuid,
				newParentUuid: $parentUuid
			);
		} catch (InvalidArgumentException $e) {
			return [
				'uuid' => $uuid,
				'reason' => self::REASON_CYCLE_DETECTED,
				'detail' => $e->getMessage(),
			];
		}

		return null;
	}//end cycleError()

	/**
	 * The dashboard's direct child count; 0 for a row without a UUID.
	 *
	 * @param string $rowUuid The dashboard's UUID.
	 *
	 * @return int
	 */
	private function childCountOf(string $rowUuid): int {
		if ($rowUuid === '') {
			return 0;
		}

		return $this->stores->dashboardMapper->countChildrenByParent(
			parentUuid: $rowUuid
		);
	}//end childCountOf()

	/**
	 * How many dashboards a dry-run delete would remove: the dashboard, plus
	 * its children when cascading.
	 *
	 * @param BulkDeleteRun $delete     The delete of the batch.
	 * @param int              $childCount The dashboard's direct child count.
	 *
	 * @return int
	 */
	private function previewDeleteCount(BulkDeleteRun $delete, int $childCount): int {
		if ($delete->cascade === true && $childCount > 0) {
			return ($childCount + 1);
		}

		return 1;
	}//end previewDeleteCount()

	/**
	 * Perform the actual delete for one dashboard, cascading when asked.
	 *
	 * A cascading delete of a dashboard that has children is delegated
	 * wholesale to {@see DashboardTreeService::deleteSubtree()}; every
	 * other case is a leaf delete that also drops the dashboard's
	 * placements and dispatches {@see DashboardDeletedEvent} so the
	 * REQ-CSC-001 cascade cleanup listeners fire.
	 *
	 * @param Dashboard $dashboard The dashboard to delete.
	 * @param string $userId The acting NC user ID.
	 * @param bool $cascade When true, recurse into children.
	 * @param int $childCount The dashboard's direct child count.
	 *
	 * @return int The number of dashboards actually deleted.
	 *
	 * @spec openspec/specs/dashboard-bulk-operations/spec.md
	 */
	private function performDelete(
		Dashboard $dashboard,
		string $userId,
		bool $cascade,
		int $childCount,
	): int {
		if ($cascade === true && $childCount > 0) {
			return $this->stores->treeService->deleteSubtree(dashboard: $dashboard);
		}

		$rowUuid = (string)$dashboard->getUuid();

		$this->stores->placementMapper->deleteByDashboardId(
			dashboardId: (int)$dashboard->getId()
		);
		$this->stores->dashboardMapper->delete(entity: $dashboard);

		// SB1 fix: dispatch DashboardDeletedEvent for cascade cleanup
		// (REQ-CSC-001).
		if ($this->stores->eventDispatcher !== null && $rowUuid !== '') {
			$this->stores->eventDispatcher->dispatchTyped(
				new DashboardDeletedEvent(
					dashboardUuid: $rowUuid,
					ownerUserId:   (string)($dashboard->getUserId() ?? $userId),
					type:          (string)($dashboard->getType() ?? Dashboard::TYPE_USER),
					deletedAt:     new DateTimeImmutable(),
					dashboardId:   (int)$dashboard->getId()
				)
			);
		}

		return 1;
	}//end performDelete()

	/**
	 * REQ-BULK-006 — enforce the per-request size cap.
	 *
	 * @param string[] $uuids The dashboard UUIDs.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When the batch exceeds the cap.
	 */
	private function assertWithinCap(array $uuids): void {
		$cap = $this->getEffectiveCap();
		if (count($uuids) > $cap) {
			throw new InvalidArgumentException(
				message: 'Request contains ' . count($uuids) . ' dashboards; maximum is ' . $cap . ' (configured by admin)'
			);
		}
	}//end assertWithinCap()

	/**
	 * Emit a single audit Activity event summarising the bulk operation
	 * (REQ-BULK-009).
	 *
	 * Audit events are fan-out via {@see ActivityPublisher::publishGlobal()}
	 * with a synthetic placeholder UUID — the dashboard count and
	 * operation type are the diagnostically interesting fields. Any
	 * Activity failure is swallowed by the publisher so an audit-log
	 * problem never rolls back the bulk operation.
	 *
	 * @param string               $operation The operation label.
	 * @param array<string, mixed> $run       The run: `dashboardUuids` (the batch), `userId` (the
	 *                                        acting NC user ID), `start` (microtime the run began),
	 *                                        `dryRun` and optional `extra` payload fields.
	 *
	 * @return void
	 */
	private function emitAuditEvent(string $operation, array $run): void {
		$dashboardCount = count($run['dashboardUuids']);
		$userId         = $run['userId'];
		$durationMs     = $this->elapsedMs(start: $run['start']);
		$dryRun         = $run['dryRun'];
		$extra          = $run['extra'] ?? [];
		try {
			$row = new ActivityRow(
				type: Extension::EVENT_UPDATED,
				actorUserId: $userId,
				dashboard: new ActivitySubject(
					uuid: 'bulk-' . $operation,
					name: 'Bulk ' . $operation . ' (' . $dashboardCount . ' dashboards)'
				),
				extraParams: array_merge(
					[
						'bulkOperation' => $operation,
						'dashboardCount' => $dashboardCount,
						'durationMs' => $durationMs,
						'dryRun' => $dryRun,
					],
					$extra
				)
			);
			$this->activityPublisher->publish(row: $row, recipientUserId: $userId);
		} catch (Throwable $t) {
			// Defensive: ActivityPublisher already swallows; this is a
			// belt-and-braces guard.
			$this->logger->warning(
				message: 'BulkOperationService audit event failed',
				context: ['operation' => $operation, 'exception' => $t]
			);
		}//end try
	}//end emitAuditEvent()

	/**
	 * Compute the elapsed time in milliseconds from a microtime(true)
	 * start marker.
	 *
	 * @param float $start The start marker (`microtime(true)`).
	 *
	 * @return int The elapsed milliseconds (>=0).
	 */
	private function elapsedMs(float $start): int {
		return (int)max(0, ((microtime(as_float: true) - $start) * 1000));
	}//end elapsedMs()

	/**
	 * Parse and validate a future ISO-8601 timestamp for the `scheduled`
	 * status. Mirrors {@see DashboardService::parseFuturePublishAt()} so
	 * the bulk path enforces the same contract as the per-dashboard
	 * publish/schedule path.
	 *
	 * @param string $publishAt The ISO-8601 timestamp.
	 *
	 * @return string The normalised `Y-m-d H:i:s` string.
	 *
	 * @throws InvalidArgumentException When the timestamp is empty,
	 *                                  unparseable, or in the past.
	 */
	private function parseFutureDate(string $publishAt): string {
		$trimmed = trim($publishAt);
		if ($trimmed === '') {
			throw new InvalidArgumentException(
				message: DashboardService::ERR_SCHEDULE_PAST_DATE
			);
		}

		try {
			$parsed = new DateTime($trimmed);
		} catch (\Exception) {
			throw new InvalidArgumentException(
				message: DashboardService::ERR_SCHEDULE_PAST_DATE
			);
		}

		if ($parsed <= new DateTime()) {
			throw new InvalidArgumentException(
				message: DashboardService::ERR_SCHEDULE_PAST_DATE
			);
		}

		return $parsed->format(format: 'Y-m-d H:i:s');
	}//end parseFutureDate()

	/**
	 * Apply the per-dashboard publication-status mutation.
	 *
	 * Encapsulates the per-status field bookkeeping:
	 * - PUBLISHED: stamps `publishedAt` on first publish, clears `publishAt`.
	 * - DRAFT: clears `publishAt`.
	 * - SCHEDULED: stores the parsed `publishAt`.
	 *
	 * @param Dashboard        $dashboard The dashboard to mutate.
	 * @param BulkStatusChange $change    Target status, parsed publishAt
	 *                                    (for STATUS_SCHEDULED) and the
	 *                                    batch timestamp.
	 *
	 * @return void
	 */
	private function applyStatusChange(Dashboard $dashboard, BulkStatusChange $change): void {
		$publicationStatus = $change->publicationStatus;
		$now = $change->now;
		$dashboard->setPublicationStatus($publicationStatus);

		if ($publicationStatus === Dashboard::STATUS_PUBLISHED) {
			if ($dashboard->getPublishedAt() === null) {
				$dashboard->setPublishedAt($now);
			}

			$dashboard->setPublishAt(null);
		}

		if ($publicationStatus === Dashboard::STATUS_DRAFT) {
			$dashboard->setPublishAt(null);
		}

		if ($publicationStatus !== Dashboard::STATUS_PUBLISHED
			&& $publicationStatus !== Dashboard::STATUS_DRAFT
		) {
			// STATUS_SCHEDULED.
			$dashboard->setPublishAt($change->publishAt);
		}

		$dashboard->setUpdatedAt($now);
		$this->stores->dashboardMapper->update(entity: $dashboard);
	}//end applyStatusChange()

	/**
	 * Resolve the response counter key for the current run mode.
	 *
	 * The bulk endpoints expose two parallel counter naming schemes:
	 * `deletedCount`/`movedCount`/etc. for real runs, `wouldDeleteCount`
	 * /`wouldMoveCount`/etc. for dry runs. This helper centralises the
	 * chooser so the per-operation `$payload` arrays stay free of inline
	 * ternaries (the project's PHPCS rules disallow them).
	 *
	 * @param bool $dryRun True when the operation is a dry-run preview.
	 * @param string $real The counter name for real runs.
	 * @param string $preview The counter name for dry-run previews.
	 *
	 * @return string The chosen key.
	 */
	private function countKey(bool $dryRun, string $real, string $preview): string {
		if ($dryRun === true) {
			return $preview;
		}

		return $real;
	}//end countKey()
}//end class
