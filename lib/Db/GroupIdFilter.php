<?php

/**
 * GroupIdFilter
 *
 * The `group_id IN (...)` condition shared by the role mappers.
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
 *
 * @spec openspec/specs/role-feature-permissions/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Restricts a query to rows of the given Nextcloud groups.
 *
 * @spec openspec/specs/role-feature-permissions/spec.md
 */
final class GroupIdFilter {
	/**
	 * Add `WHERE group_id IN (:groupIds)` to the query.
	 *
	 * @param IQueryBuilder $qb       The query, after SELECT and FROM.
	 * @param string[]      $groupIds The Nextcloud group IDs.
	 *
	 * @return IQueryBuilder The same query.
	 *
	 * @spec openspec/specs/role-feature-permissions/spec.md
	 */
	public function restrict(IQueryBuilder $qb, array $groupIds): IQueryBuilder {
		return $qb->where(
			$qb->expr()->in(
				x: 'group_id',
				y: $qb->createNamedParameter(
					value: $groupIds,
					type: IQueryBuilder::PARAM_STR_ARRAY
				)
			)
		);
	}//end restrict()
}//end class
