<?php

/**
 * Version002011Date20260930090000
 *
 * Adds the nullable `unpublish_at` column to `launchpad_dashboards`: the time
 * a published dashboard comes down (REQ-SCHEDUI-002). Read-time only, like
 * `publish_at`.
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
 * @spec openspec/changes/sharing-dashboard-schedule-screen/tasks.md#task-1
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
 * @spec openspec/changes/sharing-dashboard-schedule-screen/tasks.md#task-1
 */
class Version002011Date20260930090000 extends SimpleMigrationStep {
	/**
	 * Add `unpublish_at` when it is not already there.
	 *
	 * @param IOutput $output Migration output.
	 * @param Closure $schemaClosure Schema closure (provides ISchemaWrapper).
	 * @param array $options Migration options (unused).
	 *
	 * @return ISchemaWrapper|null The modified schema, or null when unchanged.
	 *
	 * @spec openspec/changes/sharing-dashboard-schedule-screen/tasks.md#task-1
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
				'comment' => 'Take-down timestamp; a published dashboard past it reads as unpublished. REQ-SCHEDUI-002.',
			]
		);

		return $schema;
	}//end changeSchema()
}//end class
