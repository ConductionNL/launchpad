<?php

/**
 * DashboardTableBuilder
 *
 * Builder for the dashboards database table schema.
 *
 * @category  Migration
 * @package   OCA\LaunchPad\Migration
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;

/**
 * Builder for the dashboards database table schema.
 */
class DashboardTableBuilder
{
    /**
     * Create the launchpad_dashboards table.
     *
     * @param ISchemaWrapper $schema The schema wrapper.
     *
     * @return void
     */
    public static function create(ISchemaWrapper $schema): void
    {
        if ($schema->hasTable('launchpad_dashboards') === true) {
            return;
        }

        $table = $schema->createTable('launchpad_dashboards');

        self::addColumns(table: $table);
        self::addIndexes(table: $table);
        self::addTemplateDiscoveryColumns(table: $table);
    }//end create()

    /**
     * Add columns to the dashboards table.
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
        self::addOwnershipColumns(table: $table);
        self::addLayoutColumns(table: $table);
        self::addFlagColumns(table: $table);
        self::addTimestampColumns(table: $table);
    }//end addColumns()

    /**
     * Add the identity / presentation columns (id, uuid, name,
     * description, icon).
     *
     * @param \Doctrine\DBAL\Schema\Table $table The table instance.
     *
     * @return void
     */
    private static function addIdentityColumns($table): void
    {
        $table->addColumn(
            'id',
            Types::BIGINT,
            [
                'autoincrement' => true,
                'notnull'       => true,
                'unsigned'      => true,
            ]
        );
        $table->addColumn(
            'uuid',
            Types::STRING,
            [
                'notnull' => true,
                'length'  => 36,
            ]
        );
        $table->addColumn(
            'name',
            Types::STRING,
            [
                'notnull' => true,
                'length'  => 255,
            ]
        );
        $table->addColumn(
            'description',
            Types::TEXT,
            [
                'notnull' => false,
            ]
        );
        // Owned by the frontend `dashboard-icons` capability — see
        // `lib/Db/Dashboard.php::$icon` for the legal value classes.
        $table->addColumn(
            'icon',
            Types::STRING,
            [
                'notnull' => false,
                'length'  => 2000,
                'comment' => 'Dashboard icon: registry key (dashboard-icons) or upload URL; NULL = default.',
            ]
        );
    }//end addIdentityColumns()

    /**
     * Add the ownership / provenance columns (type, user_id, group_id,
     * based_on_template).
     *
     * @param \Doctrine\DBAL\Schema\Table $table The table instance.
     *
     * @return void
     */
    private static function addOwnershipColumns($table): void
    {
        $table->addColumn(
            'type',
            Types::STRING,
            [
                'notnull' => true,
                'length'  => 20,
                'default' => 'user',
            ]
        );
        $table->addColumn(
            'user_id',
            Types::STRING,
            [
                'notnull' => false,
                'length'  => 64,
            ]
        );
        $table->addColumn(
            'group_id',
            Types::STRING,
            [
                'notnull' => false,
                'length'  => 64,
            ]
        );
        $table->addColumn(
            'based_on_template',
            Types::BIGINT,
            [
                'notnull'  => false,
                'unsigned' => true,
            ]
        );
    }//end addOwnershipColumns()

    /**
     * Add the layout / access columns (grid_columns, permission_level,
     * target_groups).
     *
     * @param \Doctrine\DBAL\Schema\Table $table The table instance.
     *
     * @return void
     */
    private static function addLayoutColumns($table): void
    {
        $table->addColumn(
            'grid_columns',
            Types::INTEGER,
            [
                'notnull' => true,
                'default' => 12,
            ]
        );
        $table->addColumn(
            'permission_level',
            Types::STRING,
            [
                'notnull' => true,
                'length'  => 20,
                'default' => 'full',
            ]
        );
        $table->addColumn(
            'target_groups',
            Types::TEXT,
            [
                'notnull' => false,
            ]
        );
    }//end addLayoutColumns()

