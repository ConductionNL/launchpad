<?php

/**
 * RoleAssignmentTableBuilder
 *
 * Builder for the `mydash_role_assignments` database table introduced
 * by the admin-roles capability. REQ-ROLE-004.
 *
 * @category  Migration
 * @package   OCA\MyDash\Migration
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 MyDash Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\MyDash\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;

/**
 * Builder for the role-assignments table schema (REQ-ROLE-004).
 *
 * The XOR constraint between user_id and group_id is enforced at the
 * service layer (RoleService::validateTarget). Database-level CHECK
 * constraints are not portable across the SQL dialects Nextcloud
 * supports, so we instead enforce uniqueness via two partial-style
 * indexes (composite (user_id, role) and (group_id, role)) plus the
 * service-layer XOR validation. Either user_id OR group_id is set per
 * row, never both.
 */
class RoleAssignmentTableBuilder
{
    /**
     * Create the `mydash_role_assignments` table when missing.
     *
     * @param ISchemaWrapper $schema The schema wrapper.
     *
     * @return void
     */
    public static function create(ISchemaWrapper $schema): void
    {
        if ($schema->hasTable(tableName: 'mydash_role_assignments') === true) {
            return;
        }

        $table = $schema->createTable(tableName: 'mydash_role_assignments');

        $table->addColumn(
            name: 'id',
            typeName: Types::BIGINT,
            options: [
                'autoincrement' => true,
                'notnull'       => true,
                'unsigned'      => true,
            ]
        );
        $table->addColumn(
            name: 'user_id',
            typeName: Types::STRING,
            options: [
                'notnull' => false,
                'length'  => 64,
            ]
        );
        $table->addColumn(
            name: 'group_id',
            typeName: Types::STRING,
            options: [
                'notnull' => false,
                'length'  => 64,
            ]
        );
        $table->addColumn(
            name: 'role',
            typeName: Types::STRING,
            options: [
                'notnull' => true,
                'length'  => 16,
            ]
        );
        $table->addColumn(
            name: 'assigned_by',
            typeName: Types::STRING,
            options: [
                'notnull' => true,
                'length'  => 64,
            ]
        );
        $table->addColumn(
            name: 'assigned_at',
            typeName: Types::DATETIME,
            options: ['notnull' => true]
        );

        $table->setPrimaryKey(columnNames: ['id']);

        // Lookup indexes for cascade and resolution paths.
        $table->addIndex(
            columnNames: ['user_id'],
            indexName: 'mydash_role_user_idx'
        );
        $table->addIndex(
            columnNames: ['group_id'],
            indexName: 'mydash_role_group_idx'
        );

        // Enforce one assignment per (user, role) and per (group, role).
        // Rows are XOR — only one of user_id / group_id is populated —
        // so a single row never participates in both unique indexes.
        $table->addUniqueIndex(
            columnNames: ['user_id', 'role'],
            indexName: 'mydash_role_user_uniq'
        );
        $table->addUniqueIndex(
            columnNames: ['group_id', 'role'],
            indexName: 'mydash_role_group_uniq'
        );
    }//end create()
}//end class
