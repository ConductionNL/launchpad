<?php

/**
 * ScheduledExportJanitor
 *
 * Hourly retention of scheduled exports (Task 9, REQ-SCH-007): artefact
 * files older than the export's `retention.keepArtefactsForDays` are deleted
 * from the owner's Files area and their `storagePath` nulled on the run-row,
 * which keeps its hashes and delivery outcomes; run-rows older than
 * `retention.keepAuditForDays` are deleted with any files they still hold.
 * A null retention keeps that part forever. A run that is still running is
 * left alone.
 *
 * Run-rows are deleted through OpenRegister's ObjectService, so they follow
 * its soft-delete lifecycle like any other object.
 *
 * @category  BackgroundJob
 * @package   OCA\LaunchPad\BackgroundJob
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-9
 */

declare(strict_types=1);

namespace OCA\LaunchPad\BackgroundJob;

use DateTimeImmutable;
use DateTimeInterface;
use OCA\LaunchPad\Service\ScheduledExportArtefactStorage;
use OCA\LaunchPad\Service\ScheduledExportAuditLog;
use OCA\LaunchPad\Service\ScheduledExportService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Deletes expired export artefacts and run-rows.
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-007-retention-policy-enforced-by-janitor
 */
class ScheduledExportJanitor extends TimedJob {

	public const INTERVAL_SECONDS = 3600;

	/**
	 * Retention of an export that sets none (design, seed data defaults).
	 *
	 * @var array{keepArtefactsForDays: int, keepAuditForDays: int}
	 */
	public const DEFAULT_RETENTION = ['keepArtefactsForDays' => 30, 'keepAuditForDays' => 365];

	/**
	 * Constructor.
	 *
	 * @param ITimeFactory                   $time      The clock.
	 * @param ScheduledExportService         $exports   Reads each run's export for its retention.
	 * @param ScheduledExportAuditLog        $auditLog  Reads, saves and deletes run-rows.
	 * @param ScheduledExportArtefactStorage $artefacts Deletes artefact files.
	 * @param LoggerInterface                $logger    Logs a run-row that could not be cleaned.
	 */
	public function __construct(
		ITimeFactory $time,
		private readonly ScheduledExportService $exports,
		private readonly ScheduledExportAuditLog $auditLog,
		private readonly ScheduledExportArtefactStorage $artefacts,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(time: $time);
		$this->setInterval(seconds: self::INTERVAL_SECONDS);
	}//end __construct()

	/**
	 * Run the retention pass.
	 *
	 * @param mixed $argument Unused.
	 *
	 * @return void
	 */
	protected function run($argument): void {
		$this->purge(now: $this->time->getDateTime());
	}//end run()

	/**
	 * Apply every export's retention to its run-rows.
	 *
	 * @param DateTimeInterface $now The moment ages are measured from.
	 *
	 * @return array{artefacts: int, runs: int} How many artefact files and run-rows were deleted.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-007-retention-policy-enforced-by-janitor
	 */
	public function purge(DateTimeInterface $now): array {
		$deleted = ['artefacts' => 0, 'runs' => 0];
		$exports = [];
		foreach ($this->auditLog->allRuns() as $run) {
			if (($run['status'] ?? null) === ScheduledExportAuditLog::STATUS_RUNNING) {
				continue;
			}

			$started = ScheduledExportService::parseTime(value: $run['startedAt'] ?? null);
			if ($started === null) {
				continue;
			}

			$exportId = (string)($run['exportId'] ?? '');
			if (array_key_exists($exportId, $exports) === false) {
				$exports[$exportId] = $this->exports->findForRun(id: $exportId);
			}

			try {
				$counts = $this->apply(run: $run, export: $exports[$exportId], ageDays: self::ageDays(from: $started, to: $now));
			} catch (Throwable $e) {
				$this->logger->warning(
					message: 'LaunchPad: retention of export run ' . (string)($run['id'] ?? '') . ' failed: ' . $e->getMessage(),
					context: ['app' => 'launchpad']
				);
				continue;
			}

			$deleted['artefacts'] += $counts['artefacts'];
			$deleted['runs']      += $counts['runs'];
		}//end foreach

		return $deleted;
	}//end purge()

