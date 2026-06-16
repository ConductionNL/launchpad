<?php

/**
 * Version002002Date20260614000000
 *
 * Migration adding the kiosk-playlists table for unattended wall-display
 * rotation of dashboards. A playlist row carries its own URL-safe token and
 * acts as an anonymous read grant over the dashboards it references.
 *
 * @category  Migration
 * @package   OCA\LaunchPad\Migration
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Creates the mydash_kiosk_playlists table.
 *
 * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-1
 */
class Version002002Date20260614000000 extends SimpleMigrationStep
{
    /**
     * Create mydash_kiosk_playlists table.
     *
     * @param IOutput $output        Migration output handler.
     * @param Closure $schemaClosure Returns an ISchemaWrapper.
     * @param array   $options       Migration options.
     *
     * @return ISchemaWrapper|null
     *
     * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-1
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

        if ($schema->hasTable('mydash_kiosk_playlists') === false) {
            $table = $schema->createTable('mydash_kiosk_playlists');

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
                'name',
                Types::STRING,
                [
                    'notnull' => true,
                    'length'  => 255,
                    'comment' => 'Human-readable playlist name.',
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
                'entries',
                Types::TEXT,
                [
                    'notnull' => true,
                    'comment' => 'JSON array of {dashboardUuid, dwellSeconds}.',
                ]
            );
            $table->addColumn(
                'refresh_seconds',
                Types::INTEGER,
                [
                    'notnull' => true,
                    'default' => 300,
                    'comment' => 'In-place widget refresh interval, clamped [30, 86400].',
                ]
            );
            $table->addColumn(
                'created_by',
                Types::STRING,
                [
                    'notnull' => true,
                    'length'  => 64,
                    'comment' => 'Nextcloud user ID who created the playlist.',
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

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['token'], 'mydash_kiosk_token_unique');
            $table->addIndex(
                ['created_by', 'revoked_at'],
                'mydash_kiosk_creator_revoked'
            );
        }//end if

        return $schema;
    }//end changeSchema()
}//end class
