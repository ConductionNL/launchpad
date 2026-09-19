<?php

/**
 * Version002010Date20260918184500
 *
 * Adds `launchpad_personal_layers`: one row per person per dashboard, holding
 * the differences between what the organisation composed and what that person
 * arranged for themselves (REQ-DWMS-001, REQ-DWMS-002).
 *
 * A layer is deliberately one row, not one row per placement. The reset in
 * REQ-DWMS-002 deletes the whole arrangement in one action, and a single row
 * is the only shape where that cannot half-succeed.
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
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Create the personal dashboard layer table.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */
class Version002010Date20260918184500 extends SimpleMigrationStep {
	/**
	 * Create the table when it is not already there.
	 *
	 * @param IOutput $output Migration output.
	 * @param Closure $schemaClosure Schema closure (provides ISchemaWrapper).
	 * @param array $options Migration options (unused).
	 *
	 * @return ISchemaWrapper|null The modified schema, or null when unchanged.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options,
	): ?ISchemaWrapper {
		// @var ISchemaWrapper $schema.
		$schema = $schemaClosure();

		if ($schema->hasTable('launchpad_personal_layers') === true) {
			return null;
		}

		$table = $schema->createTable('launchpad_personal_layers');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('dashboard_id', Types::BIGINT, ['notnull' => true]);
		// The overrides and the hidden set are JSON documents rather than
		// columns: what a person may change about a placement is the
		// placement's business, and a new geometry field must not need a
		// migration here.
		$table->addColumn('overrides', Types::TEXT, ['notnull' => false]);
		$table->addColumn('hidden', Types::TEXT, ['notnull' => false]);
		$table->addColumn('updated_at', Types::STRING, ['notnull' => false, 'length' => 32]);

		$table->setPrimaryKey(['id']);
		// One layer per person per dashboard. The uniqueness is the reason a
		// save is an upsert and a reset is a single delete.
		$table->addUniqueIndex(['user_id', 'dashboard_id'], 'lp_pers_layer_uniq');
		$table->addIndex(['dashboard_id'], 'lp_pers_layer_dash');

		return $schema;
	}//end changeSchema()
}//end class
