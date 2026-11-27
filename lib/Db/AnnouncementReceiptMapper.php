<?php

/**
 * AnnouncementReceiptMapper
 *
 * Reads and writes `oc_launchpad_ann_receipts` (announcements-managed-in-settings D3).
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
 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Mapper for announcement receipts.
 *
 * @template-extends QBMapper<AnnouncementReceipt>
 *
 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 */
class AnnouncementReceiptMapper extends QBMapper {
	use ColumnMatchQueries;

	/**
	 * The table.
	 */
	public const TABLE = 'launchpad_ann_receipts';

	/**
	 * Constructor.
	 *
	 * @param IDBConnection $db The database.
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct(db: $db, tableName: self::TABLE, entityClass: AnnouncementReceipt::class);
	}//end __construct()

	/**
	 * The receipt of one reader for one announcement, or null.
	 *
	 * @param string $uuid   The announcement.
	 * @param string $userId The reader.
	 *
	 * @return AnnouncementReceipt|null
	 *
	 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
	 */
	public function findOne(string $uuid, string $userId): ?AnnouncementReceipt {
		$found = $this->findEntities(query: $this->selectWhere(match: ['announcement_uuid' => $uuid, 'user_id' => $userId]));

		return $found[0] ?? null;
	}//end findOne()

	/**
	 * Every receipt of one announcement.
	 *
	 * @param string $uuid The announcement.
	 *
	 * @return list<AnnouncementReceipt>
	 *
	 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
	 */
	public function findByAnnouncement(string $uuid): array {
		return $this->findEntities(query: $this->selectWhere(match: ['announcement_uuid' => $uuid]));
	}//end findByAnnouncement()

	/**
	 * The receipts of one reader among the given announcements, in one query.
	 *
	 * @param string   $userId The reader.
	 * @param string[] $uuids  The announcements.
	 *
	 * @return list<AnnouncementReceipt>
	 *
	 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
	 */
	public function findByUserAmong(string $userId, array $uuids): array {
		if ($uuids === []) {
			return [];
		}

		$qb = $this->selectWhere(match: ['user_id' => $userId]);
		$qb->andWhere(
			$qb->expr()->in(
				x: 'announcement_uuid',
				y: $qb->createNamedParameter(value: array_values($uuids), type: IQueryBuilder::PARAM_STR_ARRAY)
			)
		);

		return $this->findEntities(query: $qb);
	}//end findByUserAmong()

	/**
	 * Per announcement, how many readers have seen it and how many confirmed
	 * it, in one grouped query.
	 *
	 * @param string[] $uuids The announcements.
	 *
	 * @return array<string, array{seen: int, confirmed: int}> Keyed by uuid; absent uuids have no receipts.
	 *
	 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
	 */
	public function countsFor(array $uuids): array {
		if ($uuids === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('announcement_uuid')
			->selectAlias($qb->func()->count('seen_at'), 'seen')
			->selectAlias($qb->func()->count('confirmed_at'), 'confirmed')
			->from(from: $this->getTableName())
			->where(
				$qb->expr()->in(
					x: 'announcement_uuid',
					y: $qb->createNamedParameter(value: array_values($uuids), type: IQueryBuilder::PARAM_STR_ARRAY)
				)
			)
			->groupBy('announcement_uuid');

		$counts = [];
		$cursor = $qb->executeQuery();
		while (($row = $cursor->fetch()) !== false) {
			$counts[(string) $row['announcement_uuid']] = [
				'seen'      => (int) $row['seen'],
				'confirmed' => (int) $row['confirmed'],
			];
		}

		$cursor->closeCursor();

		return $counts;
	}//end countsFor()

	/**
	 * Remove every receipt of an announcement (when it is deleted).
	 *
	 * @param string $uuid The announcement.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
	 */
	public function deleteByAnnouncement(string $uuid): void {
		$this->deleteWhere(match: ['announcement_uuid' => $uuid])->executeStatement();
	}//end deleteByAnnouncement()
}//end class
