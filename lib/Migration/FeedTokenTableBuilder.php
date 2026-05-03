<?php

/**
 * FeedTokenTableBuilder
 *
 * Builder for the feed-tokens database table schema. Materialises the
 * `oc_mydash_feed_tokens` table that backs the per-user RSS / Atom
 * feed-token capability (REQ-FEED-001..009). One row per user
 * (`UNIQUE(user_id)`) plus a fast `token` lookup index for the public
 * `/feed/{token}.xml` endpoint.
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
 * Builder for the dashboard feed-tokens table schema.
 */
class FeedTokenTableBuilder
{
    /**
     * Create the mydash_feed_tokens table when missing.
     *
     * Column layout:
     *  - id           BIGINT PK auto-increment
     *  - user_id      VARCHAR(64) NOT NULL  (UNIQUE — one token per user)
     *  - token        VARCHAR(64) NOT NULL  (indexed for public lookup)
     *  - created_at   DATETIME    NOT NULL
     *  - last_used_at DATETIME    NULLABLE  (touched on every public hit)
     *  - revoked_at   DATETIME    NULLABLE  (soft-revoke flag)
     *
     * @param ISchemaWrapper $schema The schema wrapper.
     *
     * @return void
     */
    public static function create(ISchemaWrapper $schema): void
    {
        if ($schema->hasTable(tableName: 'mydash_feed_tokens') === true) {
            return;
        }

        $table = $schema->createTable(tableName: 'mydash_feed_tokens');

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
            name: 'user_id',
            typeName: Types::STRING,
            options: [
                'notnull' => true,
                'length'  => 64,
            ]
        );
        $table->addColumn(
            name: 'token',
            typeName: Types::STRING,
            options: [
                'notnull' => true,
                'length'  => 64,
            ]
        );
        $table->addColumn(
            name: 'created_at',
            typeName: Types::DATETIME,
            options: ['notnull' => true]
        );
        $table->addColumn(
            name: 'last_used_at',
            typeName: Types::DATETIME,
            options: ['notnull' => false]
        );
        $table->addColumn(
            name: 'revoked_at',
            typeName: Types::DATETIME,
            options: ['notnull' => false]
        );

        $table->setPrimaryKey(columnNames: ['id']);
        $table->addUniqueIndex(
            columnNames: ['user_id'],
            indexName: 'mydash_feed_tok_user_uq'
        );
        $table->addIndex(
            columnNames: ['token'],
            indexName: 'mydash_feed_tok_tok_idx'
        );
    }//end create()
}//end class
