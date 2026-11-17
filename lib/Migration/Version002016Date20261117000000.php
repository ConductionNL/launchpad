<?php

/**
 * Version002016Date20261117000000
 *
 * Adds `calculated_fields` to `launchpad_widget_placements`: the JSON list of
 * a widget's calculated fields (widget-calculated-fields, tasks 21 and 22).
 * The column is nullable; existing widgets keep NULL, which the entity reads
 * as the empty list `[]`, so no row is rewritten and reverting leaves no data
 * behind.
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
 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-22
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
 * Adds the calculated-fields column to the widget placements.
 *
 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-22
 */
class Version002016Date20261117000000 extends SimpleMigrationStep {

	/**
	 * Add the nullable `calculated_fields` text column, once.
	 *
	 * @param IOutput $output        The output.
	 * @param Closure $schemaClosure Returns the schema.
	 * @param array   $options       The options.
	 *
	 * @return ISchemaWrapper|null The changed schema, or null when there is nothing to change.
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-22
	 */
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options,
	): ?ISchemaWrapper {
		// @var ISchemaWrapper $schema.
		$schema = $schemaClosure();

		if ($schema->hasTable('launchpad_widget_placements') === false) {
			return null;
		}

		$table = $schema->getTable('launchpad_widget_placements');
		if ($table->hasColumn('calculated_fields') === true) {
			return null;
		}

		$table->addColumn(
			'calculated_fields',
			Types::TEXT,
			[
				'notnull' => false,
				'default' => null,
				'comment' => 'JSON list of the widget calculated fields; NULL reads as the empty list.',
			]
		);

		return $schema;
	}//end changeSchema()
}//end class
