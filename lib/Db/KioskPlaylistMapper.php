<?php

/**
 * KioskPlaylistMapper
 *
 * QBMapper for mydash_kiosk_playlists. Covers token lookup (active-only),
 * per-creator listing, admin listing, and soft-revoke. The computed public
 * URL is hydrated onto every returned entity via IURLGenerator.
 *
 * @category  Database
 * @package   OCA\LaunchPad\Db
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-2
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Db;

use DateTime;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IURLGenerator;

/**
 * Mapper for KioskPlaylist entities.
 *
 * @extends QBMapper<KioskPlaylist>
 *
 * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-2
 */
class KioskPlaylistMapper extends QBMapper
{
    /**
     * Constructor.
     *
     * @param IDBConnection $db           The database connection.
     * @param IURLGenerator $urlGenerator URL generator for computing kiosk URLs.
     */
    public function __construct(
        IDBConnection $db,
        private readonly IURLGenerator $urlGenerator,
    ) {
        parent::__construct(
            db: $db,
            tableName: 'mydash_kiosk_playlists',
            entityClass: KioskPlaylist::class
        );
    }//end __construct()

    /**
     * Populate the computed URL on a playlist entity.
     *
     * @param KioskPlaylist $playlist The playlist to hydrate.
     *
     * @return KioskPlaylist The same playlist with URL set.
     */
    private function hydrateUrl(KioskPlaylist $playlist): KioskPlaylist
    {
        if ($playlist->getToken() !== null) {
            $playlist->setUrl(
                $this->urlGenerator->linkToRouteAbsolute(
                    routeName: 'mydash.kiosk.render',
                    arguments: ['token' => $playlist->getToken()]
                )
            );
        }

        return $playlist;
    }//end hydrateUrl()

    /**
     * Find an active (non-revoked) playlist by its unique token.
     *
     * @param string $token The playlist token.
     *
     * @return KioskPlaylist
     *
     * @throws DoesNotExistException When no active playlist matches the token.
     *
     * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-2
     */
    public function findByToken(string $token): KioskPlaylist
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select(selects: '*')
            ->from(from: $this->getTableName())
            ->where(
                $qb->expr()->eq(
                    x: 'token',
                    y: $qb->createNamedParameter(value: $token)
                )
            )
            ->andWhere($qb->expr()->isNull(x: 'revoked_at'));

        // @phpstan-ignore-next-line
        $playlist = $this->findEntity(query: $qb);
        return $this->hydrateUrl(playlist: $playlist);
    }//end findByToken()

    /**
     * Find an active playlist by its primary key.
     *
     * @param int $id The playlist primary key.
     *
     * @return KioskPlaylist
     *
     * @throws DoesNotExistException When no active playlist matches the id.
     *
     * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-2
     */
    public function findById(int $id): KioskPlaylist
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select(selects: '*')
            ->from(from: $this->getTableName())
            ->where(
                $qb->expr()->eq(
                    x: 'id',
                    y: $qb->createNamedParameter(
                        value: $id,
                        type: IQueryBuilder::PARAM_INT
                    )
                )
            )
            ->andWhere($qb->expr()->isNull(x: 'revoked_at'));

        // @phpstan-ignore-next-line
        $playlist = $this->findEntity(query: $qb);
        return $this->hydrateUrl(playlist: $playlist);
    }//end findById()

    /**
     * Find all active playlists created by a given user.
     *
     * @param string $createdBy The creator user ID.
     *
     * @return KioskPlaylist[]
     *
     * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-2
     */
    public function findByCreator(string $createdBy): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select(selects: '*')
            ->from(from: $this->getTableName())
            ->where(
                $qb->expr()->eq(
                    x: 'created_by',
                    y: $qb->createNamedParameter(value: $createdBy)
                )
            )
            ->andWhere($qb->expr()->isNull(x: 'revoked_at'))
            ->orderBy(sort: 'created_at', order: 'DESC');

        $playlists = $this->findEntities(query: $qb);
        return array_map(
            callback: fn (KioskPlaylist $playlist) => $this->hydrateUrl(playlist: $playlist),
            array: $playlists
        );
    }//end findByCreator()

    /**
     * Find all active playlists (admin scope).
     *
     * @return KioskPlaylist[]
     *
     * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-2
     */
    public function findAllActive(): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select(selects: '*')
            ->from(from: $this->getTableName())
            ->where($qb->expr()->isNull(x: 'revoked_at'))
            ->orderBy(sort: 'created_at', order: 'DESC');

        $playlists = $this->findEntities(query: $qb);
        return array_map(
            callback: fn (KioskPlaylist $playlist) => $this->hydrateUrl(playlist: $playlist),
            array: $playlists
        );
    }//end findAllActive()

    /**
     * Soft-revoke a playlist by setting revokedAt to now.
     *
     * Idempotent: if revokedAt is already set, this is a no-op.
     *
     * @param int $id The playlist primary key.
     *
     * @return void
     *
     * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-2
     */
    public function softRevoke(int $id): void
    {
        $now = (new DateTime())->format('Y-m-d H:i:s');

        $qb = $this->db->getQueryBuilder();
        $qb->update(update: $this->getTableName())
            ->set(
                key: 'revoked_at',
                value: $qb->createNamedParameter(value: $now)
            )
            ->where(
                $qb->expr()->eq(
                    x: 'id',
                    y: $qb->createNamedParameter(
                        value: $id,
                        type: IQueryBuilder::PARAM_INT
                    )
                )
            )
            ->andWhere($qb->expr()->isNull(x: 'revoked_at'));

        $qb->executeStatement();
    }//end softRevoke()
}//end class
