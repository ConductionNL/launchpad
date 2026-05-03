<?php

/**
 * DashboardTableBuilder
 *
 * Builder for the dashboards database table schema.
 *
 * @category  Migration
 * @package   OCA\MyDash\Migration
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\MyDash\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;

/**
 * Builder for the dashboards database table schema.
 */
class DashboardTableBuilder
{
    /**
     * Create the mydash_dashboards table.
     *
     * @param ISchemaWrapper $schema The schema wrapper.
     *
     * @return void
     */
    public static function create(ISchemaWrapper $schema): void
    {
        if ($schema->hasTable(tableName: 'mydash_dashboards') === true) {
            return;
        }

        $table = $schema->createTable(tableName: 'mydash_dashboards');

        self::addColumns(table: $table);
        self::addIndexes(table: $table);
    }//end create()

    /**
     * Add columns to the dashboards table.
     *
     * @param \Doctrine\DBAL\Schema\Table $table The table instance.
     *
     * @return void
     */
    private static function addColumns($table): void
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
            name: 'uuid',
            typeName: Types::STRING,
            options: [
                'notnull' => true,
                'length'  => 36,
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
        // Owned by the frontend `dashboard-icons` capability — see
        // `lib/Db/Dashboard.php::$icon` for the legal value classes.
        $table->addColumn(
            name: 'icon',
            typeName: Types::STRING,
            options: [
                'notnull' => false,
                'length'  => 2000,
                'comment' => 'Dashboard icon: registry key (dashboard-icons) or upload URL; NULL = default.',
            ]
        );
        $table->addColumn(
            name: 'type',
            typeName: Types::STRING,
            options: [
                'notnull' => true,
                'length'  => 20,
                'default' => 'user',
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
            name: 'based_on_template',
            typeName: Types::BIGINT,
            options: [
                'notnull'  => false,
                'unsigned' => true,
            ]
        );
        $table->addColumn(
            name: 'grid_columns',
            typeName: Types::INTEGER,
            options: [
                'notnull' => true,
                'default' => 12,
            ]
        );
        $table->addColumn(
            name: 'permission_level',
            typeName: Types::STRING,
            options: [
                'notnull' => true,
                'length'  => 20,
                'default' => 'full',
            ]
        );
        $table->addColumn(
            name: 'target_groups',
            typeName: Types::TEXT,
            options: [
                'notnull' => false,
            ]
        );
        $table->addColumn(
            name: 'is_default',
            typeName: Types::SMALLINT,
            options: [
                'notnull'  => true,
                'default'  => 0,
                'unsigned' => true,
            ]
        );
        $table->addColumn(
            name: 'is_active',
            typeName: Types::SMALLINT,
            options: [
                'notnull'  => true,
                'default'  => 0,
                'unsigned' => true,
            ]
        );
        // Per-dashboard comments toggle (REQ-CMNT-007). NULL = inherit
        // global setting; 1 = force on; 0 = force off.
        $table->addColumn(
            name: 'comments_enabled',
            typeName: Types::SMALLINT,
            options: [
                'notnull'  => false,
                'default'  => null,
                'unsigned' => true,
                'comment'  => 'Per-dashboard comments toggle: NULL = inherit global, 1 = on, 0 = off.',
            ]
        );
        $table->addColumn(
            name: 'created_at',
            typeName: Types::DATETIME,
            options: [
                'notnull' => true,
            ]
        );
        $table->addColumn(
            name: 'updated_at',
            typeName: Types::DATETIME,
            options: [
                'notnull' => true,
            ]
        );
    }//end addColumns()

    /**
     * Add indexes to the dashboards table.
     *
     * @param \Doctrine\DBAL\Schema\Table $table The table instance.
     *
     * @return void
     */
    private static function addIndexes($table): void
    {
        $table->setPrimaryKey(columnNames: ['id']);
        $table->addUniqueIndex(
            columnNames: ['uuid'],
            indexName: 'mydash_dashboard_uuid'
        );
        $table->addIndex(
            columnNames: ['user_id'],
            indexName: 'mydash_dashboard_user'
        );
        $table->addIndex(
            columnNames: ['type'],
            indexName: 'mydash_dashboard_type'
        );
        $table->addIndex(
            columnNames: ['user_id', 'is_active'],
            indexName: 'mydash_dashboard_active'
        );
        $table->addIndex(
            columnNames: ['type', 'group_id'],
            indexName: 'mydash_dash_type_group'
        );
    }//end addIndexes()

    /**
     * Apply the dashboard-tree hierarchy schema (REQ-DASH-023..030) to an
     * existing `mydash_dashboards` table.
     *
     * Adds the three nullable columns (`parent_uuid`, `slug`, `sort_order`)
     * plus the supporting indexes used by `DashboardTreeService` for
     * sibling lookups, ordered traversal, and the per-parent slug
     * uniqueness guarantee. Idempotent — every check is `hasColumn` /
     * `hasIndex` first.
     *
     * @param \Doctrine\DBAL\Schema\Table $table The mydash_dashboards table.
     *
     * @return void
     */
    public static function addTreeColumns($table): void
    {
        if ($table->hasColumn(name: 'parent_uuid') === false) {
            $table->addColumn(
                name: 'parent_uuid',
                typeName: Types::STRING,
                options: [
                    'notnull' => false,
                    'length'  => 36,
                ]
            );
        }

        if ($table->hasColumn(name: 'slug') === false) {
            $table->addColumn(
                name: 'slug',
                typeName: Types::STRING,
                options: [
                    'notnull' => false,
                    'length'  => 128,
                ]
            );
        }

        if ($table->hasColumn(name: 'sort_order') === false) {
            $table->addColumn(
                name: 'sort_order',
                typeName: Types::INTEGER,
                options: [
                    'notnull' => true,
                    'default' => 0,
                ]
            );
        }

        if ($table->hasIndex(name: 'mydash_dash_parent') === false) {
            $table->addIndex(
                columnNames: ['parent_uuid'],
                indexName: 'mydash_dash_parent'
            );
        }

        if ($table->hasIndex(name: 'mydash_dash_parent_slug') === false) {
            // Composite (parent_uuid, slug) supports per-parent slug
            // uniqueness lookups (REQ-DASH-024) and child-by-slug lookups
            // during path resolution (REQ-DASH-027). NULL parent_uuid is
            // accepted by both MySQL/MariaDB and PostgreSQL in non-unique
            // composite indexes — the uniqueness rule is enforced at the
            // service layer because composite NULL semantics differ
            // between drivers (sqlite ⇢ NULL = NULL, postgres ⇢ NULL ≠ NULL).
            $table->addIndex(
                columnNames: ['parent_uuid', 'slug'],
                indexName: 'mydash_dash_parent_slug'
            );
        }

        if ($table->hasIndex(name: 'mydash_dash_sort') === false) {
            $table->addIndex(
                columnNames: ['parent_uuid', 'sort_order'],
                indexName: 'mydash_dash_sort'
            );
        }
    }//end addTreeColumns()

    /**
     * Apply the dashboard publication-state schema (REQ-DASH-031..037) to
     * an existing `mydash_dashboards` table.
     *
     * Adds three nullable / defaulted columns (`publication_status`,
     * `publish_at`, `published_at`) plus the `(user_id, publication_status)`
     * composite index used by the visibility filter in
     * `DashboardMapper`. The `publication_status` column is declared with
     * `DEFAULT 'published'` so pre-existing rows are backfilled to
     * `'published'` automatically by the database engine — no explicit
     * `UPDATE` statement is required (REQ-DASH-035, design D1). New
     * dashboards created post-migration receive `'draft'` via application
     * logic in `DashboardFactory::create()` instead of relying on the
     * column default. Idempotent — every check is `hasColumn` /
     * `hasIndex` first.
     *
     * @param \Doctrine\DBAL\Schema\Table $table The mydash_dashboards table.
     *
     * @return void
     */
    public static function addPublicationColumns($table): void
    {
        if ($table->hasColumn(name: 'publication_status') === false) {
            $table->addColumn(
                name: 'publication_status',
                typeName: Types::STRING,
                options: [
                    'notnull' => true,
                    'length'  => 20,
                    'default' => 'published',
                    'comment' => 'Dashboard publication status (draft|published|scheduled). REQ-DASH-031.',
                ]
            );
        }

        if ($table->hasColumn(name: 'publish_at') === false) {
            $table->addColumn(
                name: 'publish_at',
                typeName: Types::DATETIME,
                options: [
                    'notnull' => false,
                    'comment' => 'Scheduled publication timestamp; used when publication_status = scheduled.',
                ]
            );
        }

        if ($table->hasColumn(name: 'published_at') === false) {
            $table->addColumn(
                name: 'published_at',
                typeName: Types::DATETIME,
                options: [
                    'notnull' => false,
                    'comment' => 'First-publication timestamp; preserved across unpublish. REQ-DASH-032.',
                ]
            );
        }

        if ($table->hasIndex(name: 'mydash_dash_user_pubstatus') === false) {
            $table->addIndex(
                columnNames: ['user_id', 'publication_status'],
                indexName: 'mydash_dash_user_pubstatus'
            );
        }
    }//end addPublicationColumns()

    /**
     * Apply the per-dashboard footer-override schema (REQ-FTR-006) to an
     * existing `mydash_dashboards` table.
     *
     * Adds two columns: `dashboard_footer_mode` (VARCHAR(16), default
     * `'inherit'`) selecting one of `inherit | hidden | custom`, and
     * `dashboard_footer_html` (TEXT, nullable) holding the
     * dashboard-specific HTML when mode is `custom`. Pre-existing rows
     * materialise as `inherit` automatically via the column default —
     * no explicit backfill required (footer-customization design D2).
     * Idempotent — every column is checked with `hasColumn` first.
     *
     * @param \Doctrine\DBAL\Schema\Table $table The mydash_dashboards table.
     *
     * @return void
     */
    public static function addFooterColumns($table): void
    {
        if ($table->hasColumn(name: 'dashboard_footer_mode') === false) {
            $table->addColumn(
                name: 'dashboard_footer_mode',
                typeName: Types::STRING,
                options: [
                    'notnull' => true,
                    'length'  => 16,
                    'default' => 'inherit',
                    'comment' => 'Per-dashboard footer mode (inherit|hidden|custom). REQ-FTR-006.',
                ]
            );
        }

        if ($table->hasColumn(name: 'dashboard_footer_html') === false) {
            $table->addColumn(
                name: 'dashboard_footer_html',
                typeName: Types::TEXT,
                options: [
                    'notnull' => false,
                    'comment' => 'Sanitised dashboard-specific footer HTML; only used when dashboard_footer_mode=custom.',
                ]
            );
        }
    }//end addFooterColumns()
}//end class
