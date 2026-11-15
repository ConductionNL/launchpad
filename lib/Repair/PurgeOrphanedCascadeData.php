<?php

/**
 * PurgeOrphanedCascadeData
 *
 * One-time repair step that removes orphaned rows left by pre-C4 stub
 * listeners. Runs on post-migration so existing installs are cleaned up
 * without manual intervention (REQ-CSC-003, REQ-CLN-001).
 *
 * Tables cleaned:
 *   - oc_launchpad_widget_placements   (dashboard_id no longer in dashboards)
 *   - oc_launchpad_dashboard_locks     (dashboard_uuid no longer in dashboards)
 *   - oc_launchpad_meta_values         (dashboard_uuid no longer in dashboards)
 *   - oc_launchpad_dash_translations   (dashboard_uuid no longer in dashboards)
 *   - oc_launchpad_dashboard_views     (dashboard_uuid no longer in dashboards)
 *   - oc_launchpad_dashboard_shares    (dashboard_id no longer in dashboards)
 *   - oc_launchpad_dash_versions  (dashboard_uuid no longer in dashboards)
 *
 * @category Repair
 * @package  OCA\LaunchPad\Repair
 *
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

namespace OCA\LaunchPad\Repair;

use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Purge orphaned cascade-data rows left by pre-C4 stub listeners.
 *
 * @spec openspec/specs/dashboard-cascade-events/spec.md
 */
class PurgeOrphanedCascadeData implements IRepairStep {
	/**
	 * Constructor.
	 *
	 * @param IDBConnection $db The database connection.
	 * @param LoggerInterface $logger PSR-3 logger.
	 */
	public function __construct(
		private readonly IDBConnection $db,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Repair-step name.
	 *
	 * @return string
	 *
	 * @spec openspec/specs/dashboard-cascade-events/spec.md
	 */
	public function getName(): string {
		return 'Purge orphaned cascade data from deleted dashboards (C4/REQ-CSC-003)';
	}//end getName()

	/**
	 * Delete orphaned rows from all cascade tables.
	 *
	 * Each DELETE uses a NOT IN subquery against oc_launchpad_dashboards so
	 * only rows whose parent no longer exists are removed. Failures per
	 * table are caught and logged without stopping the overall step.
	 *
	 * @param IOutput $output Repair output channel.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboard-cascade-events/spec.md
	 */
	public function run(IOutput $output): void {
		$output->info('launchpad: purging orphaned cascade data…');

		$total = 0;

		// Each table's foreign key references dashboards.uuid or dashboards.id.
		$targets = [
			['launchpad_dashboard_locks', 'dashboard_uuid', 'uuid'],
			['launchpad_meta_values', 'dashboard_uuid', 'uuid'],
			['launchpad_dash_translations', 'dashboard_uuid', 'uuid'],
			['launchpad_dashboard_views', 'dashboard_uuid', 'uuid'],
			['launchpad_dash_versions', 'dashboard_uuid', 'uuid'],
			['launchpad_widget_placements', 'dashboard_id', 'id'],
			['launchpad_dashboard_shares', 'dashboard_id', 'id'],
		];

		foreach ($targets as $target) {
			$total += $this->purgeOrphans(output: $output, target: $target);
		}

		$output->info(
			sprintf(
				'launchpad: orphaned cascade purge complete — %d row(s) removed.',
				$total
			)
		);
	}//end run()

	/**
	 * Delete the rows of one table whose foreign key matches no dashboard.
	 *
	 * @param IOutput                                $output Repair output channel.
	 * @param array{0: string, 1: string, 2: string} $target Table (without oc_ prefix), its
	 *                                                       foreign-key column, and the
	 *                                                       dashboards column it references.
	 *
	 * @return int Number of rows deleted.
	 */
	private function purgeOrphans(IOutput $output, array $target): int {
		$table = $target[0];

		try {
			$count = $this->deleteOrphanRows(target: $target);

			if ($count > 0) {
				$output->info(
					sprintf(
						'  %s: removed %d orphaned row(s).',
						$table,
						$count
					)
				);
			}

			return $count;
		} catch (Throwable $t) {
			$this->logPurgeFailure(table: $table, failure: $t);
			$output->warning(
				sprintf('  %s: purge failed (%s) — skipped.', $table, $t->getMessage())
			);
			return 0;
		}//end try
	}//end purgeOrphans()

	/**
	 * Delete the target table's rows whose parent dashboard no longer exists.
	 *
	 * @param array{0: string, 1: string, 2: string} $target Table, foreign-key column, dashboard column.
	 *
	 * @return int The number of rows removed.
	 */
	private function deleteOrphanRows(array $target): int {
		[$table, $fkCol, $parentColumn] = $target;

		$qb = $this->db->getQueryBuilder();
		$qb->delete($table)
			->where(
				$qb->expr()->notIn(
					x: $fkCol,
					y: $qb->createFunction(
						call: '(SELECT `' . $parentColumn . '` FROM `*PREFIX*launchpad_dashboards`)'
					)
				)
			);

		return $qb->executeStatement();
	}//end deleteOrphanRows()

	/**
	 * Log a table whose purge failed.
	 *
	 * @param string    $table   The table.
	 * @param Throwable $failure The failure.
	 *
	 * @return void
	 */
	private function logPurgeFailure(string $table, Throwable $failure): void {
		$this->logger->warning(
			message: sprintf(
				'launchpad PurgeOrphanedCascadeData: failed on %s: %s',
				$table,
				$failure->getMessage()
			),
			context: ['app' => 'launchpad']
		);
	}//end logPurgeFailure()
}//end class
