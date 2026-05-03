<?php

/**
 * FeedCacheTableBuilder
 *
 * Builder for the feed-cache database table schema. Materialises the
 * `oc_mydash_feed_cache` table that backs the background-job feed-refresh
 * capability (REQ-FRJ-001..012). One row per distinct external feed URL
 * (`UNIQUE(feed_url)`) holding fetch metadata (ETag, Last-Modified,
 * lastFetchedAt / lastSuccessAt / lastFailureReason) and the cached
 * normalised items as JSON (capped at 50 items, see
 * {@see \OCA\MyDash\Db\FeedCache::encodeItems()}).
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
 * Builder for the dashboard feed-cache table schema.
 */
class FeedCacheTableBuilder
{
    /**
     * Create the mydash_feed_cache table when missing.
     *
     * Column layout:
     *  - id                   BIGINT PK auto-increment
     *  - feed_url             VARCHAR(2048) NOT NULL  (UNIQUE — one row per URL)
     *  - last_fetched_at      DATETIME      NULLABLE  (every tick, even 304)
     *  - last_success_at      DATETIME      NULLABLE  (last 200/304 hit)
     *  - last_failure_reason  TEXT          NULLABLE  (4xx/5xx/timeout/parse)
     *  - etag                 VARCHAR(255)  NULLABLE  (conditional GET)
     *  - last_modified        VARCHAR(255)  NULLABLE  (conditional GET)
     *  - items_json           CLOB          NULLABLE  (capped at 50 items)
     *
     * NOTE on the unique index: most database engines reject a UNIQUE
     * index on a column wider than ~767 bytes. We index `feed_url` with
     * a 191-character key length on MySQL (default UTF8MB4) to stay safe;
     * the `length` option is honoured by Doctrine on engines that need
     * it and ignored by SQLite/Postgres which support full-length unique
     * indexes natively.
     *
     * @param ISchemaWrapper $schema The schema wrapper.
     *
     * @return void
     */
    public static function create(ISchemaWrapper $schema): void
    {
        if ($schema->hasTable(tableName: 'mydash_feed_cache') === true) {
            return;
        }

        $table = $schema->createTable(tableName: 'mydash_feed_cache');

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
            name: 'feed_url',
            typeName: Types::STRING,
            options: [
                'notnull' => true,
                'length'  => 2048,
            ]
        );
        $table->addColumn(
            name: 'last_fetched_at',
            typeName: Types::DATETIME,
            options: ['notnull' => false]
        );
        $table->addColumn(
            name: 'last_success_at',
            typeName: Types::DATETIME,
            options: ['notnull' => false]
        );
        $table->addColumn(
            name: 'last_failure_reason',
            typeName: Types::TEXT,
            options: ['notnull' => false]
        );
        $table->addColumn(
            name: 'etag',
            typeName: Types::STRING,
            options: [
                'notnull' => false,
                'length'  => 255,
            ]
        );
        $table->addColumn(
            name: 'last_modified',
            typeName: Types::STRING,
            options: [
                'notnull' => false,
                'length'  => 255,
            ]
        );
        $table->addColumn(
            name: 'items_json',
            typeName: Types::TEXT,
            options: ['notnull' => false]
        );

        $table->setPrimaryKey(columnNames: ['id']);
        $table->addUniqueIndex(
            columnNames: ['feed_url'],
            indexName: 'mydash_feed_cache_url_uq',
            options: ['lengths' => [191]]
        );
    }//end create()
}//end class
