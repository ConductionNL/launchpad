<?php

/**
 * TileClickMapper
 *
 * Mapper for the tile usage-analytics aggregate table
 * (REQ-TANLT-001..005). Owns the upsert path that increments daily
 * per-tile counters, the date-range scans used by the admin report
 * endpoints, and the retention-purge that keeps the table bounded
 * (reused from the dashboard-view-analytics `PurgeViewsJob` —
 * REQ-TANLT-005). Mirrors `DashboardViewMapper` exactly, one grain
 * down (placement instead of dashboard).
 *
 * @category  Database
 * @package   OCA\LaunchPad\Db
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

namespace OCA\LaunchPad\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * Mapper for tile usage-analytics aggregate rows.
 *
 * @extends QBMapper<TileClick>
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class TileClickMapper extends QBMapper
{
    /**
     * Constructor.
     *
     * @param IDBConnection $db The database connection.
     */
    public function __construct(IDBConnection $db)
    {
        parent::__construct(
            db: $db,
            tableName: 'launchpad_tile_clicks',
            entityClass: TileClick::class
        );
    }//end __construct()

    /**
     * Find the aggregate row for `(placementUuid, clickBucket)`.
     *
     * @param string $placementUuid The placement UUID.
     * @param string $clickBucket   The UTC date `YYYY-MM-DD`.
     *
     * @return TileClick|null The row or `null` when absent.
     */
    public function findByPlacementAndBucket(
        string $placementUuid,
        string $clickBucket
    ): ?TileClick {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from(from: $this->getTableName())
            ->where(
                $qb->expr()->eq(
                    x: 'placement_uuid',
                    y: $qb->createNamedParameter(value: $placementUuid)
                )
            )
            ->andWhere(
                $qb->expr()->eq(
                    x: 'click_bucket',
                    y: $qb->createNamedParameter(value: $clickBucket)
                )
            )
            ->setMaxResults(maxResults: 1);

        try {
            return $this->findEntity(query: $qb);
        } catch (DoesNotExistException) {
            return null;
        }
    }//end findByPlacementAndBucket()

    /**
     * Find the top-N tiles by total click count in an inclusive date
     * range (REQ-TANLT-004). Returns plain assoc rows (not entities)
     * because the result aggregates over many rows per placement.
     *
     * @param string $startDate Inclusive lower bound (`YYYY-MM-DD`).
     * @param string $endDate   Inclusive upper bound (`YYYY-MM-DD`).
     * @param int    $limit     Maximum rows to return.
     *
     * @return array<int, array{placementUuid: string, dashboardUuid: string,
     *   clickCount: int, uniqueActorCount: int}>
     *   Aggregate rows ordered by `clickCount` descending.
     */
    public function findTopTilesInRange(
        string $startDate,
        string $endDate,
        int $limit
    ): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('placement_uuid')
            ->selectAlias(
                $qb->func()->max('dashboard_uuid'),
                'dashboard_uuid'
            )
            ->selectAlias(
                $qb->func()->sum('click_count'),
                'click_count_sum'
            )
            ->selectAlias(
                $qb->func()->sum('unique_actor_count'),
                'unique_actor_sum'
            )
            ->from(from: $this->getTableName())
            ->where(
                $qb->expr()->gte(
                    x: 'click_bucket',
                    y: $qb->createNamedParameter(value: $startDate)
                )
            )
            ->andWhere(
                $qb->expr()->lte(
                    x: 'click_bucket',
                    y: $qb->createNamedParameter(value: $endDate)
                )
            )
            ->groupBy('placement_uuid')
            ->orderBy(sort: 'click_count_sum', order: 'DESC')
            ->setMaxResults(maxResults: $limit);

        $cursor = $qb->executeQuery();
        $rows   = [];
        while (($row = $cursor->fetch()) !== false) {
            $rows[] = [
                'placementUuid'    => (string) $row['placement_uuid'],
                'dashboardUuid'    => (string) $row['dashboard_uuid'],
                'clickCount'       => (int) $row['click_count_sum'],
                'uniqueActorCount' => (int) $row['unique_actor_sum'],
            ];
        }

        $cursor->closeCursor();

        return $rows;
    }//end findTopTilesInRange()

    /**
     * Find every aggregate row for one dashboard's tiles within an
     * inclusive date range, grouped per placement (REQ-TANLT-004 —
     * "per-dashboard tile breakdown").
     *
     * @param string $dashboardUuid The dashboard UUID.
     * @param string $startDate     Inclusive lower bound (`YYYY-MM-DD`).
     * @param string $endDate       Inclusive upper bound (`YYYY-MM-DD`).
     *
     * @return array<int, array{placementUuid: string, clickCount: int,
     *   uniqueActorCount: int}>
     *   Per-tile totals for the dashboard, ordered by `clickCount`
     *   descending.
     */
    public function findByDashboardInRange(
        string $dashboardUuid,
        string $startDate,
        string $endDate
    ): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('placement_uuid')
            ->selectAlias(
                $qb->func()->sum('click_count'),
                'click_count_sum'
            )
            ->selectAlias(
                $qb->func()->sum('unique_actor_count'),
                'unique_actor_sum'
            )
            ->from(from: $this->getTableName())
            ->where(
                $qb->expr()->eq(
                    x: 'dashboard_uuid',
                    y: $qb->createNamedParameter(value: $dashboardUuid)
                )
            )
            ->andWhere(
                $qb->expr()->gte(
                    x: 'click_bucket',
                    y: $qb->createNamedParameter(value: $startDate)
                )
            )
            ->andWhere(
                $qb->expr()->lte(
                    x: 'click_bucket',
                    y: $qb->createNamedParameter(value: $endDate)
                )
            )
            ->groupBy('placement_uuid')
            ->orderBy(sort: 'click_count_sum', order: 'DESC');

        $cursor = $qb->executeQuery();
        $rows   = [];
        while (($row = $cursor->fetch()) !== false) {
            $rows[] = [
                'placementUuid'    => (string) $row['placement_uuid'],
                'clickCount'       => (int) $row['click_count_sum'],
                'uniqueActorCount' => (int) $row['unique_actor_sum'],
            ];
        }

        $cursor->closeCursor();

        return $rows;
    }//end findByDashboardInRange()

    /**
     * Find every aggregate row in an inclusive date range, ordered
     * for CSV export (REQ-TANLT-005).
     *
     * @param string $startDate Inclusive lower bound (`YYYY-MM-DD`).
     * @param string $endDate   Inclusive upper bound (`YYYY-MM-DD`).
     *
     * @return TileClick[] The rows ordered by `(placement_uuid,
     *                     click_bucket)` ascending.
     */
    public function findAllInRange(
        string $startDate,
        string $endDate
    ): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from(from: $this->getTableName())
            ->where(
                $qb->expr()->gte(
                    x: 'click_bucket',
                    y: $qb->createNamedParameter(value: $startDate)
                )
            )
            ->andWhere(
                $qb->expr()->lte(
                    x: 'click_bucket',
                    y: $qb->createNamedParameter(value: $endDate)
                )
            )
            ->orderBy(sort: 'placement_uuid', order: 'ASC')
            ->addOrderBy(sort: 'click_bucket', order: 'ASC');

        return $this->findEntities(query: $qb);
    }//end findAllInRange()

    /**
     * Increment counters for `(placementUuid, clickBucket)`,
     * inserting the row when it does not yet exist (REQ-TANLT-001
     * "one row per tile per day"). No per-event row is ever created —
     * this is the ONLY write path into the table.
     *
     * @param string $placementUuid    The placement UUID.
     * @param string $dashboardUuid    The owning dashboard UUID.
     * @param string $clickBucket      The UTC date `YYYY-MM-DD`.
     * @param int    $clickCountDelta  Increment for `click_count`.
     * @param int    $uniqueCountDelta Increment for
     *                                 `unique_actor_count`.
     *
     * @return TileClick The persisted row after the update.
     */
    public function upsertClick(
        string $placementUuid,
        string $dashboardUuid,
        string $clickBucket,
        int $clickCountDelta,
        int $uniqueCountDelta
    ): TileClick {
        $existing = $this->findByPlacementAndBucket(
            placementUuid: $placementUuid,
            clickBucket: $clickBucket
        );

        if ($existing !== null) {
            $existing->setClickCount(
                $existing->getClickCount() + $clickCountDelta
            );
            $existing->setUniqueActorCount(
                $existing->getUniqueActorCount() + $uniqueCountDelta
            );

            return $this->update(entity: $existing);
        }

        $row = new TileClick();
        $row->setPlacementUuid($placementUuid);
        $row->setDashboardUuid($dashboardUuid);
        $row->setClickBucket($clickBucket);
        $row->setClickCount(max($clickCountDelta, 0));
        $row->setUniqueActorCount(max($uniqueCountDelta, 0));

        return $this->insert(entity: $row);
    }//end upsertClick()

    /**
     * Delete every aggregate row whose `click_bucket` is strictly
     * older than `$beforeDate` (REQ-TANLT-005 — reused by the shared
     * `PurgeViewsJob`).
     *
     * @param string $beforeDate Exclusive cutoff (`YYYY-MM-DD`).
     *
     * @return int The number of rows deleted.
     */
    public function deleteOlderThan(string $beforeDate): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->delete(delete: $this->getTableName())
            ->where(
                $qb->expr()->lt(
                    x: 'click_bucket',
                    y: $qb->createNamedParameter(value: $beforeDate)
                )
            );

        return $qb->executeStatement();
    }//end deleteOlderThan()

    /**
     * Delete every aggregate row for a single placement (used when
     * the parent placement itself is deleted — mirrors
     * `DashboardViewMapper::deleteByDashboard()`).
     *
     * @param string $placementUuid The placement UUID.
     *
     * @return int The number of rows deleted.
     */
    public function deleteByPlacement(string $placementUuid): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->delete(delete: $this->getTableName())
            ->where(
                $qb->expr()->eq(
                    x: 'placement_uuid',
                    y: $qb->createNamedParameter(value: $placementUuid)
                )
            );

        return $qb->executeStatement();
    }//end deleteByPlacement()
}//end class
