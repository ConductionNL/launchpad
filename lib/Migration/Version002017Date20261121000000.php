<?php

/**
 * Version002017Date20261121000000
 *
 * Adds `hierarchies` to `launchpad_dashboards`: the JSON list of the
 * hierarchy descriptors the filter bus joins widgets on
 * (drill-down-cross-widget-filter, REQ-CWF-011). The column is nullable;
 * existing dashboards keep NULL, which the entity reads as the empty list
 * `[]`, so no row is rewritten and reverting leaves no data behind.
 *
 * @category Migration
 * @package  OCA\LaunchPad\Migration
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-36
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
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
 * Adds the hierarchies column to the dashboards.
 *
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-36
 */
class Version002017Date20261121000000 extends SimpleMigrationStep {

	/**
	 * Add the nullable `hierarchies` text column, once.
	 *
	 * @param IOutput $output        The output.
	 * @param Closure $schemaClosure Returns the schema.
	 * @param array   $options       The options.
	 *
	 * @return ISchemaWrapper|null The changed schema, or null when there is nothing to change.
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) $output and $options are required by SimpleMigrationStep.
	 *
	 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-36
	 */
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options,
	): ?ISchemaWrapper {
		// @var ISchemaWrapper $schema.
		$schema = $schemaClosure();

		if ($schema->hasTable('launchpad_dashboards') === false) {
			return null;
		}

		$table = $schema->getTable('launchpad_dashboards');
		if ($table->hasColumn('hierarchies') === true) {
			return null;
		}

		$table->addColumn(
			'hierarchies',
			Types::TEXT,
			[
				'notnull' => false,
				'default' => null,
				'comment' => 'JSON list of the dashboard hierarchy descriptors; NULL reads as the empty list.',
			]
		);

		return $schema;
	}//end changeSchema()
}//end class
