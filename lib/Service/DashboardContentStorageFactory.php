<?php

/**
 * DashboardContentStorageFactory
 *
 * Factory that instantiates the correct DashboardContentStorage implementation
 * based on the `launchpad.content_storage` admin setting.
 *
 * @category Service
 * @package  OCA\MyDash\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-4
 */

declare(strict_types=1);

namespace OCA\MyDash\Service;

use OCA\MyDash\Service\DashboardContentStorage\DashboardContentStorageInterface;
use OCA\MyDash\Service\DashboardContentStorage\DbContentStorage;
use OCA\MyDash\Service\DashboardContentStorage\GroupFolderContentStorage;

/**
 * Factory for the active DashboardContentStorage backend (REQ-GFSB-004).
 *
 * Reads the persisted admin setting via `SetupWizardService::getContentStorage()`
 * and returns the matching implementation. The factory itself has no I/O — it
 * is a pure selection layer that the DI container resolves once per request.
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-4
 */
class DashboardContentStorageFactory
{
    /**
     * Constructor.
     *
     * @param DbContentStorage          $dbStorage          Database-backed implementation.
     * @param GroupFolderContentStorage $groupFolderStorage GroupFolder-backed implementation.
     * @param SetupWizardService        $wizardService      Source of the `content_storage`
     *                                                      admin setting.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-4
     */
    public function __construct(
        private readonly DbContentStorage $dbStorage,
        private readonly GroupFolderContentStorage $groupFolderStorage,
        private readonly SetupWizardService $wizardService,
    ) {
    }//end __construct()

    /**
     * Return the active content storage backend.
     *
     * Reads the persisted backend choice from `SetupWizardService::getContentStorage()`.
     * Returns the GroupFolder implementation when the value equals
     * `SetupWizardService::STORAGE_GROUPFOLDER`; falls back to the DB
     * implementation for any other value (including the default `'database'`).
     *
     * @return DashboardContentStorageInterface The active storage backend.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-4
     */
    public function getStorage(): DashboardContentStorageInterface
    {
        $backend = $this->wizardService->getContentStorage();

        if ($backend === SetupWizardService::STORAGE_GROUPFOLDER) {
            return $this->groupFolderStorage;
        }

        return $this->dbStorage;
    }//end getStorage()
}//end class
