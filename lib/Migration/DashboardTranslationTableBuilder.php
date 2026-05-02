<?php

/**
 * DashboardTranslationTableBuilder
 *
 * Builder for the oc_mydash_dashboard_translations database table schema
 * — per-language content variants for dashboards. REQ-DASH-038.
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
 * Builder for the dashboard_translations database table schema.
 */
class DashboardTranslationTableBuilder
{
    /**
     * Create the mydash_dashboard_translations table.
     *
     * Idempotent — returns immediately when the table already exists.
     *
     * @param ISchemaWrapper $schema The schema wrapper.
     *
     * @return void
     */
    public static function create(ISchemaWrapper $schema): void
    {
        if ($schema->hasTable(tableName: 'mydash_dashboard_translations') === true) {
            return;
        }

        $table = $schema->createTable(
            tableName: 'mydash_dashboard_translations'
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
            name: 'language_code',
            typeName: Types::STRING,
            options: [
                'notnull' => true,
                'length'  => 16,
                'comment' => 'ISO 639-1 base code (nl, en, de, fr); normalised by mapper.',
            ]
        );
        $table->addColumn(
            name: 'name',
            typeName: Types::STRING,
            options: [
                'notnull' => false,
                'length'  => 255,
            ]
        );
        $table->addColumn(
            name: 'description',
            typeName: Types::TEXT,
            options: ['notnull' => false]
        );
        $table->addColumn(
            name: 'widget_tree_json',
            typeName: Types::TEXT,
            options: [
                'notnull' => false,
                'comment' => 'Localised widget tree JSON — mirrors oc_mydash_dashboards shape.',
            ]
        );
        $table->addColumn(
            name: 'is_primary',
            typeName: Types::SMALLINT,
            options: [
                'notnull'  => true,
                'default'  => 0,
                'unsigned' => true,
                'comment'  => 'Exactly one row per dashboard MUST have is_primary=1 (service-enforced).',
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
            options: ['notnull' => false]
        );

        $table->setPrimaryKey(columnNames: ['id']);
        $table->addIndex(
            columnNames: ['dashboard_uuid'],
            indexName: 'mydash_trans_dash_idx'
        );
        $table->addUniqueIndex(
            columnNames: ['dashboard_uuid', 'language_code'],
            indexName: 'mydash_trans_unique_idx'
        );
        $table->addIndex(
            columnNames: ['dashboard_uuid', 'is_primary'],
            indexName: 'mydash_trans_primary_idx'
        );
    }//end create()
}//end class
