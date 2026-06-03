<?php

/**
 * DashboardContentStorageException
 *
 * Base exception for all dashboard content storage failures. Callers
 * MUST map this (and its subclasses) to HTTP 503 Service Unavailable
 * with error key `dashboard_content_storage_unavailable`.
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

use RuntimeException;

/**
 * Base exception for all dashboard content storage failures (REQ-GFSB-005).
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-1
 */
class DashboardContentStorageException extends RuntimeException
{
}//end class
