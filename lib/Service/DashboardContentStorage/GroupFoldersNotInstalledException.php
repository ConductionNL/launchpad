<?php

/**
 * GroupFoldersNotInstalledException
 *
 * Thrown when the `groupfolders` Nextcloud app is required for the active
 * storage backend but is not installed or enabled. API callers map this to
 * HTTP 503 via the base {@see DashboardContentStorageException::HTTP_STATUS}.
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
 * The `groupfolders` app is required but not installed.
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-1
 */
class GroupFoldersNotInstalledException extends DashboardContentStorageException
{

    /**
     * Fixed human-readable message per REQ-GFSB-005.
     *
     * @var string
     */
    public const MESSAGE = "The 'groupfolders' Nextcloud app is required for GroupFolder storage backend"
        ." but is not installed. Please install it via the app store or contact your administrator.";

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(message: self::MESSAGE);
    }//end __construct()
}//end class
