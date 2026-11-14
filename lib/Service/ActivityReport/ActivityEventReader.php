<?php

/**
 * ActivityEventReader
 *
 * Reads a person's actions from Nextcloud's activity events (REQ-DWMS-006).
 *
 * @category Service
 * @package  OCA\LaunchPad\Service\ActivityReport
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

namespace OCA\LaunchPad\Service\ActivityReport;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Throwable;

/**
 * The activity events a person caused in a period, across the product.
 *
 * Read from the activity app's own table, which every app that publishes
 * activity writes to: one row per person the event is shown to, so the
 * rows of one action are folded into one event. Only the type and the time
 * are kept; nothing measures hours.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-006-activity-as-a-figure-and-as-a-calendar
 */
class ActivityEventReader {

	/**
	 * The activity app's table.
	 */
	public const TABLE = 'activity';

	/**
	 * Constructor.
	 *
	 * @param IDBConnection $db The database.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-4.5
	 */
	public function __construct(
		private readonly IDBConnection $db,
	) {
	}//end __construct()

	/**
	 * The person's events in [from, until), oldest first; null when the activity app keeps none here.
	 *
	 * @param string $userId The person who acted.
	 * @param int    $from   The first second of the period (Unix time).
	 * @param int    $until  The first second after the period (Unix time).
	 *
	 * @return list<array{type:string,timestamp:int}>|null The events.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-4.5
	 */
	public function events(string $userId, int $from, int $until): ?array {
		try {
			$query = $this->db->getQueryBuilder();
			$query->select('type', 'timestamp', 'app', 'subject', 'object_type', 'object_id')
				->from(self::TABLE)
				->where($query->expr()->eq('user', $query->createNamedParameter($userId)))
				->andWhere($query->expr()->gte('timestamp', $query->createNamedParameter($from, IQueryBuilder::PARAM_INT)))
				->andWhere($query->expr()->lt('timestamp', $query->createNamedParameter($until, IQueryBuilder::PARAM_INT)))
				->orderBy('timestamp', 'ASC');
			$result = $query->executeQuery();
			$rows   = $result->fetchAll();
			$result->closeCursor();
		} catch (Throwable $e) {
			return null;
		}

		return self::fold(rows: $rows);
	}//end events()

	/**
	 * One event per action: the rows of one action shown to several people become one.
	 *
	 * @param array<int,array<string,mixed>> $rows The table rows.
	 *
	 * @return list<array{type:string,timestamp:int}> The events.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-4.5
	 */
	public static function fold(array $rows): array {
		$events = [];
		foreach ($rows as $row) {
			$type = trim((string) ($row['type'] ?? ''));
			if ($type === '') {
				$type = 'other';
			}

			$timestamp = (int) ($row['timestamp'] ?? 0);
			$parts     = [$type, $timestamp, (string) ($row['app'] ?? ''), (string) ($row['subject'] ?? '')];
			$parts[]   = (string) ($row['object_type'] ?? '');
			$parts[]   = (string) ($row['object_id'] ?? '');
			$key       = implode("\0", $parts);
			$events[$key] = ['type' => $type, 'timestamp' => $timestamp];
		}

		return array_values($events);
	}//end fold()
}//end class
