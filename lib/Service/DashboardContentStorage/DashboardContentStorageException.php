<?php

/**
 * DashboardContentStorageException
 *
 * Base exception for all dashboard content storage failures. Callers map
 * this to HTTP 503 (Service Unavailable) with error key
 * {@see DashboardContentStorageException::ERROR_KEY}.
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

use RuntimeException;

/**
 * Base exception for the pluggable content-storage layer.
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-1
 */
class DashboardContentStorageException extends RuntimeException
{

    /**
     * Stable wire-format error key returned to API clients (HTTP 503).
     *
     * @var string
     */
    public const ERROR_KEY = 'dashboard_content_storage_unavailable';

    /**
     * HTTP status code this exception maps to.
     *
     * @var int
     */
    public const HTTP_STATUS = 503;

}//end class
