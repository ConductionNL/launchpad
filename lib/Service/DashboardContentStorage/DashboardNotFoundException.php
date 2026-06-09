<?php

/**
 * DashboardNotFoundException
 *
 * Thrown when a requested dashboard does not exist in the active storage
 * backend. Extends DashboardContentStorageException so callers can catch
 * either the base or the specific type.
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
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-1
 */

declare(strict_types=1);

namespace OCA\MyDash\Service\DashboardContentStorage;

/**
 * Thrown when a dashboard does not exist in the active storage backend.
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-1
 */
class DashboardNotFoundException extends DashboardContentStorageException
{
}//end class
