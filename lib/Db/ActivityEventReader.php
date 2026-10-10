<?php

/**
 * ActivityEventReader
 *
 * Reads the Nextcloud activity stream (`oc_activity`, owned by the activity
 * app) for the reports of dashboards-and-who-may-see-them: a person's own
 * events for activity reporting (REQ-DWMS-005, REQ-DWMS-006), and what
 * colleagues did that reached the reader for the colleague activity widget
 * (REQ-DWMS-007). It never writes, and it answers "nothing" when the
 * activity app is not installed rather than failing on a missing table.
 *
 * @category  Db
 * @package   OCA\LaunchPad\Db
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Read-only access to the activity stream.
 */
class ActivityEventReader {
	/**
	 * The activity app's table, without prefix.
	 *
	 * @var string
	 */
	public const TABLE = 'activity';

	/**
	 * Constructor.
	 *
	 * @param IDBConnection $db The database connection.
	 */
	public function __construct(
		private readonly IDBConnection $db,
	) {
	}//end __construct()

	/**
	 * Whether the activity stream exists on this instance.
	 *
	 * @return bool True when the activity app's table is there.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function isAvailable(): bool {
		return $this->db->tableExists(self::TABLE);
	}//end isAvailable()

	/**
	 * The events a person caused, in a period.
	 *
	 * The activity app writes one row per recipient, so an event the person
	 * caused appears once in their own stream (author and affected user are
	 * both them) and once more per colleague it reached. Reading the own
	 * stream counts every event once.
	 *
	 * @param string $userId The person.
	 * @param int $from Unix time, inclusive.
	 * @param int $until Unix time, exclusive.
	 *
	 * @return array<int, array{type: string, timestamp: int}>
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function eventsBy(string $userId, int $from, int $until): array {
		if ($this->isAvailable() === false) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('type', 'timestamp')
			->from(self::TABLE)
			->where($qb->expr()->eq('user', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('affecteduser', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->gte('timestamp', $qb->createNamedParameter($from, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->lt('timestamp', $qb->createNamedParameter($until, IQueryBuilder::PARAM_INT)));

		$out = [];
		$result = $qb->executeQuery();
		while (($row = $result->fetch()) !== false) {
			$out[] = ['type' => (string)$row['type'], 'timestamp' => (int)$row['timestamp']];
		}

		$result->closeCursor();

		return $out;
	}//end eventsBy()

	/**
	 * Recent events by other people that reached the reader.
	 *
	 * The activity app only writes a row for a recipient it judged allowed
	 * to see the event, so this is already scoped to the reader; the
	 * caller still drops entries whose object the reader can no longer see.
	 *
	 * @param string $readerId The reader.
	 * @param int $limit How many rows at most.
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function recentFromOthers(string $readerId, int $limit): array {
		if ($this->isAvailable() === false) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('activity_id', 'timestamp', 'type', 'user', 'app', 'subject', 'object_type', 'file', 'link')
			->from(self::TABLE)
			->where($qb->expr()->eq('affecteduser', $qb->createNamedParameter($readerId)))
			->andWhere($qb->expr()->neq('user', $qb->createNamedParameter($readerId)))
			->andWhere($qb->expr()->isNotNull('user'))
			->andWhere($qb->expr()->neq('user', $qb->createNamedParameter('')))
			->orderBy('timestamp', 'DESC')
			->addOrderBy('activity_id', 'DESC')
			->setMaxResults($limit);

		$out = [];
		$result = $qb->executeQuery();
		while (($row = $result->fetch()) !== false) {
			$out[] = $row;
		}

		$result->closeCursor();

		return $out;
	}//end recentFromOthers()
}//end class
