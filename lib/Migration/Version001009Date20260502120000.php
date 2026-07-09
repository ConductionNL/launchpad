<?php

/**
 * Version001009Date20260502120000
 *
 * Migration that creates the `launchpad_role_assignments` table introduced
 * by the admin-roles capability. Zero-impact: new table only, no
 * changes to existing schema. Default behaviour (no rows) preserves the
 * existing permission model unchanged. REQ-ROLE-004.
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
 * Create the `launchpad_role_assignments` table (REQ-ROLE-004).
 */
class Version001009Date20260502120000 extends SimpleMigrationStep
{
    /**
     * Create the role-assignments table when it does not yet exist.
     *
     * @param IOutput $output        The migration output handler.
     * @param Closure $schemaClosure The schema closure (returns ISchemaWrapper).
     * @param array   $options       The migration options.
     *
     * @return ISchemaWrapper|null The modified schema or null.
     */
    public function changeSchema(
        IOutput $output,
        Closure $schemaClosure,
        array $options
    ): ?ISchemaWrapper {
        $schema = $schemaClosure();

        RoleAssignmentTableBuilder::create(schema: $schema);

        return $schema;
    }//end changeSchema()
}//end class
