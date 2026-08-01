<?php

/**
 * Version002007Date20260724000000
 *
 * Migration that creates the `launchpad_tile_clicks` table — daily
 * aggregate click counters for the tile usage-analytics capability
 * (REQ-TANLT-001..005). One row per `(placement_uuid, click_bucket)`
 * enforced by a composite unique index. No per-event rows are
 * persisted; unique-actor dedup happens entirely in the cache layer,
 * reusing the existing `UniqueViewerDedup` salted-daily-hash mechanism
 * (REQ-TANLT-002) so no per-user-per-event hashes ever reach the
 * database. This is a strict downward extension of the
 * dashboard-view-analytics capability — see
 * `Version001019Date20260502130000` for the sibling table this one
 * mirrors.
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

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Create the `launchpad_tile_clicks` aggregate table
 * (REQ-TANLT-001..005).
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter) `changeSchema()` overrides
 *  {@see SimpleMigrationStep}; Nextcloud fixes the signature, so the unused
 *  `$output` / `$options` parameters cannot be dropped.
 * @SuppressWarnings(PHPMD.StaticAccess)          {@see TileClicksTableBuilder} is a
 *  stateless schema-builder helper. Migration steps are instantiated by
 *  Nextcloud's migrator, not the DI container, so a static call is the only
 *  way to share the table definition with the test suite.
 */
class Version002007Date20260724000000 extends SimpleMigrationStep
{
    /**
     * Create the daily-aggregate tile-clicks table.
     *
     * @param IOutput $output        The migration output handler.
     * @param Closure $schemaClosure The schema closure returns an
     *                               ISchemaWrapper.
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

        TileClicksTableBuilder::create(schema: $schema);

        return $schema;
    }//end changeSchema()
}//end class
