<?php

/**
 * Version001015Date20260502130000
 *
 * Migration creating the `oc_launchpad_dash_versions` table for the
 * `dashboard-versioning` capability (REQ-VERS-001..009). Owns:
 *   - `dashboard_uuid` / `version_number` composite UNIQUE for fast
 *     per-dashboard lookups and monotonic versionNumber enforcement.
 *   - `dashboard_uuid` / `created_at` index for newest-first ordering.
 *
 * Zero-impact: only adds a new table; existing dashboards remain
 * unaffected until their first PUT triggers an automatic snapshot.
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
 * Add the launchpad_dash_versions table (REQ-VERS-001..009).
 */
class Version001015Date20260502130000 extends SimpleMigrationStep
{
    /**
     * Create the dashboard versions table.
     *
     * @param IOutput $output        The migration output handler.
     * @param Closure $schemaClosure The schema closure (returns
     *                               ISchemaWrapper).
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

        DashboardVersionTableBuilder::create(schema: $schema);

        return $schema;
    }//end changeSchema()
}//end class
