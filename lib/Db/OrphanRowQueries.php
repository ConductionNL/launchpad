<?php

/**
 * OrphanRowQueries
 *
 * Count and purge rows of a mapper's table whose parent row is gone
 * (REQ-CLN-001, REQ-CLN-002).
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

use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Orphan queries for QBMapper subclasses. A link is
 * `[alias, parent table, parent alias, foreign-key column]`: a row is an
 * orphan when no parent row has `id` equal to its foreign key.
 */
trait OrphanRowQueries {
	/**
	 * Count the orphaned rows.
	 *
	 * @param array{0: string, 1: string, 2: string, 3: string} $link The parent link.
	 *
	 * @return int The number of orphaned rows.
	 */
	protected function countOrphanRows(array $link): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count($link[0] . '.id'));
		$this->whereParentMissing(qb: $qb, link: $link);

		$result = $qb->executeQuery();
		$count = $result->fetchOne();
		$result->closeCursor();

		return (int)($count ?? 0);
	}//end countOrphanRows()

	/**
	 * Delete the orphaned rows. The ids are read first and deleted by
	 * primary key, since DELETE with a JOIN is not portable across drivers.
	 *
	 * @param array{0: string, 1: string, 2: string, 3: string} $link The parent link.
	 *
	 * @return int The number of rows deleted.
	 */
	protected function deleteOrphanRows(array $link): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($link[0] . '.id');
		$this->whereParentMissing(qb: $qb, link: $link);

		$result = $qb->executeQuery();
		$ids = [];
		while (($row = $result->fetch()) !== false) {
			$ids[] = (int)$row['id'];
		}

		$result->closeCursor();

		if (count(value: $ids) === 0) {
			return 0;
		}

		$delete = $this->db->getQueryBuilder();
		$delete->delete(delete: $this->getTableName())
			->where(
				$delete->expr()->in(
					x: 'id',
					y: $delete->createNamedParameter(
						value: $ids,
						type: IQueryBuilder::PARAM_INT_ARRAY
					)
				)
			);

		return $delete->executeStatement();
	}//end deleteOrphanRows()

	/**
	 * Read from this table, left-joined to the parent, where no parent matched.
	 *
	 * @param IQueryBuilder                                    $qb   The query.
	 * @param array{0: string, 1: string, 2: string, 3: string} $link The parent link.
	 *
	 * @return void
	 */
	private function whereParentMissing(IQueryBuilder $qb, array $link): void {
		[$alias, $parentTable, $parentAlias, $column] = $link;
		$qb->from(from: $this->getTableName(), alias: $alias)
			->leftJoin(
				fromAlias: $alias,
				join: $parentTable,
				alias: $parentAlias,
				condition: $parentAlias . '.id = ' . $alias . '.' . $column
			)
			->where($qb->expr()->isNull(x: $parentAlias . '.id'));
	}//end whereParentMissing()
}//end trait
