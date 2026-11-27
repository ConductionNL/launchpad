<?php

/**
 * DashboardWrites
 *
 * Counts the writes to the dashboards and dashboard-shares tables in this
 * request, so a read kept for the request can tell it is still current.
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

/**
 * Write counter for the dashboard visibility tables.
 *
 * DashboardMapper and DashboardShareMapper bump it on every insert, update,
 * delete and bulk statement; DashboardService keeps the "visible to user"
 * list for the request only while the counter is unchanged.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
final class DashboardWrites {

	/**
	 * Writes so far in this request.
	 *
	 * @var int
	 */
	private static int $count = 0;

	/**
	 * Record one write.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public static function bump(): void {
		self::$count++;
	}//end bump()

	/**
	 * The writes so far.
	 *
	 * @return int The counter.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public static function count(): int {
		return self::$count;
	}//end count()
}//end class
