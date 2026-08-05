<?php

/**
 * RoleLayoutDefaultTableBuilder
 *
 * Builder for the `launchpad_role_layout_def` schema (REQ-RFP-002).
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

use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;

/**
 * Schema builder for the role-layout-defaults table.
 */
class RoleLayoutDefaultTableBuilder
{
    /**
     * Create the table when missing.
     *
     * @param ISchemaWrapper $schema The schema wrapper.
     *
     * @return void
     */
    public static function create(ISchemaWrapper $schema): void
    {
        if ($schema->hasTable(tableName: 'launchpad_role_layout_def') === true) {
            return;
        }

        $table = $schema->createTable(tableName: 'launchpad_role_layout_def');

        self::addColumns(table: $table);
        self::addIndexes(table: $table);
    }//end create()

    /**
     * Add columns to the table.
     *
     * Delegates to one helper per column group; the call order below is
     * the physical column order of the created table.
     *
     * @param \Doctrine\DBAL\Schema\Table $table The table instance.
     *
     * @return void
     */
    private static function addColumns($table): void
    {
        self::addIdentityColumns(table: $table);
        self::addGridColumns(table: $table);
        self::addTimestampColumns(table: $table);
    }//end addColumns()

    /**
     * Add the identity / association columns (id, name, description,
     * group_id, widget_id).
     *
     * @param \Doctrine\DBAL\Schema\Table $table The table instance.
     *
     * @return void
     */
    private static function addIdentityColumns($table): void
    {
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
            name: 'name',
            typeName: Types::STRING,
            options: [
                'notnull' => true,
                'length'  => 255,
            ]
        );
        $table->addColumn(
            name: 'description',
            typeName: Types::TEXT,
            options: [
                'notnull' => false,
            ]
        );
        $table->addColumn(
            name: 'group_id',
            typeName: Types::STRING,
            options: [
                'notnull' => true,
                'length'  => 255,
            ]
        );
        $table->addColumn(
            name: 'widget_id',
            typeName: Types::STRING,
            options: [
                'notnull' => true,
                'length'  => 255,
            ]
        );
    }//end addIdentityColumns()

    /**
     * Add the grid-geometry / ordering columns (grid_x, grid_y,
     * grid_width, grid_height, sort_order, is_compulsory).
     *
     * @param \Doctrine\DBAL\Schema\Table $table The table instance.
     *
     * @return void
     */
    private static function addGridColumns($table): void
    {
        $table->addColumn(
            name: 'grid_x',
            typeName: Types::INTEGER,
            options: [
                'notnull' => true,
                'default' => 0,
            ]
        );
        $table->addColumn(
            name: 'grid_y',
            typeName: Types::INTEGER,
            options: [
                'notnull' => true,
                'default' => 0,
            ]
        );
        $table->addColumn(
            name: 'grid_width',
            typeName: Types::INTEGER,
            options: [
                'notnull' => true,
                'default' => 4,
            ]
        );
        $table->addColumn(
            name: 'grid_height',
            typeName: Types::INTEGER,
            options: [
                'notnull' => true,
                'default' => 4,
            ]
        );
        $table->addColumn(
            name: 'sort_order',
            typeName: Types::INTEGER,
            options: [
                'notnull' => true,
                'default' => 0,
            ]
        );
        $table->addColumn(
            name: 'is_compulsory',
            typeName: Types::SMALLINT,
            options: [
                'notnull' => true,
                'default' => 0,
            ]
        );
    }//end addGridColumns()

    /**
     * Add the audit-timestamp columns (created_at, updated_at).
     *
     * @param \Doctrine\DBAL\Schema\Table $table The table instance.
     *
     * @return void
     */
    private static function addTimestampColumns($table): void
    {
        $table->addColumn(
            name: 'created_at',
            typeName: Types::STRING,
            options: [
                'notnull' => false,
                'length'  => 32,
            ]
        );
        $table->addColumn(
            name: 'updated_at',
            typeName: Types::STRING,
            options: [
                'notnull' => false,
                'length'  => 32,
            ]
        );
    }//end addTimestampColumns()

    /**
     * Add primary key + uniqueness index on (group_id, widget_id).
     *
     * @param \Doctrine\DBAL\Schema\Table $table The table instance.
     *
     * @return void
     */
    private static function addIndexes($table): void
    {
        $table->setPrimaryKey(columnNames: ['id']);
        $table->addUniqueIndex(
            columnNames: ['group_id', 'widget_id'],
            indexName: 'launchpad_rld_group_widget'
        );
        $table->addIndex(
            columnNames: ['group_id'],
            indexName: 'launchpad_rld_group'
        );
    }//end addIndexes()
}//end class
