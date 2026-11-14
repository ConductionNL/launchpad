<?php

/**
 * RetentionJanitor
 *
 * Retention of scheduled exports (tasks.md task 9, REQ-SCH-007): after
 * `keepArtefactsForDays` a run's files are deleted from Files and their
 * `storagePath` nulled, the hashes and delivery outcomes kept; after
 * `keepAuditForDays` the run-row itself is deleted through OpenRegister's
 * standard delete, its remaining files with it. An explicit null keeps
 * forever; a missing value takes the schema default (30 and 365 days).
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

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IRootFolder;
use Throwable;

/**
 * Purges expired artefacts and run-rows.
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-007-retention-policy-enforced-by-janitor
 * @SuppressWarnings(PHPMD.CyclomaticComplexity) sweep() applies both retention periods to every row in one pass.
 */
class RetentionJanitor {

	public const DEFAULT_ARTEFACT_DAYS = 30;

	public const DEFAULT_AUDIT_DAYS = 365;

	/**
	 * Constructor.
	 *
	 * @param ExportObjectStore $store      Reads each run's export for its retention.
	 * @param AuditLog          $log        The run-rows.
	 * @param IRootFolder       $rootFolder Deletes the artefact files.
	 * @param ITimeFactory      $time       The clock.
	 */
	public function __construct(
		private readonly ExportObjectStore $store,
		private readonly AuditLog $log,
		private readonly IRootFolder $rootFolder,
		private readonly ITimeFactory $time,
	) {
	}//end __construct()

	/**
	 * One pass over every run-row.
	 *
	 * @return array{artefactsPurged: int, runsDeleted: int}
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-9
	 */
	public function sweep(): array {
		$now     = $this->time->now()->getTimestamp();
		$exports = [];
		$purged  = 0;
		$deleted = 0;
		foreach ($this->log->allRuns() as $run) {
			$started = strtotime((string)($run['startedAt'] ?? ''));
			if ($started === false || ($run['status'] ?? null) === 'running') {
				continue;
			}

			$exportId             = (string)($run['exportId'] ?? '');
			$exports[$exportId] ??= $this->store->find(schema: ExportObjectStore::SCHEMA_EXPORT, uuid: $exportId, system: true) ?? [];
			$retention            = (array)($exports[$exportId]['retention'] ?? []);
			$ageDays              = ($now - $started) / 86400;

			$auditDays = $this->days(retention: $retention, key: 'keepAuditForDays', default: self::DEFAULT_AUDIT_DAYS);
			if ($auditDays !== null && $ageDays > $auditDays) {
				$this->deleteFiles(run: $run);
				$deleted += (int)$this->log->delete(runId: (string)($run['id'] ?? ''));
				continue;
			}

			$artefactDays = $this->days(retention: $retention, key: 'keepArtefactsForDays', default: self::DEFAULT_ARTEFACT_DAYS);
			if ($artefactDays !== null && $ageDays > $artefactDays && $this->deleteFiles(run: $run) > 0) {
				foreach (array_keys((array)$run['renderArtefacts']) as $i) {
					$run['renderArtefacts'][$i]['storagePath'] = null;
				}

				$this->log->update(run: $run);
				$purged++;
			}
		}//end foreach

		return ['artefactsPurged' => $purged, 'runsDeleted' => $deleted];
	}//end sweep()

	/**
	 * A retention period: the value when it is a positive integer, null when explicitly null (forever), else the default.
	 *
	 * @param array<string, mixed> $retention The export's retention.
	 * @param string               $key       The setting.
	 * @param int                  $default   Its default.
	 *
	 * @return int|null
	 */
	private function days(array $retention, string $key, int $default): ?int {
		if (array_key_exists($key, $retention) === true && $retention[$key] === null) {
			return null;
		}

		if (is_int($retention[$key] ?? null) === true && $retention[$key] >= 1) {
			return $retention[$key];
		}

		return $default;
	}//end days()

	/**
	 * Delete a run's stored files; a file already gone counts as deleted.
	 *
	 * @param array<string, mixed> $run The run-row.
	 *
	 * @return int How many paths were cleared.
	 */
	private function deleteFiles(array $run): int {
		$cleared = 0;
		foreach ((array)($run['renderArtefacts'] ?? []) as $artefact) {
			$path = null;
			if (is_array($artefact) === true) {
				$path = $artefact['storagePath'] ?? null;
			}

			if (is_string($path) === false || $path === '') {
				continue;
			}

			try {
				$this->rootFolder->get($path)->delete();
			} catch (Throwable) {
				// Gone already, or not deletable: the path is cleared either way.
				unset($path);
			}

			$cleared++;
		}

		return $cleared;
	}//end deleteFiles()
}//end class
