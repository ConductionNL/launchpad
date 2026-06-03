<?php

/**
 * DbContentStorage
 *
 * Database backend for the pluggable dashboard content storage layer.
 *
 * Persists dashboard widget placement content via the existing
 * {@see \OCA\MyDash\Db\WidgetPlacementMapper} and
 * {@see \OCA\MyDash\Db\DashboardMapper}. This is the default backend used
 * when `content_storage = 'db'` (or when no setting is explicitly configured).
 *
 * @category Service
 * @package  OCA\MyDash\Service\DashboardContentStorage
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-2
 */

declare(strict_types=1);

namespace OCA\MyDash\Service\DashboardContentStorage;

use OCA\MyDash\Db\DashboardMapper;
use OCA\MyDash\Db\WidgetPlacementMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Database-backed implementation of the content storage interface.
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-2
 */
class DbContentStorage implements DashboardContentStorageInterface
{

    /**
     * Backend identifier returned in error messages.
     *
     * @var string
     */
    private const BACKEND_NAME = 'db';

    /**
     * Constructor.
     *
     * @param DashboardMapper       $dashboardMapper Dashboard mapper.
     * @param WidgetPlacementMapper $placementMapper Widget placement mapper.
     * @param LoggerInterface       $logger          PSR logger.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-2
     */
    public function __construct(
        private readonly DashboardMapper $dashboardMapper,
        private readonly WidgetPlacementMapper $placementMapper,
        private readonly LoggerInterface $logger,
    ) {
    }//end __construct()

    /**
     * Read widget placement content from the database.
     *
     * Fetches the dashboard entity by UUID to obtain the DB row ID, then
     * fetches all associated WidgetPlacement rows in display order.
     *
     * @param string $dashboardUuid The dashboard UUID.
     *
     * @return array Serialized placement data.
     *
     * @throws DashboardNotFoundException       When the UUID does not exist.
     * @throws DashboardContentStorageException On unexpected DB failure.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-2
     */
    public function read(string $dashboardUuid): array
    {
        try {
            $dashboard  = $this->dashboardMapper->findByUuid(uuid: $dashboardUuid);
            $placements = $this->placementMapper->findByDashboardId(
                dashboardId: $dashboard->getId()
            );
            return array_map(
                static fn($placement) => $placement->jsonSerialize(),
                $placements
            );
        } catch (DoesNotExistException) {
            throw new DashboardNotFoundException(
                uuid: $dashboardUuid,
                backend: self::BACKEND_NAME
            );
        } catch (Throwable $t) {
            $this->logger->warning(
                'mydash: DbContentStorage.read failed for {uuid}: {msg}',
                ['uuid' => $dashboardUuid, 'msg' => $t->getMessage()]
            );
            throw new DashboardContentStorageException(
                message: sprintf(
                    'Failed to read dashboard content for UUID "%s" from database: %s',
                    $dashboardUuid,
                    $t->getMessage()
                ),
                previous: $t
            );
        }//end try
    }//end read()

    /**
     * Write (persist) placement content to the database.
     *
     * This is a no-op for the DB backend because widget placements are
     * written atomically by the controller / service layer through
     * {@see WidgetPlacementMapper} during normal API operations.
     * The method exists to satisfy the interface contract and is used by
     * the migration command to verify DB content exists.
     *
     * @param string $dashboardUuid The dashboard UUID.
     * @param array  $content       The placements (ignored for DB backend).
     *
     * @return void
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-2
     */
    public function write(string $dashboardUuid, array $content): void
    {
        // DB backend: placements are managed by the existing WidgetPlacementMapper
        // API flow; no separate write step is needed here.
    }//end write()

    /**
     * Delete all placement rows for a dashboard.
     *
     * Looks up the dashboard entity by UUID to obtain the numeric DB ID,
     * then removes all WidgetPlacement rows for that ID.
     *
     * @param string $dashboardUuid The dashboard UUID.
     *
     * @return void
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-2
     */
    public function delete(string $dashboardUuid): void
    {
        try {
            $dashboard = $this->dashboardMapper->findByUuid(uuid: $dashboardUuid);
            $this->placementMapper->deleteByDashboardId(
                dashboardId: $dashboard->getId()
            );
        } catch (DoesNotExistException) {
            // Dashboard does not exist — nothing to delete; satisfies the no-op
            // guarantee for missing content (interface contract).
        } catch (Throwable $t) {
            $this->logger->warning(
                'mydash: DbContentStorage.delete failed for {uuid}: {msg}',
                ['uuid' => $dashboardUuid, 'msg' => $t->getMessage()]
            );
            throw new DashboardContentStorageException(
                message: sprintf(
                    'Failed to delete dashboard content for UUID "%s" from database.',
                    $dashboardUuid
                ),
                previous: $t
            );
        }//end try
    }//end delete()

    /**
     * Check whether a dashboard exists in the database.
     *
     * @param string $dashboardUuid The dashboard UUID.
     *
     * @return bool True when the dashboard row exists, false otherwise.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-2
     */
    public function exists(string $dashboardUuid): bool
    {
        try {
            $this->dashboardMapper->findByUuid(uuid: $dashboardUuid);
            return true;
        } catch (DoesNotExistException) {
            return false;
        } catch (Throwable) {
            return false;
        }
    }//end exists()
}//end class
