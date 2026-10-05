<?php

/**
 * AnnouncementFollowMapper
 *
 * Reads and writes `launchpad_ann_follows` (REQ-ANN-003).
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

use DateTime;
use DateTimeZone;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * Announcement follow mapper.
 *
 * @extends QBMapper<AnnouncementFollow>
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
 */
class AnnouncementFollowMapper extends QBMapper {
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
			tableName: 'launchpad_ann_follows',
			entityClass: AnnouncementFollow::class
		);
	}//end __construct()

	/**
	 * The categories one person follows.
	 *
	 * @param string $userId The person.
	 *
	 * @return string[]
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function findCategoriesOf(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('category')
			->from(from: $this->getTableName())
			->where($qb->expr()->eq(x: 'user_id', y: $qb->createNamedParameter(value: $userId)))
			->orderBy(sort: 'category');
		$result     = $qb->executeQuery();
		$categories = [];
		while (($row = $result->fetch()) !== false) {
			$categories[] = (string) $row['category'];
		}

		$result->closeCursor();

		return $categories;
	}//end findCategoriesOf()

	/**
	 * The people who follow a category.
	 *
	 * @param string $category The category.
	 *
	 * @return string[]
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function findFollowersOf(string $category): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id')
			->from(from: $this->getTableName())
			->where($qb->expr()->eq(x: 'category', y: $qb->createNamedParameter(value: $category)))
			->orderBy(sort: 'user_id');
		$result    = $qb->executeQuery();
		$followers = [];
		while (($row = $result->fetch()) !== false) {
			$followers[] = (string) $row['user_id'];
		}

		$result->closeCursor();

		return $followers;
	}//end findFollowersOf()

	/**
	 * Follow a category; following it twice keeps one row.
	 *
	 * @param string $userId The person.
	 * @param string $category The category.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function follow(string $userId, string $category): void {
		if (in_array(needle: $category, haystack: $this->findCategoriesOf(userId: $userId), strict: true) === true) {
			return;
		}

		$row = new AnnouncementFollow();
		// Entity setters take their argument positionally.
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$row->setUserId($userId);
		$row->setCategory($category);
		$row->setCreatedAt((new DateTime(timezone: new DateTimeZone(timezone: 'UTC')))->format(format: 'Y-m-d H:i:s'));
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$this->insert(entity: $row);
	}//end follow()

	/**
	 * Stop following a category.
	 *
	 * @param string $userId The person.
	 * @param string $category The category.
	 *
	 * @return integer Rows removed.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function unfollow(string $userId, string $category): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete(delete: $this->getTableName())
			->where($qb->expr()->eq(x: 'user_id', y: $qb->createNamedParameter(value: $userId)))
			->andWhere($qb->expr()->eq(x: 'category', y: $qb->createNamedParameter(value: $category)));

		return $qb->executeStatement();
	}//end unfollow()

	/**
	 * Remove every follow of a person (user deleted).
	 *
	 * @param string $userId The person.
	 *
	 * @return integer Rows removed.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function deleteByUser(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete(delete: $this->getTableName())
			->where($qb->expr()->eq(x: 'user_id', y: $qb->createNamedParameter(value: $userId)));

		return $qb->executeStatement();
	}//end deleteByUser()
}//end class
