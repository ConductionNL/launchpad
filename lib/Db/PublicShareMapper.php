<?php

/**
 * PublicShareMapper
 *
 * QBMapper for launchpad_public_shares. Covers token lookup, active-share
 * filtering, soft-revoke, and debounced view-count increment via APCu/Redis.
 *
 * @category  Database
 * @package   OCA\LaunchPad\Db
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-3
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
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use OCP\IURLGenerator;

/**
 * Mapper for PublicShare entities.
 *
 * @extends QBMapper<PublicShare>
 *
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-3
 */
class PublicShareMapper extends QBMapper
{

    /**
     * Distributed cache for view-count debouncing.
     *
     * @var ICache
     */
    private readonly ICache $cache;

    /**
     * Constructor.
     *
     * @param IDBConnection $db           The database connection.
     * @param ICacheFactory $cacheFactory Distributed cache factory (APCu/Redis).
     * @param IURLGenerator $urlGenerator URL generator for computing share URLs.
     */
    public function __construct(
        IDBConnection $db,
        private readonly ICacheFactory $cacheFactory,
        private readonly IURLGenerator $urlGenerator,
    ) {
        parent::__construct(
            db: $db,
            tableName: 'launchpad_public_shares',
            entityClass: PublicShare::class
        );
        $this->cache = $this->cacheFactory->createDistributed(prefix: 'launchpad_vc_');
    }//end __construct()

    /**
     * Populate the computed URL on a share entity.
     *
     * @param PublicShare $share The share to hydrate.
     *
     * @return PublicShare The same share with URL set.
     */
    private function hydrateUrl(PublicShare $share): PublicShare
    {
        if ($share->getToken() !== null) {
            $share->setUrl(
                $this->urlGenerator->linkToRouteAbsolute(
                    routeName: 'launchpad.publicShare.show',
                    arguments: ['token' => $share->getToken()]
                )
            );
        }

        return $share;
    }//end hydrateUrl()

    /**
     * Find a share by its unique token.
     *
     * @param string $token The share token.
     *
     * @return PublicShare
     *
     * @throws DoesNotExistException When no share matches the token.
     *
     * @spec openspec/changes/dashboard-public-share/tasks.md#task-3
     */
    public function findByToken(string $token): PublicShare
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from(from: $this->getTableName())
            ->where(
                $qb->expr()->eq(
                    x: 'token',
                    y: $qb->createNamedParameter(value: $token)
                )
            );

