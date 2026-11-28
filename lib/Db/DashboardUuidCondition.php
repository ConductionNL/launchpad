<?php

/**
 * DashboardUuidCondition
 *
 * The `dashboard_uuid = :uuid` condition of the version and reaction mappers' aggregate queries.
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
 * @spec openspec/specs/dashboard-versioning/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Restricts a query to the rows of one dashboard.
 *
 * @spec openspec/specs/dashboard-versioning/spec.md
 */
final class DashboardUuidCondition {
	/**
	 * Add `WHERE dashboard_uuid = :dashboardUuid` to the query.
	 *
	 * @param IQueryBuilder $qb            The query, after SELECT and FROM.
	 * @param string        $dashboardUuid The dashboard UUID.
	 *
	 * @return IQueryBuilder The same query.
	 *
	 * @spec openspec/specs/dashboard-versioning/spec.md
	 */
	public function restrict(IQueryBuilder $qb, string $dashboardUuid): IQueryBuilder {
		return $qb->where(
			$qb->expr()->eq(
				x: 'dashboard_uuid',
				y: $qb->createNamedParameter(value: $dashboardUuid)
			)
		);
	}//end restrict()
}//end class
