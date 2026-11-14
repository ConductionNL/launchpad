<?php

/**
 * AnnouncementMapper
 *
 * Reads and writes `oc_launchpad_announcements`. Targeting and the reader's
 * groups are applied in {@see \OCA\LaunchPad\Service\AnnouncementService};
 * this mapper only narrows by status and time window.
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
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Db;

use DateTime;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Mapper for announcements.
 *
 * @template-extends QBMapper<Announcement>
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
 */
class AnnouncementMapper extends QBMapper {
	use ColumnMatchQueries;

	/**
	 * Constructor.
	 *
	 * @param IDBConnection $db The database.
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct(db: $db, tableName: 'launchpad_announcements', entityClass: Announcement::class);
	}//end __construct()

	/**
	 * One announcement by uuid.
	 *
	 * @param string $uuid The uuid.
	 *
	 * @return Announcement
	 *
	 * @throws DoesNotExistException When there is none.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function findByUuid(string $uuid): Announcement {
		return $this->findEntity(query: $this->selectWhere(match: ['uuid' => $uuid]));
	}//end findByUuid()

	/**
	 * Published announcements inside their time window at a moment, newest first.
	 *
	 * @param DateTime $now The moment.
	 *
	 * @return list<Announcement>
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function findLive(DateTime $now): array {
		$qb = $this->selectWhere(match: ['status' => Announcement::STATUS_PUBLISHED]);
		$qb->andWhere(
			$qb->expr()->orX(
				$qb->expr()->isNull('publish_at'),
				$qb->expr()->lte('publish_at', $qb->createNamedParameter(value: $now, type: IQueryBuilder::PARAM_DATETIME_MUTABLE))
			)
		)->andWhere(
			$qb->expr()->orX(
				$qb->expr()->isNull('expires_at'),
				$qb->expr()->gt('expires_at', $qb->createNamedParameter(value: $now, type: IQueryBuilder::PARAM_DATETIME_MUTABLE))
			)
		)->orderBy('publish_at', 'DESC')->addOrderBy('id', 'DESC');

		return $this->findEntities(query: $qb);
	}//end findLive()

	/**
	 * Drafts, of one author or of everyone.
	 *
	 * @param string|null $authorId The author, or null for every draft.
	 *
	 * @return list<Announcement>
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function findDrafts(?string $authorId): array {
		$match = ['status' => Announcement::STATUS_DRAFT];
		if ($authorId !== null) {
			$match['author_id'] = $authorId;
		}

		$qb = $this->selectWhere(match: $match);
		$qb->orderBy('updated_at', 'DESC');

		return $this->findEntities(query: $qb);
	}//end findDrafts()
}//end class
