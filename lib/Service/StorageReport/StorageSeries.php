<?php

/**
 * StorageSeries
 *
 * The daily storage series, kept in launchpad's app data (REQ-DWMS-010).
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
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;

/**
 * One snapshot per day, at most DAYS of them, in one JSON file of app data.
 *
 * A snapshot holds totals only (bytes per workspace, per service and in
 * all), never a file or a person's files, the same daily-bucket shape the
 * dashboard view analytics keep. No table is added for it.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-010-storage-use-per-workspace-and-service-over-time
 */
class StorageSeries {

	/**
	 * The app data folder.
	 */
	public const FOLDER = 'storage-report';

	/**
	 * The file in it.
	 */
	public const FILE = 'series.json';

	/**
	 * How many daily snapshots are kept.
	 */
	public const DAYS = 400;

	/**
	 * Constructor.
	 *
	 * @param IAppData $appData Launchpad's app data.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.2
	 */
	public function __construct(
		private readonly IAppData $appData,
	) {
	}//end __construct()

	/**
	 * The kept snapshots by day (Y-m-d), oldest first.
	 *
	 * @return array<string,array<string,mixed>>
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.2
	 */
	public function all(): array {
		try {
			$content = $this->appData->getFolder(self::FOLDER)->getFile(self::FILE)->getContent();
		} catch (NotFoundException) {
			return [];
		}

		$series = json_decode($content, true);
		if (is_array($series) === false) {
			return [];
		}

		ksort($series);
		return $series;
	}//end all()

	/**
	 * Keep a day's snapshot, replacing that day's earlier one and dropping the oldest beyond DAYS.
	 *
	 * @param string              $day      The day (Y-m-d).
	 * @param array<string,mixed> $snapshot The totals.
	 *
	 * @return array<string,array<string,mixed>> The series as kept.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.2
	 */
	public function record(string $day, array $snapshot): array {
		$series       = $this->all();
		$series[$day] = $snapshot;
		ksort($series);
		$series = array_slice($series, -self::DAYS, null, true);

		try {
			$folder = $this->appData->getFolder(self::FOLDER);
		} catch (NotFoundException) {
			$folder = $this->appData->newFolder(self::FOLDER);
		}

		$content = (string) json_encode($series);
		try {
			$folder->getFile(self::FILE)->putContent($content);
		} catch (NotFoundException) {
			$folder->newFile(self::FILE, $content);
		}

		return $series;
	}//end record()
}//end class
