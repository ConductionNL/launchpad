<?php

/**
 * DashboardVersionTableBuilder
 *
 * Builder for the `mydash_dashboard_versions` schema. Created by the
 * `dashboard-versioning` change (REQ-VERS-001..009).
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
 * Builder for the dashboard versions database table schema.
 */
class DashboardVersionTableBuilder
{
    /**
     * Create the mydash_dashboard_versions table.
     *
     * Columns:
     *   - id              BIGINT PK auto-increment
     *   - dashboard_uuid  STRING(36) NOT NULL
     *   - version_number  BIGINT NOT NULL
     *   - snapshot_json   TEXT NOT NULL (MEDIUMTEXT on MySQL via the
     *                                    `OCP\DB\Types::TEXT` mapping)
     *   - created_by      STRING(64) NOT NULL
     *   - created_at      DATETIME NOT NULL
     *   - note            STRING(500) NULL
     *
     * Indexes:
     *   - mydash_dvers_uuid_num : UNIQUE(dashboard_uuid, version_number)
     *   - mydash_dvers_uuid_ts  : (dashboard_uuid, created_at)
     *
     * @param ISchemaWrapper $schema The schema wrapper.
     *
     * @return void
     */
    public static function create(ISchemaWrapper $schema): void
    {
        if ($schema->hasTable(tableName: 'mydash_dashboard_versions') === true) {
            return;
        }

        $table = $schema->createTable(
            tableName: 'mydash_dashboard_versions'
        );

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
            name: 'dashboard_uuid',
            typeName: Types::STRING,
            options: [
                'notnull' => true,
                'length'  => 36,
            ]
        );
        $table->addColumn(
            name: 'version_number',
            typeName: Types::BIGINT,
            options: [
                'notnull'  => true,
                'unsigned' => true,
            ]
        );
        $table->addColumn(
            name: 'snapshot_json',
            typeName: Types::TEXT,
            options: ['notnull' => true]
        );
        $table->addColumn(
            name: 'created_by',
            typeName: Types::STRING,
            options: [
                'notnull' => true,
                'length'  => 64,
            ]
        );
        $table->addColumn(
            name: 'created_at',
            typeName: Types::DATETIME,
            options: ['notnull' => true]
        );
        $table->addColumn(
            name: 'note',
            typeName: Types::STRING,
            options: [
                'notnull' => false,
                'length'  => 500,
            ]
        );

        $table->setPrimaryKey(columnNames: ['id']);
        $table->addUniqueIndex(
            columnNames: ['dashboard_uuid', 'version_number'],
            indexName: 'mydash_dvers_uuid_num'
        );
        $table->addIndex(
            columnNames: ['dashboard_uuid', 'created_at'],
            indexName: 'mydash_dvers_uuid_ts'
        );
    }//end create()
}//end class
