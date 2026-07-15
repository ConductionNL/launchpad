<?php

/**
 * Version001019Date20260502130000
 *
 * Migration that creates the `launchpad_dashboard_views` table — daily
 * aggregate view-event counters for the dashboard view-analytics
 * capability (REQ-ANLT-001..011). One row per `(dashboard_uuid,
 * view_bucket)` enforced by a composite unique index. No per-event
 * rows are persisted; unique-viewer dedup happens entirely in the
 * cache layer (REQ-ANLT-003) so no per-user-per-event hashes ever
 * reach the database.
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

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Create the `launchpad_dashboard_views` aggregate table
 * (REQ-ANLT-001..011).
 */
class Version001019Date20260502130000 extends SimpleMigrationStep
{
    /**
     * Create the daily-aggregate views table.
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

        DashboardViewsTableBuilder::create(schema: $schema);

        return $schema;
    }//end changeSchema()
}//end class
