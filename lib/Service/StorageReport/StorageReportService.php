<?php

/**
 * StorageReportService
 *
 * Storage use per workspace and per service, now and over twelve months, with what can be removed (REQ-DWMS-010).
 *
 * @category Service
 * @package  OCA\LaunchPad\Service\StorageReport
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\StorageReport;

use OCP\DB\QueryBuilder\IQueryBuilder;
use DateTimeImmutable;
use DateTimeZone;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IGroupManager;

/**
 * Builds the administrator's storage report.
 *
 * A workspace is a Nextcloud group: its figure is the files of its members,
 * so a person in two groups counts in both, and the total in use is taken
 * from the services, not from adding the workspaces up. A service is the
 * files, trash or versions over every home, or one app's app data. The
 * removable total is named apart from the total in use: trash entries past
 * the trash retention, and launchpad uploads no launchpad row names.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-010-storage-use-per-workspace-and-service-over-time
 */
class StorageReportService {

	/**
	 * Months in the series.
	 */
	public const MONTHS = 12;

	/**
	 * Days Nextcloud keeps trash when its retention is `auto`.
	 */
	public const DEFAULT_RETENTION_DAYS = 30;

	/**
	 * How old an unreferenced upload must be before it counts as orphaned, so an upload being placed is not.
	 */
	public const UPLOAD_GRACE_SECONDS = 86400;

	/**
	 * How many orphaned upload names the report lists.
	 */
	public const MAX_NAMES = 50;

	/**
	 * Constructor.
	 *
	 * @param StorageFigures $figures The platform's figures.
	 * @param StorageSeries  $series  The daily series.
	 * @param IGroupManager  $groups  The workspaces and who is an administrator.
	 * @param IConfig        $config  The trash retention.
	 * @param ITimeFactory   $time    Now.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.1
	 */
	public function __construct(
		private readonly StorageFigures $figures,
		private readonly StorageSeries $series,
		private readonly IGroupManager $groups,
		private readonly IConfig $config,
		private readonly ITimeFactory $time,
	) {
	}//end __construct()

	/**
	 * The report, for an administrator only.
	 *
	 * @param string $readerId The caller.
	 *
	 * @return array<string,mixed> The report: total, workspaces, services, registers, removable and the series.
	 *
	 * @throws StorageReportRefusedException When the caller is not an administrator.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.4
	 */
	public function report(string $readerId): array {
		if ($readerId === '' || $this->groups->isAdmin($readerId) === false) {
			throw new StorageReportRefusedException(message: 'Only an administrator can read the storage report.');
		}

		$now      = $this->time->getTime();
		$snapshot = $this->snapshot();
		$series   = $this->series->all();
		$series[gmdate('Y-m-d', $now)] = $snapshot;
		ksort($series);
		$months = self::months(now: $now);

		$workspaces = [];
		foreach ($snapshot['workspaces'] as $id => $bytes) {
			$workspaces[] = ['id' => $id, 'bytes' => $bytes, 'series' => self::monthly(series: $series, section: 'workspaces', key: $id, months: $months)];
		}

		$services = [];
		foreach ($snapshot['services'] as $name => $bytes) {
			$services[] = ['name' => $name, 'bytes' => $bytes, 'series' => self::monthly(series: $series, section: 'services', key: $name, months: $months)];
		}

		return [
			'date'       => gmdate('Y-m-d', $now),
			'total'      => $snapshot['total'],
			'series'     => self::monthly(series: $series, section: null, key: 'total', months: $months),
			'workspaces' => $workspaces,
			'services'   => $services,
			'registers'  => $this->figures->registerCounts(),
			'removable'  => self::removable(
				trash: $this->figures->trashEntries(),
				retentionDays: self::retentionDays(setting: (string) $this->config->getSystemValue('trashbin_retention_obligation', 'auto')),
				uploads: $this->figures->uploads(),
				referenced: $this->figures->referencedUploads(),
				now: $now
			),
		];
	}//end report()

	/**
	 * Take today's snapshot and keep it in the series.
	 *
	 * @return array<string,mixed> The snapshot.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.2
	 */
	public function recordToday(): array {
		$snapshot = $this->snapshot();
		$this->series->record(day: gmdate('Y-m-d', $this->time->getTime()), snapshot: $snapshot);
		return $snapshot;
	}//end recordToday()

	/**
	 * The figures now: bytes per workspace, per service, and in total.
	 *
	 * @return array{total:int,workspaces:array<string,int>,services:array<string,int>}
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.1
	 */
	public function snapshot(): array {
		$homes      = $this->figures->homeUsage();
		$workspaces = [];
		foreach ($this->groups->search('') as $group) {
			$bytes = 0;
			foreach ($group->getUsers() as $user) {
				$bytes += ($homes[$user->getUID()] ?? 0);
			}

			$workspaces[$group->getGID()] = $bytes;
		}

		ksort($workspaces);
		$services = $this->figures->serviceUsage();

		return ['total' => array_sum($services), 'workspaces' => $workspaces, 'services' => $services];
	}//end snapshot()

