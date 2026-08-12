<?php

/**
 * Version001007Date20260501120000
 *
 * Creates the two tables backing the role-feature-permissions capability:
 *   - launchpad_role_feat_perms
 *   - launchpad_role_layout_def
 * (See REQ-RFP-001 .. REQ-RFP-010 in
 *  openspec/changes/role-based-content/specs/role-feature-permissions/spec.md)
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
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migration creating the role-feature-permissions tables.
 */
class Version001007Date20260501120000 extends SimpleMigrationStep {
	/**
	 * Create both `launchpad_role_feat_perms` and `launchpad_role_layout_def`.
	 *
	 * @param IOutput $output The migration output handler.
	 * @param Closure $schemaClosure Returns an ISchemaWrapper.
	 * @param array $options The migration options (unused).
	 *
	 * @return ISchemaWrapper|null The modified schema or null if no changes.
	 */
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options,
	): ?ISchemaWrapper {
		$schema = $schemaClosure();

		$hadFeaturePerms = $schema->hasTable(tableName: 'launchpad_role_feat_perms');
		$hadLayoutDefs = $schema->hasTable(tableName: 'launchpad_role_layout_def');

		if ($hadFeaturePerms === true && $hadLayoutDefs === true) {
			return null;
		}

		RoleFeaturePermissionTableBuilder::create(schema: $schema);
		RoleLayoutDefaultTableBuilder::create(schema: $schema);

		return $schema;
	}//end changeSchema()
}//end class
