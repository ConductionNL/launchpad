<?php

/**
 * AnnouncementFollowMapper
 *
 * Reads and writes `oc_launchpad_ann_follows` (engagement-announcements,
 * REQ-ANN-003).
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
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * Mapper for followed categories.
 *
 * @template-extends QBMapper<AnnouncementFollow>
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
 */
class AnnouncementFollowMapper extends QBMapper {
	use ColumnMatchQueries;

	/**
	 * Constructor.
	 *
	 * @param IDBConnection $db The database.
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct(db: $db, tableName: 'launchpad_ann_follows', entityClass: AnnouncementFollow::class);
	}//end __construct()

	/**
	 * The follows of one user.
	 *
	 * @param string $userId The user.
	 *
	 * @return list<AnnouncementFollow>
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
	 */
	public function findByUser(string $userId): array {
		return $this->findEntities(query: $this->selectWhere(match: ['user_id' => $userId]));
	}//end findByUser()

	/**
	 * The follows of one category.
	 *
	 * @param string $category The category.
	 *
	 * @return list<AnnouncementFollow>
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
	 */
	public function findByCategory(string $category): array {
		return $this->findEntities(query: $this->selectWhere(match: ['category' => $category]));
	}//end findByCategory()

	/**
	 * Stop following a category; nothing happens when it was not followed.
	 *
	 * @param string $userId   The user.
	 * @param string $category The category.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
	 */
	public function unfollow(string $userId, string $category): void {
		$this->deleteWhere(match: ['user_id' => $userId, 'category' => $category])->executeStatement();
	}//end unfollow()
}//end class
