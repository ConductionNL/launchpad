<?php

/**
 * Version002011Date20260929230000
 *
 * Adds `unpublish_at` to `launchpad_dashboards`: the moment a published
 * dashboard comes down (sharing-dashboard-schedule-screen REQ-SCHEDUI-002).
 * Nullable, so every existing dashboard stays up as it is.
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
 * @spec openspec/changes/sharing-dashboard-schedule-screen/specs/dashboards/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add the take-down time to dashboards.
 *
 * @spec openspec/changes/sharing-dashboard-schedule-screen/specs/dashboards/spec.md
 */
class Version002011Date20260929230000 extends SimpleMigrationStep {
	/**
	 * Add the column when it is not already there.
	 *
	 * @param IOutput $output Migration output.
	 * @param Closure $schemaClosure Schema closure (provides ISchemaWrapper).
	 * @param array $options Migration options (unused).
	 *
	 * @return ISchemaWrapper|null The modified schema, or null when unchanged.
	 *
	 * @spec openspec/changes/sharing-dashboard-schedule-screen/specs/dashboards/spec.md
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
		if ($table->hasColumn('unpublish_at') === true) {
			return null;
		}

		$table->addColumn(
			'unpublish_at',
			Types::DATETIME,
			[
				'notnull' => false,
				'comment' => 'When a published dashboard comes down; NULL keeps it up.',
			]
		);

		return $schema;
	}//end changeSchema()
}//end class
