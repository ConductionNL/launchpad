<?php

/**
 * Version001004Date20260601000000
 *
 * Migration adding the public_shares table for anonymous dashboard sharing.
 *
 * @category  Migration
 * @package   OCA\MyDash\Migration
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\MyDash\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Creates the mydash_public_shares table.
 *
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-1
 */
class Version001004Date20260601000000 extends SimpleMigrationStep
{
    /**
     * Create mydash_public_shares table.
     *
     * @param IOutput $output        Migration output handler.
     * @param Closure $schemaClosure Returns an ISchemaWrapper.
     * @param array   $options       Migration options.
     *
     * @return ISchemaWrapper|null
     *
     * @spec openspec/changes/dashboard-public-share/tasks.md#task-1
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function changeSchema(
        IOutput $output,
        Closure $schemaClosure,
        array $options
    ): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if ($schema->hasTable('mydash_public_shares') === false) {
            $table = $schema->createTable('mydash_public_shares');

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
                'dashboard_uuid',
                Types::STRING,
                [
                    'notnull' => true,
                    'length'  => 36,
                    'comment' => 'UUID of the shared dashboard.',
                ]
            );
            $table->addColumn(
                'token',
                Types::STRING,
                [
                    'notnull' => true,
                    'length'  => 64,
                    'comment' => 'URL-safe random access token (64 bytes).',
                ]
            );
            $table->addColumn(
                'password_hash',
                Types::STRING,
                [
                    'notnull' => false,
                    'length'  => 255,
                    'default' => null,
                    'comment' => 'BCrypt hash of share password; NULL means no password.',
                ]
            );
            $table->addColumn(
                'expires_at',
                Types::DATETIME,
                [
                    'notnull' => false,
                    'default' => null,
                    'comment' => 'Optional expiry timestamp; NULL means never expires.',
                ]
            );
            $table->addColumn(
                'created_by',
                Types::STRING,
                [
                    'notnull' => true,
                    'length'  => 64,
                    'comment' => 'Nextcloud user ID who created the share.',
                ]
            );
            $table->addColumn(
                'created_at',
                Types::DATETIME,
                ['notnull' => true]
            );
            $table->addColumn(
                'revoked_at',
                Types::DATETIME,
                [
                    'notnull' => false,
                    'default' => null,
                    'comment' => 'Soft-delete timestamp; NULL means still active.',
                ]
            );
            $table->addColumn(
                'view_count',
                Types::INTEGER,
                [
                    'notnull' => true,
                    'default' => 0,
                    'comment' => 'Debounced view counter.',
                ]
            );
            $table->addColumn(
                'last_viewed_at',
                Types::DATETIME,
                [
                    'notnull' => false,
                    'default' => null,
                    'comment' => 'Timestamp of the most recent debounced view.',
                ]
            );

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['token'], 'mydash_pshares_token_unique');
            $table->addIndex(
                ['dashboard_uuid', 'revoked_at'],
                'mydash_pshares_dash_revoked'
            );
        }//end if

        return $schema;
    }//end changeSchema()
}//end class
