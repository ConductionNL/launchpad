<?php

/**
 * ColumnMatchQueries
 *
 * Query starters for mappers whose WHERE clause is a list of
 * column = value conditions joined by AND.
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

use OCP\DB\QueryBuilder\IParameter;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Builds SELECT and DELETE queries on the mapper's table filtered on
 * column = value pairs. Integer values are bound as PARAM_INT, every other
 * value with the default string binding. Used by QBMapper subclasses.
 */
trait ColumnMatchQueries {
	/**
	 * Start `SELECT *` on this mapper's table, filtered on every
	 * column => value pair of `$match` (AND).
	 *
	 * @param array<string, mixed> $match The column => value conditions.
	 *
	 * @return IQueryBuilder The query, ready for more clauses.
	 */
	protected function selectWhere(array $match): IQueryBuilder {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(from: $this->getTableName());

		return $this->andWhereColumns(qb: $qb, match: $match);
	}//end selectWhere()

	/**
	 * Start `DELETE` on this mapper's table, filtered on every
	 * column => value pair of `$match` (AND).
	 *
	 * @param array<string, mixed> $match The column => value conditions.
	 *
	 * @return IQueryBuilder The query, ready for more clauses.
	 */
	protected function deleteWhere(array $match): IQueryBuilder {
		$qb = $this->db->getQueryBuilder();
		$qb->delete(delete: $this->getTableName());

		return $this->andWhereColumns(qb: $qb, match: $match);
	}//end deleteWhere()

	/**
	 * Add one equality condition per column: `where` for the first,
	 * `andWhere` for the rest.
	 *
	 * @param IQueryBuilder        $qb    The query.
	 * @param array<string, mixed> $match The column => value conditions.
	 *
	 * @return IQueryBuilder The query.
	 */
	private function andWhereColumns(IQueryBuilder $qb, array $match): IQueryBuilder {
		$first = true;
		foreach ($match as $column => $value) {
			$condition = $qb->expr()->eq(x: $column, y: $this->namedParameter(qb: $qb, value: $value));
			if ($first === true) {
				$qb->where($condition);
				$first = false;
				continue;
			}

			$qb->andWhere($condition);
		}

		return $qb;
	}//end andWhereColumns()

	/**
	 * Bind a value as a named parameter: PARAM_INT for an integer, the
	 * default string binding otherwise.
	 *
	 * @param IQueryBuilder $qb    The query.
	 * @param mixed         $value The value.
	 *
	 * @return IParameter|string The placeholder.
	 */
	private function namedParameter(IQueryBuilder $qb, mixed $value): IParameter|string {
		if (is_int($value) === true) {
			return $qb->createNamedParameter(value: $value, type: IQueryBuilder::PARAM_INT);
		}

		return $qb->createNamedParameter(value: $value);
	}//end namedParameter()
}//end trait
