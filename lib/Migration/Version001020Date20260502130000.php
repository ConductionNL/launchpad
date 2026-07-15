<?php

/**
 * Version001020Date20260502130000
 *
 * Migration that creates the `oc_launchpad_feed_tokens` table backing the
 * per-user RSS / Atom feed-token capability (REQ-FEED-001..009). One row
 * per user (`UNIQUE(user_id)`) plus a fast `token` lookup index for the
 * public `/feed/{token}.xml` endpoint.
 *
 * Migration is purely additive — there are no existing rows to backfill;
 * feeds are opt-in (REQ-FEED-008) and only materialise once a user calls
 * `GET /api/feed/token`.
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
 * Add the feed-tokens table backing the dashboard RSS/Atom capability
 * (REQ-FEED-001..009).
 */
class Version001020Date20260502130000 extends SimpleMigrationStep
{
    /**
     * Create the feed-tokens table.
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

        FeedTokenTableBuilder::create(schema: $schema);

        return $schema;
    }//end changeSchema()
}//end class
