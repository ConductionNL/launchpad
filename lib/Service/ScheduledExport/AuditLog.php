<?php

/**
 * AuditLog
 *
 * The `scheduled_export_run` rows (tasks.md task 7, REQ-SCH-008): one per
 * fire, manual or scheduled, holding what was rendered (format, size,
 * sha256, path) and how each delivery went (channel, attempts, status,
 * provider message id, error). Rows are OpenRegister objects, so the
 * standard permission model and retention apply to them.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\ScheduledExport
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

namespace OCA\LaunchPad\Service\ScheduledExport;

use DateTimeInterface;
use OCA\LaunchPad\Service\RetryHandler;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Security\ISecureRandom;

/**
 * Writes and reads the audit row of each fire.
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-008-audit-row-per-run-with-hash-and-delivery-outcome
 */
class AuditLog {

	/**
	 * Constructor.
	 *
	 * @param ExportObjectStore $store  The OpenRegister object store.
	 * @param RetryHandler      $retry  Rolls the deliveries up into the run status.
	 * @param ITimeFactory      $time   The clock.
	 * @param ISecureRandom     $random Makes the correlation id.
	 */
	public function __construct(
		private readonly ExportObjectStore $store,
		private readonly RetryHandler $retry,
		private readonly ITimeFactory $time,
		private readonly ISecureRandom $random,
	) {
	}//end __construct()

	/**
	 * Open the row of a fire, `running`, before anything is rendered.
	 *
	 * @param array<string, mixed> $export The export being fired.
	 * @param FireTrigger          $trigger     Who or what fired it.
	 *
	 * @return array<string, mixed> The saved row.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-7
	 */
	public function start(array $export, FireTrigger $trigger): array {
		$row = $this->fresh(export: $export, trigger: $trigger) + ['status' => 'running'];
		return $this->store->save(schema: ExportObjectStore::SCHEMA_RUN, data: $row, system: true);
	}//end start()

	/**
	 * Close a row with its artefacts and deliveries; the status is the roll-up
	 * of the deliveries, and `finishedAt` is set once nothing is left to retry.
	 *
	 * @param array<string, mixed>       $run        The row from start().
	 * @param list<array<string, mixed>> $artefacts  `{format, sizeBytes, sha256, storagePath}` per artefact.
	 * @param list<array<string, mixed>> $deliveries One entry per recipient delivery.
	 *
	 * @return array<string, mixed> The saved row.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-7
	 */
	public function record(array $run, array $artefacts, array $deliveries): array {
		$run['renderArtefacts'] = array_values($artefacts);
		$run['deliveries']      = array_values($deliveries);
		$run['status']          = $this->retry->rollUp(deliveries: $run['deliveries']);
		if ($run['status'] !== 'running') {
			$run['finishedAt'] = $this->now();
		}

		return $this->store->save(schema: ExportObjectStore::SCHEMA_RUN, data: $run, system: true);
	}//end record()

	/**
	 * Write the row of a fire that was skipped without rendering (REQ-SCH-002).
	 *
	 * @param array<string, mixed> $export The export.
	 * @param FireTrigger          $trigger     Who or what fired it.
	 * @param string               $status `partial` for an overlap, `failed` for a blocked creator.
	 * @param string               $reason Why it was skipped.
	 *
	 * @return array<string, mixed> The saved row.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-002-background-runner-fires-schedules-without-an-active-browser-session
	 */
	public function skipped(array $export, FireTrigger $trigger, string $status, string $reason): array {
		$row = $this->fresh(export: $export, trigger: $trigger) + [
			'status' => $status,
			'finishedAt' => $this->now(),
			'skipReason' => $reason,
			'renderArtefacts' => [],
			'deliveries' => [],
		];

		return $this->store->save(schema: ExportObjectStore::SCHEMA_RUN, data: $row, system: true);
	}//end skipped()

	/**
	 * The rows of one export, newest first.
	 *
	 * @param string $exportId The export uuid.
	 *
	 * @return list<array<string, mixed>>
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-7
	 */
	public function runsOf(string $exportId): array {
		$runs = $this->store->findAll(schema: ExportObjectStore::SCHEMA_RUN, filters: ['exportId' => $exportId], system: true);
		$runs = array_values(array_filter($runs, static fn (array $run): bool => ($run['exportId'] ?? null) === $exportId));
		usort($runs, static fn (array $a, array $b): int => strcmp((string)($b['startedAt'] ?? ''), (string)($a['startedAt'] ?? '')));

		return $runs;
	}//end runsOf()

	/**
	 * Every row, for the janitor and the retry sweep.
	 *
	 * @return list<array<string, mixed>>
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-7
	 */
	public function allRuns(): array {
		return $this->store->findAll(schema: ExportObjectStore::SCHEMA_RUN, system: true);
	}//end allRuns()

	/**
	 * One row of an export, or null when it is not a row of that export.
	 *
	 * @param string $exportId The export uuid.
	 * @param string $runId    The row uuid.
	 *
	 * @return array<string, mixed>|null
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-7
	 */
	public function runOf(string $exportId, string $runId): ?array {
		$run = $this->store->find(schema: ExportObjectStore::SCHEMA_RUN, uuid: $runId, system: true);
		if ($run === null || ($run['exportId'] ?? null) !== $exportId) {
			return null;
		}

		return $run;
	}//end runOf()

	/**
	 * Save a row as it is (the janitor nulling paths, the retry sweep).
	 *
	 * @param array<string, mixed> $run The row.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-7
	 */
	public function update(array $run): array {
		return $this->store->save(schema: ExportObjectStore::SCHEMA_RUN, data: $run, system: true);
	}//end update()

	/**
	 * Delete a row (the janitor, after `keepAuditForDays`).
	 *
	 * @param string $runId The row uuid.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-7
	 */
	public function delete(string $runId): bool {
		return $this->store->delete(uuid: $runId, system: true);
	}//end delete()

	/**
	 * The fields every new row starts with.
	 *
	 * @param array<string, mixed> $export The export.
	 * @param FireTrigger          $trigger     Who or what fired it.
	 *
	 * @return array<string, mixed>
	 */
	private function fresh(array $export, FireTrigger $trigger): array {
		return [
			'exportId' => (string)($export['id'] ?? ''),
			'startedAt' => $this->now(),
			'triggeredBy' => $trigger->kind,
			'triggeredByUser' => $trigger->userId,
			'correlationId' => $this->uuid(),
		];
	}//end fresh()

	/**
	 * Now, as an ATOM string.
	 *
	 * @return string
	 */
	private function now(): string {
		return $this->time->now()->format(DateTimeInterface::ATOM);
	}//end now()

	/**
	 * A random version-4 uuid.
	 *
	 * @return string
	 */
	private function uuid(): string {
		$hex = $this->random->generate(32, '0123456789abcdef');
		$hex = substr_replace($hex, '4', 12, 1);
		$hex = substr_replace($hex, '89ab'[hexdec($hex[16]) % 4], 16, 1);

		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4));
	}//end uuid()
}//end class
