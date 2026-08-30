<?php

/**
 * Version002005Date20260710000000
 *
 * Repairs the role-feature-permissions tables on instances that created them
 * under the pre-rename long names. `Version001007` originally shipped table
 * builders that created `launchpad_role_feature_perms` and
 * `launchpad_role_layout_defaults`; the builders were later shortened to
 * `launchpad_role_feat_perms` / `launchpad_role_layout_def` (the names the
 * mappers query — see RoleFeaturePermissionMapper / RoleLayoutDefaultMapper),
 * but because `Version001007` was already recorded as run, the rename never
 * reached instances that had already created the long-named tables. Those
 * instances 500 on every Roles & Permissions admin call
 * (`relation "oc_launchpad_role_feat_perms" does not exist`).
 *
 * This migration renames the legacy tables to the abbreviated names, preserving
 * their rows. It is a no-op on fresh installs (the abbreviated tables already
 * exist) and on instances that never created the legacy tables. The two schemas
 * are column-identical, so a table rename is sufficient. See REQ-RFP-001 in
 * openspec/changes/role-based-content/specs/role-feature-permissions/spec.md.
 *
 * @category  Migration
 * @package   OCA\LaunchPad\Migration
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Rename the legacy long-named role tables to the abbreviated mapper names.
 *
 * @spec openspec/changes/role-based-content/specs/role-feature-permissions/spec.md
 */
class Version002005Date20260710000000 extends SimpleMigrationStep {
	/**
	 * Legacy → abbreviated table-name pairs (unprefixed).
	 *
	 * @var array<string, string>
	 */
	private const RENAMES = [
		'launchpad_role_feature_perms' => 'launchpad_role_feat_perms',
		'launchpad_role_layout_defaults' => 'launchpad_role_layout_def',
	];

	/**
	 * Constructor.
	 *
	 * @param IDBConnection $connection The database connection.
	 * @param IConfig $config The system config (for the table prefix).
	 */
	public function __construct(
		private readonly IDBConnection $connection,
		private readonly IConfig $config,
	) {
	}//end __construct()

	/**
	 * No declarative schema change — the rename is applied as raw DDL in
	 * {@see self::preSchemaChange()} so it runs before any later schema diff.
	 *
	 * @param IOutput $output Migration output.
	 * @param Closure $schemaClosure Schema closure.
	 * @param array $options Options.
	 *
	 * @return ISchemaWrapper|null Always null.
	 *
	 * @spec openspec/changes/role-based-content/specs/role-feature-permissions/spec.md
	 */
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options,
	): ?ISchemaWrapper {
		return null;
	}//end changeSchema()

	/**
	 * Rename each legacy table when — and only when — the legacy table exists
	 * and the abbreviated target does not.
	 *
	 * @param IOutput $output Migration output.
	 * @param Closure $schemaClosure Schema closure.
	 * @param array $options Options.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/role-based-content/specs/role-feature-permissions/spec.md
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$schema = $schemaClosure();
		$prefix = $this->config->getSystemValueString('dbtableprefix', 'oc_');

		foreach (self::RENAMES as $legacy => $target) {
			if ($schema->hasTable(tableName: $target) === true) {
				// Fresh install or already migrated — nothing to do.
				continue;
			}

			if ($schema->hasTable(tableName: $legacy) === false) {
				// Instance never created the legacy table.
				continue;
			}

			// Table names come from the constant allow-list above and are plain
			// lowercase snake_case, so unquoted identifiers are injection-safe
			// and portable: `ALTER TABLE … RENAME TO …` and bare identifiers are
			// accepted by PostgreSQL, MySQL/MariaDB and SQLite alike (quoting
			// styles differ between those engines, so we deliberately omit it).
			$this->connection->executeStatement(
				'ALTER TABLE ' . $prefix . $legacy . ' RENAME TO ' . $prefix . $target
			);
			$output->info('LaunchPad migration: renamed ' . $prefix . $legacy . ' → ' . $prefix . $target . '.');
		}//end foreach
	}//end preSchemaChange()
}//end class
