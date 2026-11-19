<?php

/**
 * Version002015Date20261114130000
 *
 * Adds the app catalogue (launcher-app-catalogue, REQ-ACAT-001..004):
 * `launchpad_catalogue_apps`, the applications an administrator approved,
 * and `launchpad_app_requests`, what users asked for and how it was decided.
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
 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-1
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Create the catalogue and request tables.
 *
 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-1
 */
class Version002015Date20261114130000 extends SimpleMigrationStep {
	/**
	 * Create each table when it is not already there.
	 *
	 * @param IOutput $output Migration output.
	 * @param Closure $schemaClosure Schema closure (provides ISchemaWrapper).
	 * @param array $options Migration options (unused).
	 *
	 * @return ISchemaWrapper|null The modified schema, or null when unchanged.
	 *
	 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-1
	 */
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options,
	): ?ISchemaWrapper {
		// @var ISchemaWrapper $schema.
		$schema  = $schemaClosure();
		$changed = false;

		if ($schema->hasTable('launchpad_catalogue_apps') === false) {
			$this->createCatalogueApps(schema: $schema);
			$changed = true;
		}

		if ($schema->hasTable('launchpad_app_requests') === false) {
			$this->createAppRequests(schema: $schema);
			$changed = true;
		}

		if ($changed === false) {
			return null;
		}

		return $schema;
	}//end changeSchema()

	/**
	 * The approved applications.
	 *
	 * @param ISchemaWrapper $schema The schema.
	 *
	 * @return void
	 */
	private function createCatalogueApps(ISchemaWrapper $schema): void {
		$table = $schema->createTable('launchpad_catalogue_apps');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
		$table->addColumn('uuid', Types::STRING, ['notnull' => true, 'length' => 36]);
		$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('description', Types::TEXT, ['notnull' => false]);
		$table->addColumn('icon', Types::TEXT, ['notnull' => false]);
		$table->addColumn('icon_type', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'class']);
		$table->addColumn(
			'link_type',
			Types::STRING,
			[
				'notnull' => true,
				'length' => 8,
				'comment' => 'app (a Nextcloud app id) or url (a web address).',
			]
		);
		$table->addColumn('link_value', Types::TEXT, ['notnull' => true]);
		$table->addColumn(
			'target_groups',
			Types::TEXT,
			[
				'notnull' => false,
				'comment' => 'JSON list of group ids that see the entry; empty means everyone.',
			]
		);
		$table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		$table->addColumn('created_at', Types::DATETIME, ['notnull' => false]);
		$table->addColumn('updated_at', Types::DATETIME, ['notnull' => false]);

		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['uuid'], 'launchpad_cat_uuid');
	}//end createCatalogueApps()

	/**
	 * The requests for a missing application.
	 *
	 * @param ISchemaWrapper $schema The schema.
	 *
	 * @return void
	 */
	private function createAppRequests(ISchemaWrapper $schema): void {
		$table = $schema->createTable('launchpad_app_requests');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
		$table->addColumn('uuid', Types::STRING, ['notnull' => true, 'length' => 36]);
		$table->addColumn('requested_by', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('app_name', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('purpose', Types::TEXT, ['notnull' => false]);
		$table->addColumn(
			'status',
			Types::STRING,
			[
				'notnull' => true,
				'length' => 16,
				'default' => 'open',
				'comment' => 'open, approved or declined.',
			]
		);
		$table->addColumn('decided_by', Types::STRING, ['notnull' => false, 'length' => 64]);
		$table->addColumn('decision_note', Types::TEXT, ['notnull' => false]);
		$table->addColumn('catalogue_app_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true]);
		$table->addColumn('created_at', Types::DATETIME, ['notnull' => false]);
		$table->addColumn('decided_at', Types::DATETIME, ['notnull' => false]);

		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['uuid'], 'launchpad_areq_uuid');
		$table->addIndex(['requested_by', 'status'], 'launchpad_areq_user_status');
		$table->addIndex(['status'], 'launchpad_areq_status');
	}//end createAppRequests()
}//end class