	/**
	 * What can be removed: trash past its retention and uploads nothing names.
	 *
	 * @param list<array{user:string,name:string,bytes:int}> $trash         Trash entries.
	 * @param int|null                                        $retentionDays Days trash is kept; null when it is never expired.
	 * @param list<array{name:string,bytes:int,mtime:int}>    $uploads       Launchpad uploads.
	 * @param array<string,true>|null                         $referenced    Upload names still named; null when unknown.
	 * @param int                                             $now           Now (Unix time).
	 *
	 * @return array{total:int,pastRetention:array{count:int,bytes:int,retentionDays:int|null},orphanedUploads:array{count:int,bytes:int,names:list<string>,known:bool}}
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.3
	 */
	public static function removable(array $trash, ?int $retentionDays, array $uploads, ?array $referenced, int $now): array {
		$past = ['count' => 0, 'bytes' => 0, 'retentionDays' => $retentionDays];
		if ($retentionDays !== null) {
			$cutoff = ($now - ($retentionDays * 86400));
			foreach ($trash as $entry) {
				if (preg_match('/\.d(\d+)$/', $entry['name'], $match) === 1 && (int) $match[1] < $cutoff) {
					$past['count']++;
					$past['bytes'] += $entry['bytes'];
				}
			}
		}

		$orphaned = ['count' => 0, 'bytes' => 0, 'names' => [], 'known' => ($referenced !== null)];
		if ($referenced !== null) {
			foreach ($uploads as $upload) {
				if (isset($referenced[$upload['name']]) === true || $upload['mtime'] > ($now - self::UPLOAD_GRACE_SECONDS)) {
					continue;
				}

				$orphaned['count']++;
				$orphaned['bytes'] += $upload['bytes'];
				if (count($orphaned['names']) < self::MAX_NAMES) {
					$orphaned['names'][] = $upload['name'];
				}
			}
		}

		return ['total' => ($past['bytes'] + $orphaned['bytes']), 'pastRetention' => $past, 'orphanedUploads' => $orphaned];
	}//end removable()

	/**
	 * The days after which trash may be removed, from Nextcloud's `trashbin_retention_obligation`.
	 *
	 * `auto` keeps 30 days; `D, auto` and `auto, D` and `D1, D2` remove at the latest after their last
	 * number of days; `D` alone too; `disabled` never expires trash, so nothing is past retention.
	 *
	 * @param string $setting The setting.
	 *
	 * @return int|null The days, or null when trash is never expired.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.3
	 */
	public static function retentionDays(string $setting): ?int {
		$parts = array_map('trim', explode(',', strtolower($setting)));
		if (in_array('disabled', $parts, true) === true) {
			return null;
		}

		$days = array_values(array_filter($parts, static fn (string $part): bool => ctype_digit($part)));
		if ($days === []) {
			return self::DEFAULT_RETENTION_DAYS;
		}

		return (int) end($days);
	}//end retentionDays()

	/**
	 * The report as CSV: the total, each workspace and service with its twelve months, and the removable total.
	 *
	 * @param array<string,mixed> $report A report from report().
	 *
	 * @return string The CSV.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.4
	 */
	public static function csv(array $report): string {
		$months = array_keys((array) ($report['series'] ?? []));
		$out    = fopen('php://temp', 'r+');
		fputcsv($out, array_merge(['kind', 'name', 'bytes'], $months), ',', '"', '');
		fputcsv($out, array_merge(['total', '', (string) $report['total']], self::cells(series: (array) $report['series'])), ',', '"', '');
		foreach ((array) $report['workspaces'] as $workspace) {
			fputcsv($out, array_merge(['workspace', $workspace['id'], (string) $workspace['bytes']], self::cells(series: $workspace['series'])), ',', '"', '');
		}

		foreach ((array) $report['services'] as $service) {
			fputcsv($out, array_merge(['service', $service['name'], (string) $service['bytes']], self::cells(series: $service['series'])), ',', '"', '');
		}

		$removable = (array) $report['removable'];
		fputcsv($out, ['removable', 'past retention', (string) $removable['pastRetention']['bytes']], ',', '"', '');
		fputcsv($out, ['removable', 'orphaned uploads', (string) $removable['orphanedUploads']['bytes']], ',', '"', '');
		fputcsv($out, ['removable', 'total', (string) $removable['total']], ',', '"', '');
		rewind($out);
		$csv = (string) stream_get_contents($out);
		fclose($out);

		return $csv;
	}//end csv()

	/**
	 * The last MONTHS months (Y-m), oldest first, ending with the month of now.
	 *
	 * @param int $now Now (Unix time).
	 *
	 * @return list<string>
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.2
	 */
	public static function months(int $now): array {
		$first  = (new DateTimeImmutable('@' . $now))->setTimezone(new DateTimeZone('UTC'))->modify('first day of this month');
		$months = [];
		for ($back = (self::MONTHS - 1); $back >= 0; $back--) {
			$months[] = $first->modify('-' . $back . ' months')->format('Y-m');
		}

		return $months;
	}//end months()

	/**
	 * Per month, the value of the month's last snapshot; null for a month without one.
	 *
	 * @param array<string,array<string,mixed>> $series  The snapshots by day, oldest first.
	 * @param string|null                       $section `workspaces`, `services`, or null for a top-level value.
	 * @param string                            $key     The workspace, the service, or `total`.
	 * @param list<string>                      $months  The months (Y-m).
	 *
	 * @return array<string,int|null>
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.2
	 */
	public static function monthly(array $series, ?string $section, string $key, array $months): array {
		$values = array_fill_keys($months, null);
		foreach ($series as $day => $snapshot) {
			$month = substr((string) $day, 0, 7);
			if (array_key_exists($month, $values) === false) {
				continue;
			}

			$value = $snapshot[$key] ?? null;
			if ($section !== null) {
				$value = $snapshot[$section][$key] ?? null;
			}

			if ($value !== null) {
				$values[$month] = (int) $value;
			}
		}

		return $values;
	}//end monthly()

	/**
	 * A series as CSV cells, empty for a month without a snapshot.
	 *
	 * @param array<string,int|null> $series The series.
	 *
	 * @return list<string>
	 */
	private static function cells(array $series): array {
		$cells = [];
		foreach ($series as $value) {
			$cells[] = (string) $value;
		}

		return $cells;
	}//end cells()
}//end class
