<?php

/**
 * MetadataTablesBuilder
 *
 * Builders for the dashboard metadata-fields capability tables
 * (REQ-MDFL-001..008): the global field-definition registry
 * (`mydash_meta_fields`) and the per-dashboard typed value rows
 * (`mydash_meta_values`).
 *
 * Both tables live behind the `dashboard-metadata-fields`
 * capability spec — see `openspec/specs/dashboard-metadata-fields/spec.md`
 * for the canonical column contract and orphan-tolerance rules.
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
 * Builders for the metadata-field registry and per-dashboard value tables.
 */
class MetadataTablesBuilder
{
    /**
     * Create both metadata tables (idempotent — skip when present).
     *
     * @param ISchemaWrapper $schema The schema wrapper.
     *
     * @return void
     */
    public static function create(ISchemaWrapper $schema): void
    {
        self::createFieldsTable(schema: $schema);
        self::createValuesTable(schema: $schema);
    }//end create()

    /**
     * Create the `mydash_meta_fields` registry table.
     *
     * @param ISchemaWrapper $schema The schema wrapper.
     *
     * @return void
     */
    private static function createFieldsTable(ISchemaWrapper $schema): void
    {
        if ($schema->hasTable(tableName: 'mydash_meta_fields') === true) {
            return;
        }

        $table = $schema->createTable(tableName: 'mydash_meta_fields');

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
            name: 'field_key',
            typeName: Types::STRING,
            options: [
                'notnull' => true,
                'length'  => 64,
            ]
        );
        $table->addColumn(
            name: 'label',
            typeName: Types::STRING,
            options: [
                'notnull' => true,
                'length'  => 255,
            ]
        );
        $table->addColumn(
            name: 'type',
            typeName: Types::STRING,
            options: [
                'notnull' => true,
                'length'  => 20,
            ]
        );
        $table->addColumn(
            name: 'options',
            typeName: Types::TEXT,
            options: [
                'notnull' => false,
                'comment' => 'JSON array of strings; NULL for non-select types.',
            ]
        );
        $table->addColumn(
            name: 'required',
            typeName: Types::SMALLINT,
            options: [
                'notnull' => true,
                'default' => 0,
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
            name: 'created_at',
            typeName: Types::DATETIME,
            options: [
                'notnull' => false,
            ]
        );
        $table->addColumn(
            name: 'updated_at',
            typeName: Types::DATETIME,
            options: [
                'notnull' => false,
            ]
        );

        $table->setPrimaryKey(columnNames: ['id']);
        $table->addUniqueIndex(
            columnNames: ['field_key'],
            indexName: 'mydash_meta_fkey'
        );
        $table->addIndex(
            columnNames: ['sort_order'],
            indexName: 'mydash_meta_forder'
        );
    }//end createFieldsTable()

    /**
     * Create the `mydash_meta_values` per-dashboard value table.
     *
     * @param ISchemaWrapper $schema The schema wrapper.
     *
     * @return void
     */
    private static function createValuesTable(ISchemaWrapper $schema): void
    {
        if ($schema->hasTable(tableName: 'mydash_meta_values') === true) {
            return;
        }

        $table = $schema->createTable(tableName: 'mydash_meta_values');

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
            name: 'field_id',
            typeName: Types::BIGINT,
            options: [
                'notnull'  => true,
                'unsigned' => true,
            ]
        );
        $table->addColumn(
            name: 'value',
            typeName: Types::TEXT,
            options: [
                'notnull' => true,
            ]
        );

        $table->setPrimaryKey(columnNames: ['id']);
        $table->addUniqueIndex(
            columnNames: ['dashboard_uuid', 'field_id'],
            indexName: 'mydash_meta_vunique'
        );
        $table->addIndex(
            columnNames: ['dashboard_uuid'],
            indexName: 'mydash_meta_vdash'
        );
        $table->addIndex(
            columnNames: ['field_id'],
            indexName: 'mydash_meta_vfield'
        );
    }//end createValuesTable()
}//end class
