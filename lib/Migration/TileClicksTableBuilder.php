<?php

/**
 * TileClicksTableBuilder
 *
 * Builder for the tile usage-analytics database table schema
 * (REQ-TANLT-001). Creates the `launchpad_tile_clicks` table that
 * stores **daily aggregate** click counts per widget placement (tile) —
 * one row per `(placementUuid, clickBucket)` pair. Per-event rows are
 * deliberately not persisted; unique-actor dedup happens entirely in
 * the cache layer, reusing {@see \OCA\LaunchPad\Service\UniqueViewerDedup}
 * (REQ-TANLT-002) so no per-user-per-event hashes ever reach the
 * database. Mirrors `DashboardViewsTableBuilder` one grain down.
 *
 * @category  Migration
 * @package   OCA\LaunchPad\Migration
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;

/**
 * Builder for the tile usage-analytics database table schema.
 */
class TileClicksTableBuilder
{
    /**
     * Create the `launchpad_tile_clicks` table (REQ-TANLT-001).
     *
     * Idempotent — early-returns when the table already exists. The
     * composite unique index on `(placement_uuid, click_bucket)` is
     * the one-row-per-(tile, day) invariant; the secondary
     * `click_bucket` index speeds up date-range scans for the admin
     * top-tiles / breakdown / export endpoints.
     *
     * @param ISchemaWrapper $schema The schema wrapper.
     *
     * @return void
     */
    public static function create(ISchemaWrapper $schema): void
    {
        if ($schema->hasTable('launchpad_tile_clicks') === true) {
            return;
        }

        $table = $schema->createTable('launchpad_tile_clicks');

        self::addColumns(table: $table);
        self::addIndexes(table: $table);
    }//end create()

    /**
     * Add columns to the `launchpad_tile_clicks` table.
     *
     * @param \Doctrine\DBAL\Schema\Table $table The table instance.
     *
     * @return void
     */
    private static function addColumns($table): void
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
            'placement_uuid',
            Types::STRING,
            [
                'notnull' => true,
                'length'  => 36,
                'comment' => 'The widget placement (tile) this aggregate row belongs to (REQ-TANLT-001).',
            ]
        );
        $table->addColumn(
            'dashboard_uuid',
            Types::STRING,
            [
                'notnull' => true,
                'length'  => 36,
                'comment' => 'The dashboard the placement belongs to (denormalised for breakdown queries).',
            ]
        );
        $table->addColumn(
            'click_bucket',
            Types::DATE,
            [
                'notnull' => true,
                'comment' => 'Calendar date in UTC; one row per (tile, day).',
            ]
        );
        $table->addColumn(
            'click_count',
            Types::INTEGER,
            [
                'notnull'  => true,
                'default'  => 0,
                'unsigned' => true,
                'comment'  => 'Total number of click events on this date.',
            ]
        );
        $table->addColumn(
            'unique_actor_count',
            Types::INTEGER,
            [
                'notnull'  => true,
                'default'  => 0,
                'unsigned' => true,
                'comment'  => 'Distinct actors on this date (cache-deduped, reused salted-daily-hash).',
            ]
        );
    }//end addColumns()

    /**
     * Add indexes to the `launchpad_tile_clicks` table.
     *
     * Composite unique index `(placement_uuid, click_bucket)`
     * enforces the "one row per tile per day" invariant
     * (REQ-TANLT-001). The standalone `click_bucket` index supports
     * the date-range predicates used by the admin report endpoints
     * and the shared retention-purge job (REQ-TANLT-004,
     * REQ-TANLT-005).
     *
     * @param \Doctrine\DBAL\Schema\Table $table The table instance.
     *
     * @return void
     */
    private static function addIndexes($table): void
    {
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(
            ['placement_uuid', 'click_bucket'],
            'launchpad_tanlt_uniq'
        );
        $table->addIndex(
            ['click_bucket'],
            'launchpad_tanlt_bucket'
        );
    }//end addIndexes()
}//end class