	/**
	 * One run-row's retention.
	 *
	 * @param array<string, mixed>      $run     The run-row.
	 * @param array<string, mixed>|null $export  Its export, or null when the export is gone.
	 * @param int                       $ageDays Whole days since the run started.
	 *
	 * @return array{artefacts: int, runs: int}
	 */
	private function apply(array $run, ?array $export, int $ageDays): array {
		$retention = self::retentionOf(export: $export);
		$owner     = (string)($export['@self']['owner'] ?? $run['@self']['owner'] ?? '');
		$keepAudit = $retention['keepAuditForDays'];
		if ($keepAudit !== null && $ageDays > $keepAudit) {
			$files = $this->deleteFiles(run: $run, owner: $owner)['deleted'];
			$this->auditLog->deleteRun(runId: (string)$run['id']);
			return ['artefacts' => $files, 'runs' => 1];
		}

		$keepFiles = $retention['keepArtefactsForDays'];
		if ($keepFiles === null || $ageDays <= $keepFiles) {
			return ['artefacts' => 0, 'runs' => 0];
		}

		$result = $this->deleteFiles(run: $run, owner: $owner);
		if ($result['deleted'] > 0) {
			$this->auditLog->write(run: $result['run']);
		}

		return ['artefacts' => $result['deleted'], 'runs' => 0];
	}//end apply()

	/**
	 * Delete a run-row's artefact files and null their paths; hashes stay.
	 *
	 * @param array<string, mixed> $run   The run-row.
	 * @param string               $owner The export owner.
	 *
	 * @return array{run: array<string, mixed>, deleted: int}
	 */
	private function deleteFiles(array $run, string $owner): array {
		$deleted   = 0;
		$artefacts = (array)($run['renderArtefacts'] ?? []);
		foreach ($artefacts as $index => $artefact) {
			if (is_array($artefact) === false) {
				continue;
			}

			$path = $artefact['storagePath'] ?? null;
			if (is_string($path) === false || $path === '') {
				continue;
			}

			if ($this->artefacts->delete(owner: $owner, path: $path) === true) {
				$artefacts[$index]['storagePath'] = null;
				$deleted++;
			}
		}

		$run['renderArtefacts'] = $artefacts;
		return ['run' => $run, 'deleted' => $deleted];
	}//end deleteFiles()

	/**
	 * An export's retention over the defaults; an explicit null is forever.
	 *
	 * @param array<string, mixed>|null $export The export.
	 *
	 * @return array{keepArtefactsForDays: int|null, keepAuditForDays: int|null}
	 */
	private static function retentionOf(?array $export): array {
		$retention = self::DEFAULT_RETENTION;
		$set       = [];
		if (is_array($export['retention'] ?? null) === true) {
			$set = $export['retention'];
		}

		foreach (array_keys($retention) as $key) {
			if (array_key_exists($key, $set) === false) {
				continue;
			}

			if ($set[$key] === null) {
				$retention[$key] = null;
			} elseif (is_int($set[$key]) === true && $set[$key] >= 0) {
				$retention[$key] = $set[$key];
			}
		}

		return $retention;
	}//end retentionOf()

	/**
	 * Whole days between two moments.
	 *
	 * @param DateTimeImmutable $from The earlier moment.
	 * @param DateTimeInterface $to   The later moment.
	 *
	 * @return int
	 */
	private static function ageDays(DateTimeImmutable $from, DateTimeInterface $to): int {
		return intdiv(max(0, $to->getTimestamp() - $from->getTimestamp()), 86400);
	}//end ageDays()
}//end class
