<?php

/**
 * Version001008Date20260502000000
 *
 * Migration that adds the `group_id` column and the composite
 * `(type, group_id)` index to `oc_launchpad_dashboards`. Required by
 * REQ-DASH-011 (group-shared dashboard type) and REQ-DASH-013
 * (visible-to-user resolution endpoint).
 *
 * Zero-impact: the column is nullable, no backfill is required, and
 * the index speeds up the new `findByGroup` / `findVisibleToUser`
 * lookups without affecting existing queries.
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
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add group_id column + composite index to launchpad_dashboards (REQ-DASH-011).
 */
class Version001008Date20260502000000 extends SimpleMigrationStep {
	/**
	 * Add the group_id column and the composite (type, group_id) index.
	 *
	 * @param IOutput $output The migration output handler.
	 * @param Closure $schemaClosure The schema closure returns an
	 *                               ISchemaWrapper.
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

		if ($schema->hasTable('launchpad_dashboards') === false) {
			return $schema;
		}

		$table = $schema->getTable('launchpad_dashboards');

		if ($table->hasColumn('group_id') === false) {
			$table->addColumn(
				'group_id',
				Types::STRING,
				[
					'notnull' => false,
					'length' => 64,
				]
			);
		}

		if ($table->hasIndex('launchpad_dash_type_group') === false) {
			$table->addIndex(
				['type', 'group_id'],
				'launchpad_dash_type_group'
			);
		}

		return $schema;
	}//end changeSchema()
}//end class