    /**
     * Add the boolean-flag columns (is_default, is_active,
     * comments_enabled).
     *
     * @param \Doctrine\DBAL\Schema\Table $table The table instance.
     *
     * @return void
     */
    private static function addFlagColumns($table): void
    {
        $table->addColumn(
            'is_default',
            Types::SMALLINT,
            [
                'notnull'  => true,
                'default'  => 0,
                'unsigned' => true,
            ]
        );
        $table->addColumn(
            'is_active',
            Types::SMALLINT,
            [
                'notnull'  => true,
                'default'  => 0,
                'unsigned' => true,
            ]
        );
        // Per-dashboard comments toggle (REQ-CMNT-007). NULL = inherit
        // global setting; 1 = force on; 0 = force off.
        $table->addColumn(
            'comments_enabled',
            Types::SMALLINT,
            [
                'notnull'  => false,
                'default'  => null,
                'unsigned' => true,
                'comment'  => 'Per-dashboard comments toggle: NULL = inherit global, 1 = on, 0 = off.',
            ]
        );
    }//end addFlagColumns()

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
            'created_at',
            Types::DATETIME,
            [
                'notnull' => true,
            ]
        );
        $table->addColumn(
            'updated_at',
            Types::DATETIME,
            [
                'notnull' => true,
            ]
        );
    }//end addTimestampColumns()

    /**
     * Add indexes to the dashboards table.
     *
     * @param \Doctrine\DBAL\Schema\Table $table The table instance.
     *
     * @return void
     */
    private static function addIndexes($table): void
    {
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(
            ['uuid'],
            'launchpad_dashboard_uuid'
        );
        $table->addIndex(
            ['user_id'],
            'launchpad_dashboard_user'
        );
        $table->addIndex(
            ['type'],
            'launchpad_dashboard_type'
        );
        $table->addIndex(
            ['user_id', 'is_active'],
            'launchpad_dashboard_active'
        );
        $table->addIndex(
            ['type', 'group_id'],
            'launchpad_dash_type_group'
        );
    }//end addIndexes()

    /**
     * Apply the GroupFolder storage backend schema (REQ-GFSB-002) to an
     * existing `launchpad_dashboards` table.
     *
     * Adds two nullable columns: `content` (LONGTEXT, JSON blob used by the
     * `db` storage backend) and `locale` (VARCHAR(16), optional locale code
     * routing GroupFolder reads/writes to a locale-specific sub-path).
     * Both columns are nullable — pre-existing rows with no content simply
     * keep NULL until explicitly written by the storage layer. Idempotent.
     *
     * @param \Doctrine\DBAL\Schema\Table $table The launchpad_dashboards table.
     *
     * @return void
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-6
     */
    public static function addContentStorageColumns($table): void
    {
        if ($table->hasColumn('content') === false) {
            $table->addColumn(
                'content',
                Types::TEXT,
                [
                    'notnull' => false,
                    'comment' => 'Dashboard content JSON (used by db storage backend). REQ-GFSB-002.',
                ]
            );
        }

        if ($table->hasColumn('locale') === false) {
            $table->addColumn(
                'locale',
                Types::STRING,
                [
                    'notnull' => false,
                    'length'  => 16,
                    'comment' => 'Optional locale code for GroupFolder path routing. REQ-GFSB-004.',
                ]
            );
        }
    }//end addContentStorageColumns()

    /**
     * Apply the dashboard-tree hierarchy schema (REQ-DASH-023..030) to an
     * existing `launchpad_dashboards` table.
     *
     * Adds the three nullable columns (`parent_uuid`, `slug`, `sort_order`)
     * plus the supporting indexes used by `DashboardTreeService` for
     * sibling lookups, ordered traversal, and the per-parent slug
     * uniqueness guarantee. Idempotent — every check is `hasColumn` /
     * `hasIndex` first.
     *
     * @param \Doctrine\DBAL\Schema\Table $table The launchpad_dashboards table.
     *
     * @return void
     */
    public static function addTreeColumns($table): void
    {
        if ($table->hasColumn('parent_uuid') === false) {
            $table->addColumn(
                'parent_uuid',
                Types::STRING,
                [
                    'notnull' => false,
                    'length'  => 36,
                ]
            );
        }

        if ($table->hasColumn('slug') === false) {
            $table->addColumn(
                'slug',
                Types::STRING,
                [
                    'notnull' => false,
                    'length'  => 128,
                ]
            );
        }

        if ($table->hasColumn('sort_order') === false) {
            $table->addColumn(
                'sort_order',
                Types::INTEGER,
                [
                    'notnull' => true,
                    'default' => 0,
                ]
            );
        }

        if ($table->hasIndex('launchpad_dash_parent') === false) {
            $table->addIndex(
                ['parent_uuid'],
                'launchpad_dash_parent'
            );
        }

        if ($table->hasIndex('launchpad_dash_parent_slug') === false) {
            // Composite (parent_uuid, slug) supports per-parent slug
            // uniqueness lookups (REQ-DASH-024) and child-by-slug lookups
            // during path resolution (REQ-DASH-027). NULL parent_uuid is
            // accepted by both MySQL/MariaDB and PostgreSQL in non-unique
            // composite indexes — the uniqueness rule is enforced at the
            // service layer because composite NULL semantics differ
            // between drivers (sqlite ⇢ NULL = NULL, postgres ⇢ NULL ≠ NULL).
            $table->addIndex(
                ['parent_uuid', 'slug'],
                'launchpad_dash_parent_slug'
            );
        }

        if ($table->hasIndex('launchpad_dash_sort') === false) {
            $table->addIndex(
                ['parent_uuid', 'sort_order'],
                'launchpad_dash_sort'
            );
        }
    }//end addTreeColumns()

    /**
     * Apply the dashboard publication-state schema (REQ-DASH-031..037) to
     * an existing `launchpad_dashboards` table.
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
     * @param \Doctrine\DBAL\Schema\Table $table The launchpad_dashboards table.
     *
     * @return void
     */
    public static function addPublicationColumns($table): void
    {
        if ($table->hasColumn('publication_status') === false) {
            $table->addColumn(
                'publication_status',
                Types::STRING,
                [
                    'notnull' => true,
                    'length'  => 20,
                    'default' => 'published',
                    'comment' => 'Dashboard publication status (draft|published|scheduled). REQ-DASH-031.',
                ]
            );
        }

        if ($table->hasColumn('publish_at') === false) {
            $table->addColumn(
                'publish_at',
                Types::DATETIME,
                [
                    'notnull' => false,
                    'comment' => 'Scheduled publication timestamp; used when publication_status = scheduled.',
                ]
            );
        }

        if ($table->hasColumn('published_at') === false) {
            $table->addColumn(
                'published_at',
                Types::DATETIME,
                [
                    'notnull' => false,
                    'comment' => 'First-publication timestamp; preserved across unpublish. REQ-DASH-032.',
                ]
            );
        }

        if ($table->hasIndex('launchpad_dash_user_pub') === false) {
            $table->addIndex(
                ['user_id', 'publication_status'],
                'launchpad_dash_user_pub'
            );
        }
    }//end addPublicationColumns()

    /**
     * Apply the per-dashboard footer-override schema (REQ-FTR-006) to an
     * existing `launchpad_dashboards` table.
     *
     * Adds two columns: `dashboard_footer_mode` (VARCHAR(16), default
     * `'inherit'`) selecting one of `inherit | hidden | custom`, and
     * `dashboard_footer_html` (TEXT, nullable) holding the
     * dashboard-specific HTML when mode is `custom`. Pre-existing rows
     * materialise as `inherit` automatically via the column default —
     * no explicit backfill required (footer-customization design D2).
     * Idempotent — every column is checked with `hasColumn` first.
     *
     * @param \Doctrine\DBAL\Schema\Table $table The launchpad_dashboards table.
     *
     * @return void
     */
    public static function addFooterColumns($table): void
    {
        if ($table->hasColumn('dashboard_footer_mode') === false) {
            $table->addColumn(
                'dashboard_footer_mode',
                Types::STRING,
                [
                    'notnull' => true,
                    'length'  => 16,
                    'default' => 'inherit',
                    'comment' => 'Per-dashboard footer mode (inherit|hidden|custom). REQ-FTR-006.',
                ]
            );
        }

        if ($table->hasColumn('dashboard_footer_html') === false) {
            $table->addColumn(
                'dashboard_footer_html',
                Types::TEXT,
                [
                    'notnull' => false,
                    'comment' => 'Sanitised dashboard-specific footer HTML; only used when dashboard_footer_mode=custom.',
                ]
            );
        }
    }//end addFooterColumns()

    /**
     * Apply the template-discovery schema (REQ-TMPL-014..017) to an
     * existing `launchpad_dashboards` table.
     *
     * Adds three nullable metadata columns (`template_category`,
     * `template_description`, `template_preview_image`) plus the
     * `(type, template_category)` composite index used by the gallery
     * filter path in `DashboardMapper::findAllTemplatesForGallery()`.
     * The base `WHERE type = 'admin_template'` query path already
     * benefits from the existing single-column `type` index. Idempotent —
     * every check is `hasColumn` / `hasIndex` first.
     *
     * @param \Doctrine\DBAL\Schema\Table $table The launchpad_dashboards table.
     *
     * @return void
     */
    public static function addTemplateDiscoveryColumns($table): void
    {
        if ($table->hasColumn('template_category') === false) {
            $table->addColumn(
                'template_category',
                Types::STRING,
                [
                    'notnull' => false,
                    'length'  => 64,
                    'comment' => 'Free-form gallery category for admin templates. REQ-TMPL-016.',
                ]
            );
        }

        if ($table->hasColumn('template_description') === false) {
            $table->addColumn(
                'template_description',
                Types::TEXT,
                [
                    'notnull' => false,
                    'comment' => 'Long-form gallery description for admin templates. REQ-TMPL-016.',
                ]
            );
        }

        if ($table->hasColumn('template_preview_image') === false) {
            $table->addColumn(
                'template_preview_image',
                Types::TEXT,
                [
                    'notnull' => false,
                    'comment' => 'Preview image URL stored via the resource-uploads pattern. REQ-TMPL-017.',
                ]
            );
        }

        if ($table->hasIndex('launchpad_tpl_type_cat') === false) {
            $table->addIndex(
                ['type', 'template_category'],
                'launchpad_tpl_type_cat'
            );
        }
    }//end addTemplateDiscoveryColumns()
}//end class
