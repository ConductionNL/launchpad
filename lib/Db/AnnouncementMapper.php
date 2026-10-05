<?php

/**
 * AnnouncementMapper
 *
 * Reads and writes `launchpad_announcements` (engagement-announcements,
 * REQ-ANN-001, REQ-ANN-003, REQ-ANN-005). The publish window is compared
 * in SQL on UTC `Y-m-d H:i:s` strings; targeting is applied by
 * AnnouncementService because it needs the reader's groups.
 *
 * @category  Database
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

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * Announcement mapper.
 *
 * @extends QBMapper<Announcement>
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
 */
class AnnouncementMapper extends QBMapper {
	/**
	 * Upper bound on rows read per list, so a long history never loads whole.
	 */
	public const LIST_CAP = 500;

	/**
	 * Constructor.
	 *
	 * @param IDBConnection $db Database connection.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct(
			db: $db,
			tableName: 'launchpad_announcements',
			entityClass: Announcement::class
		);
	}//end __construct()

	/**
	 * One announcement by its uuid.
	 *
	 * @param string $uuid The uuid.
	 *
	 * @return Announcement
	 *
	 * @throws DoesNotExistException When there is none.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function findByUuid(string $uuid): Announcement {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(from: $this->getTableName())
			->where($qb->expr()->eq(x: 'uuid', y: $qb->createNamedParameter(value: $uuid)));

		return $this->findEntity(query: $qb);
	}//end findByUuid()

	/**
	 * Published announcements inside their window at the given time, newest first.
	 *
	 * @param string $now UTC time, Y-m-d H:i:s.
	 *
	 * @return Announcement[]
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function findLive(string $now): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(from: $this->getTableName())
			->where($qb->expr()->eq(x: 'status', y: $qb->createNamedParameter(value: Announcement::STATUS_PUBLISHED)))
			->andWhere($qb->expr()->lte(x: 'publish_at', y: $qb->createNamedParameter(value: $now)))
			->andWhere(
				$qb->expr()->orX(
					$qb->expr()->isNull(x: 'expires_at'),
					$qb->expr()->gt(x: 'expires_at', y: $qb->createNamedParameter(value: $now))
				)
			)
			->orderBy(sort: 'publish_at', order: 'DESC')
			->addOrderBy(sort: 'id', order: 'DESC')
			->setMaxResults(maxResults: self::LIST_CAP);

		return $this->findEntities(query: $qb);
	}//end findLive()

	/**
	 * Every announcement, newest first: the authors' view (drafts included).
	 *
	 * @return Announcement[]
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function findAllNewestFirst(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(from: $this->getTableName())
			->orderBy(sort: 'updated_at', order: 'DESC')
			->addOrderBy(sort: 'id', order: 'DESC')
			->setMaxResults(maxResults: self::LIST_CAP);

		return $this->findEntities(query: $qb);
	}//end findAllNewestFirst()

	/**
	 * Live announcements whose followers were not notified yet.
	 *
	 * @param string $now UTC time, Y-m-d H:i:s.
	 *
	 * @return Announcement[]
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function findDueForNotification(string $now): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(from: $this->getTableName())
			->where($qb->expr()->eq(x: 'status', y: $qb->createNamedParameter(value: Announcement::STATUS_PUBLISHED)))
			->andWhere($qb->expr()->lte(x: 'publish_at', y: $qb->createNamedParameter(value: $now)))
			->andWhere($qb->expr()->isNull(x: 'notified_at'))
			->andWhere(
				$qb->expr()->orX(
					$qb->expr()->isNull(x: 'expires_at'),
					$qb->expr()->gt(x: 'expires_at', y: $qb->createNamedParameter(value: $now))
				)
			)
			->orderBy(sort: 'id')
			->setMaxResults(maxResults: self::LIST_CAP);

		return $this->findEntities(query: $qb);
	}//end findDueForNotification()
}//end class
