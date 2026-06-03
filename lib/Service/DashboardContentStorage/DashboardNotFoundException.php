<?php

/**
 * DashboardNotFoundException
 *
 * Thrown when the requested dashboard UUID does not exist in the active
 * storage backend (neither DB row nor GroupFolder file found). Extends
 * {@see DashboardContentStorageException} so callers that only catch the
 * base class still handle this sub-type correctly.
 *
 * @category Exception
 * @package  OCA\MyDash\Service\DashboardContentStorage
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-1
 */

declare(strict_types=1);

namespace OCA\MyDash\Service\DashboardContentStorage;

/**
 * Dashboard content not found in the active storage backend.
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-1
 */
class DashboardNotFoundException extends DashboardContentStorageException
{
    /**
     * Constructor.
     *
     * @param string $uuid    The dashboard UUID that was not found.
     * @param string $backend The active backend name (db|groupfolder).
     */
    public function __construct(string $uuid, string $backend='unknown')
    {
        parent::__construct(
            message: sprintf(
                'Dashboard content for UUID "%s" not found in the active storage backend (%s).',
                $uuid,
                $backend
            )
        );
    }//end __construct()
}//end class