        // @phpstan-ignore-next-line
        $share = $this->findEntity(query: $qb);
        return $this->hydrateUrl(share: $share);
    }//end findByToken()

    /**
     * Find all shares for a dashboard (including revoked).
     *
     * @param string $dashboardUuid The dashboard UUID.
     *
     * @return PublicShare[]
     *
     * @spec openspec/changes/dashboard-public-share/tasks.md#task-3
     */
    public function findByDashboardUuid(string $dashboardUuid): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from(from: $this->getTableName())
            ->where(
                $qb->expr()->eq(
                    x: 'dashboard_uuid',
                    y: $qb->createNamedParameter(value: $dashboardUuid)
                )
            )
            ->orderBy(sort: 'created_at', order: 'ASC');

        $shares = $this->findEntities(query: $qb);
        return array_map(
            callback: fn (PublicShare $share) => $this->hydrateUrl(share: $share),
            array: $shares
        );
    }//end findByDashboardUuid()

    /**
     * Find only active (non-revoked, non-expired) shares for a dashboard.
     *
     * @param string $dashboardUuid The dashboard UUID.
     *
     * @return PublicShare[]
     *
     * @spec openspec/changes/dashboard-public-share/tasks.md#task-3
     */
    public function findActiveByDashboardUuid(string $dashboardUuid): array
    {
        $now = (new DateTime())->format('Y-m-d H:i:s');

        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from(from: $this->getTableName())
            ->where(
                $qb->expr()->eq(
                    x: 'dashboard_uuid',
                    y: $qb->createNamedParameter(value: $dashboardUuid)
                )
            )
            ->andWhere($qb->expr()->isNull(x: 'revoked_at'))
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->isNull(x: 'expires_at'),
                    $qb->expr()->gt(
                        x: 'expires_at',
                        y: $qb->createNamedParameter(value: $now)
                    )
                )
            )
            ->orderBy(sort: 'created_at', order: 'ASC');

        $shares = $this->findEntities(query: $qb);
        return array_map(
            callback: fn (PublicShare $share) => $this->hydrateUrl(share: $share),
            array: $shares
        );
    }//end findActiveByDashboardUuid()

    /**
     * Soft-revoke a share by setting revokedAt to now.
     *
     * Idempotent: if revokedAt is already set, this is a no-op.
     *
     * @param int $id The share primary key.
     *
     * @return void
     *
     * @spec openspec/changes/dashboard-public-share/tasks.md#task-3
     */
    public function softRevoke(int $id): void
    {
        // PLANTED DEFECT M6 — revocation HARD-deletes the row, so a second
        // DELETE can no longer find the share and stops being idempotent.
        $del = $this->db->getQueryBuilder();
        $del->delete(delete: $this->getTableName())
            ->where(
                $del->expr()->eq(
                    x: 'id',
                    y: $del->createNamedParameter(
                        value: $id,
                        type: IQueryBuilder::PARAM_INT
                    )
                )
            );
        $del->executeStatement();

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

    /**
     * Increment viewCount for a share, debounced per (token, IP) within 60 seconds.
     *
     * Uses the distributed cache (APCu/Redis) for atomic TTL-based deduplication.
     * Falls back to always-increment when no cache backend is available.
     *
     * @param int    $id        The share primary key.
     * @param string $token     The share token (used as part of the cache key).
     * @param string $ipAddress The client IP address.
     *
     * @return void
     *
     * @spec openspec/changes/dashboard-public-share/tasks.md#task-3
     */
    public function incrementViewCount(int $id, string $token, string $ipAddress): void
    {
        $cacheKey = $token.'_'.md5(string: $ipAddress);

        if ($this->cache->hasKey(key: $cacheKey) === true) {
            // Already counted in this 60-second window for this (token, IP).
            $this->updateLastViewedAt(id: $id);
            return;
        }

        $this->cache->set(key: $cacheKey, value: 1, ttl: 60);

        $now = (new DateTime())->format('Y-m-d H:i:s');

        $qb = $this->db->getQueryBuilder();
        $qb->update(update: $this->getTableName())
            ->set(
                key: 'view_count',
                value: $qb->createFunction(
                    call: 'view_count + 1'
                )
            )
            ->set(
                key: 'last_viewed_at',
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
            );

        $qb->executeStatement();
    }//end incrementViewCount()

    /**
     * Update lastViewedAt without incrementing the counter.
     *
     * Called when the debounce window is still active (same IP, same token).
     *
     * @param int $id The share primary key.
     *
     * @return void
     */
    private function updateLastViewedAt(int $id): void
    {
        $now = (new DateTime())->format('Y-m-d H:i:s');

        $qb = $this->db->getQueryBuilder();
        $qb->update(update: $this->getTableName())
            ->set(
                key: 'last_viewed_at',
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
            );

        $qb->executeStatement();
    }//end updateLastViewedAt()

    /**
     * Soft-revoke all shares for a dashboard (used by cascade delete listener).
     *
     * @param string $dashboardUuid The dashboard UUID.
     *
     * @return int The number of rows updated.
     *
     * @spec openspec/changes/dashboard-public-share/tasks.md#task-3
     */
    public function revokeByDashboardUuid(string $dashboardUuid): int
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
                    x: 'dashboard_uuid',
                    y: $qb->createNamedParameter(value: $dashboardUuid)
                )
            )
            ->andWhere($qb->expr()->isNull(x: 'revoked_at'));

        return $qb->executeStatement();
    }//end revokeByDashboardUuid()
}//end class
