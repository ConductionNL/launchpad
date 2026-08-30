<?php

/**
 * Version001005Date20260430120000
 *
 * Migration to add the launchpad_dashboard_shares table for per-user/per-group
 * dashboard sharing.
 *
 * @category  Migration
 * @package   OCA\LaunchPad\Migration
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version001005Date20260430120000 extends SimpleMigrationStep {
	/**
	 * Create the dashboard shares table.
	 *
	 * @param IOutput $output The migration output handler.
	 * @param Closure $schemaClosure The schema closure returns an ISchemaWrapper.
	 * @param array $options The migration options.
	 *
	 * @return ISchemaWrapper|null The modified schema or null.
	 */
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options,
	): ?ISchemaWrapper {
		$schema = $schemaClosure();

		DashboardShareTableBuilder::create(schema: $schema);

		return $schema;
	}//end changeSchema()
}//end class
