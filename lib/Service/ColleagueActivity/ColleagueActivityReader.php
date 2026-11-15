<?php

/**
 * ColleagueActivityReader
 *
 * Reads the recent activity other people caused that the activity app shows to a reader (REQ-DWMS-007).
 *
 * @category Service
 * @package  OCA\LaunchPad\Service\ColleagueActivity
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

namespace OCA\LaunchPad\Service\ColleagueActivity;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Throwable;

/**
 * The reader's own activity stream, minus what the reader did.
 *
 * The activity app writes one row per person an event is shown to (the
 * `affecteduser`), so the rows addressed to the reader are the stream the
 * activity app already scoped to them. This reads those rows, newest first,
 * and leaves out the reader's own actions. It does not build a second feed.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-007-recent-colleague-activity-filtered-by-what-you-may-see
 */
class ColleagueActivityReader {

	/**
	 * The activity app's table.
	 */
	public const TABLE = 'activity';

	/**
	 * The most rows one read scans.
	 */
	public const MAX_ROWS = 500;

	/**
	 * Constructor.
	 *
	 * @param IDBConnection $db The database.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-5.1
	 */
	public function __construct(
		private readonly IDBConnection $db,
	) {
	}//end __construct()

	/**
	 * Rows shown to the reader and caused by somebody else since a moment, newest first; null without the activity app.
	 *
	 * @param string $readerId The reader.
	 * @param int    $since    The first second to include (Unix time).
	 * @param int    $limit    The most rows to read, capped at MAX_ROWS.
	 *
	 * @return list<array<string,mixed>>|null The rows.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-5.1
	 */
	public function rows(string $readerId, int $since, int $limit=self::MAX_ROWS): ?array {
		try {
			$query = $this->db->getQueryBuilder();
			$query->select('activity_id', 'timestamp', 'type', 'user', 'app', 'subject', 'subjectparams', 'object_type', 'object_name', 'link')
				->from(self::TABLE)
				->where($query->expr()->eq('affecteduser', $query->createNamedParameter($readerId)))
				->andWhere($query->expr()->neq('user', $query->createNamedParameter($readerId)))
				->andWhere($query->expr()->neq('user', $query->createNamedParameter('')))
				->andWhere($query->expr()->gte('timestamp', $query->createNamedParameter($since, IQueryBuilder::PARAM_INT)))
				->orderBy('timestamp', 'DESC')
				->addOrderBy('activity_id', 'DESC')
				->setMaxResults(max(1, min($limit, self::MAX_ROWS)));
			$result = $query->executeQuery();
			$rows   = $result->fetchAll();
			$result->closeCursor();
		} catch (Throwable $e) {
			return null;
		}

		return array_values($rows);
	}//end rows()
}//end class
