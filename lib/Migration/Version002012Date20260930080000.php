<?php

/**
 * Version002012Date20260930080000
 *
 * Adds `launchpad_profile_values`: the values of the custom profile fields
 * an administrator defines, and a mirror of the searchable standard profile
 * fields with their visibility scope (widgets-people-expertise-and-fields,
 * REQ-PEX-001..004). One row per value, so every tag is its own row.
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
 * @spec openspec/specs/people-widget/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Create the profile values table.
 *
 * @spec openspec/specs/people-widget/spec.md
 */
class Version002012Date20260930080000 extends SimpleMigrationStep {
	/**
	 * Create the table when it is not already there.
	 *
	 * @param IOutput $output Migration output.
	 * @param Closure $schemaClosure Schema closure (provides ISchemaWrapper).
	 * @param array $options Migration options (unused).
	 *
	 * @return ISchemaWrapper|null The modified schema, or null when unchanged.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options,
	): ?ISchemaWrapper {
		// @var ISchemaWrapper $schema.
		$schema = $schemaClosure();

		if ($schema->hasTable('launchpad_profile_values') === true) {
			return null;
		}

		$table = $schema->createTable('launchpad_profile_values');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
		$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('field_key', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('value', Types::TEXT, ['notnull' => true]);
		$table->addColumn(
			'value_search',
			Types::TEXT,
			[
				'notnull' => true,
				'comment' => 'Lower-cased value, matched with LIKE by the people search.',
			]
		);
		$table->addColumn(
			'source',
			Types::STRING,
			[
				'notnull' => true,
				'length' => 16,
				'comment' => 'self, ldap or nextcloud (a mirrored standard field).',
			]
		);
		$table->addColumn(
			'scope',
			Types::STRING,
			[
				'notnull' => false,
				'length' => 32,
				'comment' => 'Nextcloud visibility scope of a mirrored standard field; NULL for custom fields.',
			]
		);
		$table->addColumn('updated_at', Types::DATETIME, ['notnull' => false]);

		$table->setPrimaryKey(['id']);
		$table->addIndex(['user_id'], 'launchpad_pv_user');
		$table->addIndex(['field_key'], 'launchpad_pv_field');

		return $schema;
	}//end changeSchema()
}//end class
