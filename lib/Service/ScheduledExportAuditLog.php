<?php

/**
 * ScheduledExportAuditLog
 *
 * The audit trail of scheduled exports (Task 7, REQ-SCH-008): one
 * `scheduled_export_run` object in OpenRegister per fire, manual or
 * scheduled, holding the render artefacts (format, size, sha256, storage
 * path) and one delivery entry per recipient (channel, attempt count,
 * status, provider message id, error).
 *
 * Every change is written at once, so a run that crashes half-way still
 * shows what was rendered and which deliveries were attempted. Writes run
 * without a user's access rules (the runner has no session); the reads for
 * the run-history UI apply them.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-7
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Run-rows of scheduled exports in OpenRegister.
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-008-audit-row-per-run-with-hash-and-delivery-outcome
 */
class ScheduledExportAuditLog {

	public const STATUS_RUNNING = 'running';

	public const TRIGGER_SCHEDULE = 'schedule';

	public const TRIGGER_MANUAL = 'manual';

	/**
	 * Constructor.
	 *
	 * @param ScheduledExportStore $store OpenRegister reads and writes.
	 */
	public function __construct(
		private readonly ScheduledExportStore $store,
	) {
	}//end __construct()

	/**
	 * Open the run-row of a fire.
	 *
	 * @param string            $exportId      The export's uuid.
	 * @param string            $triggeredBy   `schedule` or `manual`.
	 * @param string|null       $user          Who pressed "Run now", for a manual run.
	 * @param string            $correlationId Shared by every artefact and delivery of this fire.
	 * @param DateTimeInterface $now           The moment of the fire.
	 *
	 * @return array<string, mixed> The saved run-row.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-008-audit-row-per-run-with-hash-and-delivery-outcome
	 */
	public function start(string $exportId, string $triggeredBy, ?string $user, string $correlationId, DateTimeInterface $now): array {
		$run = [
			'exportId' => $exportId,
			'startedAt' => self::atom(moment: $now),
			'status' => self::STATUS_RUNNING,
			'triggeredBy' => $triggeredBy,
			'renderArtefacts' => [],
			'deliveries' => [],
			'correlationId' => $correlationId,
		];
		if ($triggeredBy === self::TRIGGER_MANUAL && $user !== null) {
			$run['triggeredByUser'] = $user;
		}

		return $this->store->save(schema: ScheduledExportStore::SCHEMA_RUN, data: $run, rbac: false);
	}//end start()

	/**
	 * Add render artefacts to a run-row.
	 *
	 * @param array<string, mixed>       $run       The run-row.
	 * @param list<array<string, mixed>> $artefacts Each `{format, sizeBytes, sha256, storagePath, ...}`.
	 *
	 * @return array<string, mixed> The saved run-row.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-008-audit-row-per-run-with-hash-and-delivery-outcome
	 */
	public function addArtefacts(array $run, array $artefacts): array {
		$run['renderArtefacts'] = array_values(array_merge((array)($run['renderArtefacts'] ?? []), $artefacts));
		return $this->write(run: $run);
	}//end addArtefacts()

	/**
	 * Record a delivery: it replaces the entry of the same recipient and
	 * channel, or is appended. Written at once (Task 5).
	 *
	 * @param array<string, mixed> $run      The run-row.
	 * @param array<string, mixed> $delivery The delivery, with `recipientId` and `channel`.
	 *
	 * @return array<string, mixed> The saved run-row.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-008-audit-row-per-run-with-hash-and-delivery-outcome
	 */
	public function recordDelivery(array $run, array $delivery): array {
		$deliveries = array_values((array)($run['deliveries'] ?? []));
		$replaced   = false;
		foreach ($deliveries as $index => $existing) {
			if (self::sameDelivery(a: (array)$existing, b: $delivery) === true) {
				$deliveries[$index] = $delivery;
				$replaced           = true;
				break;
			}
		}

		if ($replaced === false) {
			$deliveries[] = $delivery;
		}

		$run['deliveries'] = $deliveries;
		return $this->write(run: $run);
	}//end recordDelivery()

	/**
	 * Close a run-row with its status.
	 *
	 * @param array<string, mixed> $run    The run-row.
	 * @param string               $status `success`, `partial`, `failed`, or `running` while retries are pending.
	 * @param DateTimeInterface    $now    The moment it finished.
	 * @param string|null          $reason Why it ended this way, for a skipped or failed fire.
	 *
	 * @return array<string, mixed> The saved run-row.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-008-audit-row-per-run-with-hash-and-delivery-outcome
	 */
	public function finish(array $run, string $status, DateTimeInterface $now, ?string $reason=null): array {
		$run['status'] = $status;
		if ($status !== self::STATUS_RUNNING) {
			$run['finishedAt'] = self::atom(moment: $now);
		}

		if ($reason !== null) {
			$run['reason'] = $reason;
		}

		return $this->write(run: $run);
	}//end finish()

