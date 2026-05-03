<?php

/**
 * DashboardLockTableBuilder
 *
 * Builder for the `mydash_dashboard_locks` database table schema. Owns
 * the column set, primary key and indexes required by REQ-LOCK-001..008.
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
 * Builder for the dashboard_locks database table schema.
 */
class DashboardLockTableBuilder
{
    /**
     * Create the mydash_dashboard_locks table.
     *
     * Schema follows the `dashboard-locking` design (D1, D2):
     *  - `dashboard_uuid` UNIQUE — enforces atomically that only one
     *    lock exists per dashboard at a time (REQ-LOCK-007 contention
     *    guarantee).
     *  - `created_at` is set on first acquire and never updated.
     *  - `updated_at` is bumped by every heartbeat. Expiry is computed
     *    at query time as `updated_at + 15 min`; no `expires_at` column
     *    is stored.
     *  - The `updated_at` index keeps the inline `cleanExpiredLock`
     *    DELETE fast even when many stale rows accumulate.
     *
     * @param ISchemaWrapper $schema The schema wrapper.
     *
     * @return void
     */
    public static function create(ISchemaWrapper $schema): void
    {
        if ($schema->hasTable(tableName: 'mydash_dashboard_locks') === true) {
            return;
        }

        $table = $schema->createTable(tableName: 'mydash_dashboard_locks');

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
            name: 'user_id',
            typeName: Types::STRING,
            options: [
                'notnull' => true,
                'length'  => 64,
            ]
        );
        $table->addColumn(
            name: 'display_name',
            typeName: Types::STRING,
            options: [
                'notnull' => true,
                'length'  => 255,
            ]
        );
        $table->addColumn(
            name: 'created_at',
            typeName: Types::DATETIME,
            options: ['notnull' => true]
        );
        $table->addColumn(
            name: 'updated_at',
            typeName: Types::DATETIME,
            options: ['notnull' => true]
        );

        $table->setPrimaryKey(columnNames: ['id']);
        $table->addUniqueIndex(
            columnNames: ['dashboard_uuid'],
            indexName: 'mydash_lock_dash_unique'
        );
        $table->addIndex(
            columnNames: ['user_id'],
            indexName: 'mydash_lock_user_idx'
        );
        $table->addIndex(
            columnNames: ['updated_at'],
            indexName: 'mydash_lock_updated_idx'
        );
    }//end create()
}//end class
