<?php

/**
 * GroupFoldersNotInstalledException
 *
 * Thrown when the GroupFolder storage backend is configured but the
 * `groupfolders` Nextcloud app is not installed. Maps to HTTP 503.
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
 * Thrown when the `groupfolders` app is required but not installed (REQ-GFSB-005).
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-1
 */
class GroupFoldersNotInstalledException extends DashboardContentStorageException
{
    /**
     * Human-readable error message surfaced to admins.
     *
     * @var string
     */
    public const MESSAGE = "The 'groupfolders' app is required but not installed. "
        ."Please install it via the app store.";

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(message: self::MESSAGE);
    }//end __construct()
}//end class