	/**
	 * Save a run-row as it is now (the janitor's and the retry's write).
	 *
	 * @param array<string, mixed> $run The run-row, with its `id`.
	 *
	 * @return array<string, mixed> The saved run-row.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-7
	 */
	public function write(array $run): array {
		$id = null;
		if (isset($run['id']) === true) {
			$id = (string)$run['id'];
		}

		return $this->store->save(schema: ScheduledExportStore::SCHEMA_RUN, data: $run, uuid: $id, rbac: false);
	}//end write()

	/**
	 * The run-rows of an export the user may see, newest first.
	 *
	 * @param string $exportId The export's uuid.
	 * @param int    $limit    At most this many.
	 * @param int    $offset   Skip this many.
	 *
	 * @return list<array<string, mixed>>
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-008-audit-row-per-run-with-hash-and-delivery-outcome
	 */
	public function runsOf(string $exportId, int $limit=50, int $offset=0): array {
		$runs = $this->store->list(schema: ScheduledExportStore::SCHEMA_RUN, filters: ['exportId' => $exportId]);
		usort(
			$runs,
			static fn (array $a, array $b): int => strcmp((string)($b['startedAt'] ?? ''), (string)($a['startedAt'] ?? ''))
		);

		return array_slice($runs, max(0, $offset), max(0, $limit));
	}//end runsOf()

	/**
	 * One run-row of an export the user may see; null when it is not that
	 * export's or not visible.
	 *
	 * @param string $exportId The export's uuid.
	 * @param string $runId    The run-row's uuid.
	 * @param bool   $rbac     Apply the signed-in user's access rules.
	 *
	 * @return array<string, mixed>|null
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-008-audit-row-per-run-with-hash-and-delivery-outcome
	 */
	public function runOf(string $exportId, string $runId, bool $rbac=true): ?array {
		$run = $this->store->find(schema: ScheduledExportStore::SCHEMA_RUN, uuid: $runId, rbac: $rbac);
		if ($run === null || ($run['exportId'] ?? null) !== $exportId) {
			return null;
		}

		return $run;
	}//end runOf()

	/**
	 * Whether an earlier fire of the export is still running.
	 *
	 * @param string $exportId The export's uuid.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-002-background-runner-fires-schedules-without-an-active-browser-session
	 */
	public function isRunning(string $exportId): bool {
		$runs = $this->store->list(
			schema: ScheduledExportStore::SCHEMA_RUN,
			filters: ['exportId' => $exportId, 'status' => self::STATUS_RUNNING],
			rbac: false
		);
		foreach ($runs as $run) {
			if (($run['status'] ?? null) === self::STATUS_RUNNING && ($run['triggeredBy'] ?? null) === self::TRIGGER_SCHEDULE) {
				return true;
			}
		}

		return false;
	}//end isRunning()

	/**
	 * Every run-row still waiting for a delivery retry or holding
	 * artefacts, for the runner and the janitor; read without access rules.
	 *
	 * @param array<string, mixed> $filters Equality filters.
	 *
	 * @return list<array<string, mixed>>
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-7
	 */
	public function allRuns(array $filters=[]): array {
		return $this->store->list(schema: ScheduledExportStore::SCHEMA_RUN, filters: $filters, rbac: false);
	}//end allRuns()

	/**
	 * Delete a run-row (retention).
	 *
	 * @param string $runId The run-row's uuid.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-007-retention-policy-enforced-by-janitor
	 */
	public function deleteRun(string $runId): bool {
		return $this->store->delete(schema: ScheduledExportStore::SCHEMA_RUN, uuid: $runId, rbac: false);
	}//end deleteRun()

	/**
	 * Whether two deliveries are the same recipient on the same channel.
	 *
	 * @param array<string, mixed> $a One delivery.
	 * @param array<string, mixed> $b The other.
	 *
	 * @return bool
	 */
	private static function sameDelivery(array $a, array $b): bool {
		return ($a['recipientId'] ?? null) === ($b['recipientId'] ?? null)
			&& ($a['channel'] ?? null) === ($b['channel'] ?? null)
			&& ($a['artefactKey'] ?? null) === ($b['artefactKey'] ?? null);
	}//end sameDelivery()

	/**
	 * A moment as an ATOM timestamp.
	 *
	 * @param DateTimeInterface $moment The moment.
	 *
	 * @return string
	 */
	private static function atom(DateTimeInterface $moment): string {
		return DateTimeImmutable::createFromInterface($moment)->format(DateTimeInterface::ATOM);
	}//end atom()
}//end class
