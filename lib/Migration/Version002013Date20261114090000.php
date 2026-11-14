<?php

/**
 * Version002013Date20261114090000
 *
 * Adds `version` to `launchpad_widget_placements`: a per-widget counter that
 * every save raises by one. A save made on an older version of a widget is
 * refused, so two editors' changes to the same widget never silently replace
 * each other (building-edit-a-dashboard-together, task 1.2).
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
 * @spec openspec/changes/building-edit-a-dashboard-together/specs/dashboard-co-editing/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Adds the per-widget version column.
 *
 * @spec openspec/changes/building-edit-a-dashboard-together/specs/dashboard-co-editing/spec.md
 */
class Version002013Date20261114090000 extends SimpleMigrationStep {

	/**
	 * Add the column when the placements table lacks it.
	 *
	 * @param IOutput $output        Migration output.
	 * @param Closure $schemaClosure Returns the current schema.
	 * @param array   $options       Migration options.
	 *
	 * @return ISchemaWrapper|null
	 *
	 * @spec openspec/changes/building-edit-a-dashboard-together/specs/dashboard-co-editing/spec.md
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
		if ($table->hasColumn('version') === true) {
			return null;
		}

		$table->addColumn(
			'version',
			Types::INTEGER,
			[
				'notnull' => true,
				'default' => 0,
				'unsigned' => true,
				'comment' => 'Raised by one on every save of the placement; a save on an older version is refused.',
			]
		);

		return $schema;
	}//end changeSchema()
}//end class
