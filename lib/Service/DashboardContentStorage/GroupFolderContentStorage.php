<?php

/**
 * GroupFolderContentStorage
 *
 * GroupFolder backend for the pluggable dashboard content storage layer.
 *
 * Persists dashboard widget placement content as JSON files inside a managed
 * Nextcloud GroupFolder named "LaunchPad". The GroupFolder is auto-created on
 * the first write if it does not yet exist, with ACL rules granting full access
 * to administrators and denying access to all other users at the filesystem
 * level (per-dashboard visibility is controlled by the `dashboards` capability's
 * permission system, not by the GroupFolder ACL directly).
 *
 * File layout:
 *   LaunchPad/<uuid>.json                — no locale
 *   LaunchPad/<locale>/<uuid>.json       — locale-specific
 *
 * Activated when `content_storage = 'groupfolder'` in admin settings.
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
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-3
 */

declare(strict_types=1);

namespace OCA\MyDash\Service\DashboardContentStorage;

use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IAppConfig;
use OCP\IGroupManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * GroupFolder-backed implementation of the content storage interface.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) The storage class must interact
 *   with the groupfolders app API (IAppManager, container), the NC file system
 *   (IRootFolder), and the admin config (IAppConfig) — all are required by
 *   REQ-GFSB-003 and REQ-GFSB-005 and cannot be simplified without losing
 *   spec compliance.
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-3
 */
class GroupFolderContentStorage implements DashboardContentStorageInterface
{

    /**
     * Name of the GroupFolder this backend creates and uses.
     *
     * @var string
     */
    public const FOLDER_NAME = 'LaunchPad';

    /**
     * Groupfolders app ID — used to check installation.
     *
     * @var string
     */
    private const GROUPFOLDERS_APP_ID = 'groupfolders';

    /**
     * Groupfolders FolderManager service class name (string to avoid hard-dep).
     *
     * @var string
     */
    private const FOLDER_MANAGER_CLASS = 'OCA\\GroupFolders\\Folder\\FolderManager';

    /**
     * Backend identifier returned in error messages.
     *
     * @var string
     */
    private const BACKEND_NAME = 'groupfolder';

    /**
     * Cache of the resolved GroupFolder folder ID (avoids repeated lookups).
     *
     * @var integer|null
     */
    private ?int $groupFolderId = null;

    /**
     * Constructor.
     *
     * @param IRootFolder        $rootFolder   NC root folder.
     * @param IGroupManager      $groupManager Group manager (admin ACL).
     * @param IAppConfig         $appConfig    App config (admin setting read).
     * @param ContainerInterface $container    Server DI container (lazy-loads
     *                                         groupfolders FolderManager).
     * @param LoggerInterface    $logger       PSR logger.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-3
     */
    public function __construct(
        private readonly IRootFolder $rootFolder,
        private readonly IGroupManager $groupManager,
        private readonly IAppConfig $appConfig,
        private readonly ContainerInterface $container,
        private readonly LoggerInterface $logger,
    ) {
    }//end __construct()

    /**
     * Check that the `groupfolders` app is installed; throw if not.
     *
     * @return void
     *
     * @throws GroupFoldersNotInstalledException When the app is absent.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-3
     */
    private function requireGroupFoldersApp(): void
    {
        if ($this->container->has(self::FOLDER_MANAGER_CLASS) === false) {
            throw new GroupFoldersNotInstalledException();
        }
    }//end requireGroupFoldersApp()

    /**
     * Resolve the file path for a dashboard UUID + optional locale.
     *
     * Returns `LaunchPad/<uuid>.json` when locale is empty, or
     * `LaunchPad/<locale>/<uuid>.json` otherwise. REQ-GFSB-004.
     *
     * @param string $dashboardUuid The dashboard UUID.
     * @param string $locale        Language code (e.g. `'nl'`) or empty string.
     *
     * @return string The relative path within the GroupFolder mount root.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-3
     */
    public function resolvePath(string $dashboardUuid, string $locale=''): string
    {
        if ($locale === '') {
            return self::FOLDER_NAME.'/'.$dashboardUuid.'.json';
        }

        return self::FOLDER_NAME.'/'.$locale.'/'.$dashboardUuid.'.json';
    }//end resolvePath()

    /**
     * Ensure the "LaunchPad" GroupFolder exists, creating it if necessary.
     *
     * Returns the GroupFolder's numeric ID. The creation is idempotent —
     * if the folder already exists the existing ID is returned without
     * creating a duplicate. REQ-GFSB-003.
     *
     * ACL set on creation:
     *   - Administrators: all permissions (read, write, delete)
     *   - All others: no default access at filesystem level (per-dashboard
     *     permissions control user-facing visibility via PermissionService)
     *
     * @return int The GroupFolder ID.
     *
     * @throws GroupFoldersNotInstalledException When groupfolders is not installed.
     * @throws DashboardContentStorageException  On creation failure.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-3
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-8
     */
    public function ensureLaunchPadGroupFolder(): int
    {
        if ($this->groupFolderId !== null) {
            return $this->groupFolderId;
        }

        $this->requireGroupFoldersApp();

        try {
            // @var object $manager
            $manager = $this->container->get(self::FOLDER_MANAGER_CLASS);

            // Check existing folders for one named "LaunchPad".
            $folders = $manager->getFolders();
            foreach ($folders as $folder) {
                if (($folder['mount_point'] ?? '') === self::FOLDER_NAME) {
                    $this->groupFolderId = (int) $folder['id'];
                    return $this->groupFolderId;
                }
            }

            // Not found — create it.
            $id = $manager->createFolder(mountPoint: self::FOLDER_NAME);

            // Restrict access: grant admins-only, deny global access.
            // The `admin` group receives PERMISSION_ALL (31).
            $manager->addApplicableGroup(folderId: $id, group: 'admin');
            // Disable the default "all users" group if the method exists.
            if (method_exists($manager, 'setFolderGroup') === true) {
                $manager->setFolderGroup(
                    folderId: $id,
                    group: 'everyone',
                    permissions: 0
                );
            }

            $this->groupFolderId = $id;

            $this->logger->info(
                'mydash: Created LaunchPad GroupFolder with id {id}',
                ['id' => $id]
            );

            return $this->groupFolderId;
        } catch (GroupFoldersNotInstalledException $e) {
            throw $e;
        } catch (Throwable $t) {
            throw new DashboardContentStorageException(
                message: 'Failed to ensure LaunchPad GroupFolder exists: '.$t->getMessage(),
                previous: $t
            );
        }//end try
    }//end ensureLaunchPadGroupFolder()

    /**
     * Resolve the Nextcloud Folder node for the GroupFolder root directory.
     *
     * The GroupFolder is mounted by Nextcloud at `/__groupfolders/<id>` in
     * the virtual FS root. We navigate there via `IRootFolder`.
     *
     * @param int $folderId The GroupFolder ID returned by
     *                      {@see self::ensureLaunchPadGroupFolder()}.
     *
     * @return Folder The folder node.
     *
     * @throws DashboardContentStorageException When the mount point is not
     *                                          accessible.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-3
     */
    private function getGroupFolderRoot(int $folderId): Folder
    {
        try {
            $mountPath = '/__groupfolders/'.$folderId;
            $node      = $this->rootFolder->get($mountPath);
            if (($node instanceof Folder) === false) {
                throw new DashboardContentStorageException(
                    'GroupFolder mount point is not a directory: '.$mountPath
                );
            }

            return $node;
        } catch (NotFoundException $e) {
            throw new DashboardContentStorageException(
                message: 'LaunchPad GroupFolder mount point not accessible. '
                    .'The groupfolders app may need to be re-configured. '
                    .'Run launchpad:storage:migrate-to-groupfolder to resolve.',
                previous: $e
            );
        } catch (DashboardContentStorageException $e) {
            throw $e;
        } catch (Throwable $t) {
            throw new DashboardContentStorageException(
                message: 'Failed to access GroupFolder root: '.$t->getMessage(),
                previous: $t
            );
        }//end try
    }//end getGroupFolderRoot()

    /**
     * Get or create a sub-directory node within the GroupFolder root.
     *
     * @param Folder $root      The GroupFolder root node.
     * @param string $directory The subdirectory name (e.g. a locale code).
     *
     * @return Folder The existing or newly created folder node.
     *
     * @throws DashboardContentStorageException On creation failure.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-3
     */
    private function ensureSubFolder(Folder $root, string $directory): Folder
    {
        try {
            $node = $root->get($directory);
            if (($node instanceof Folder) === false) {
                throw new DashboardContentStorageException(
                    'Expected a directory but found a file at path: '.$directory
                );
            }

            return $node;
        } catch (NotFoundException) {
            try {
                return $root->newFolder(path: $directory);
            } catch (NotPermittedException $e) {
                throw new DashboardContentStorageException(
                    message: 'Cannot create sub-directory in GroupFolder: '.$directory,
                    previous: $e
                );
            }
        }//end try
    }//end ensureSubFolder()

    /**
     * Read widget placement content from the GroupFolder JSON file.
     *
     * Tries the locale-specific path first
     * (`LaunchPad/<locale>/<uuid>.json`), then falls back to the
     * locale-less path (`LaunchPad/<uuid>.json`). REQ-GFSB-004.
     *
     * @param string $dashboardUuid The dashboard UUID.
     * @param string $locale        Optional locale code.
     *
     * @return array The deserialized placement data.
     *
     * @throws DashboardNotFoundException       When the file does not exist.
     * @throws DashboardContentStorageException On any I/O failure.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-3
     */
    public function read(string $dashboardUuid, string $locale=''): array
    {
        $this->requireGroupFoldersApp();

        try {
            $folderId = $this->ensureLaunchPadGroupFolder();
            $root     = $this->getGroupFolderRoot(folderId: $folderId);

            // Attempt locale-specific path first, then fall back to locale-less.
            $paths = [];
            if ($locale !== '') {
                $paths[] = $locale.'/'.$dashboardUuid.'.json';
            }

            $paths[] = $dashboardUuid.'.json';

            foreach ($paths as $path) {
                try {
                    $file    = $root->get($path);
                    $content = $file->getContent();
                    $decoded = json_decode(json: $content, associative: true, flags: JSON_THROW_ON_ERROR);
                    if (is_array($decoded) === true) {
                        return $decoded;
                    }

                    return [];
                } catch (NotFoundException) {
                    // Try next path.
                    continue;
                }
            }

            throw new DashboardNotFoundException(
                uuid: $dashboardUuid,
                backend: self::BACKEND_NAME
            );
        } catch (DashboardContentStorageException $e) {
            throw $e;
        } catch (Throwable $t) {
            $this->logger->warning(
                'mydash: GroupFolderContentStorage.read failed for {uuid}: {msg}',
                ['uuid' => $dashboardUuid, 'msg' => $t->getMessage()]
            );
            throw new DashboardContentStorageException(
                message: sprintf(
                    'Failed to read dashboard content for UUID "%s" from GroupFolder: %s',
                    $dashboardUuid,
                    $t->getMessage()
                ),
                previous: $t
            );
        }//end try
    }//end read()

    /**
     * Write widget placement content as a JSON file in the GroupFolder.
     *
     * Auto-creates the LaunchPad GroupFolder and any locale sub-directory
     * on first write. The file is human-readable (JSON_PRETTY_PRINT).
     * REQ-GFSB-003, REQ-GFSB-004.
     *
     * @param string $dashboardUuid The dashboard UUID.
     * @param array  $content       The placement data to persist.
     * @param string $locale        Optional locale code.
     *
     * @return void
     *
     * @throws DashboardContentStorageException On any I/O or availability failure.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-3
     */
    public function write(string $dashboardUuid, array $content, string $locale=''): void
    {
        $this->requireGroupFoldersApp();

        try {
            $folderId = $this->ensureLaunchPadGroupFolder();
            $root     = $this->getGroupFolderRoot(folderId: $folderId);

            $json     = json_encode(
                value: $content,
                flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
            $fileName = $dashboardUuid.'.json';

            $dir = $root;
            if ($locale !== '') {
                $dir = $this->ensureSubFolder(root: $root, directory: $locale);
            }

            try {
                $file = $dir->get($fileName);
                $file->putContent(data: $json);
            } catch (NotFoundException) {
                $dir->newFile(path: $fileName, content: $json);
            }
        } catch (DashboardContentStorageException $e) {
            throw $e;
        } catch (Throwable $t) {
            $this->logger->warning(
                'mydash: GroupFolderContentStorage.write failed for {uuid}: {msg}',
                ['uuid' => $dashboardUuid, 'msg' => $t->getMessage()]
            );
            throw new DashboardContentStorageException(
                message: sprintf(
                    'Failed to write dashboard content for UUID "%s" to GroupFolder (operation: write): %s',
                    $dashboardUuid,
                    $t->getMessage()
                ),
                previous: $t
            );
        }//end try
    }//end write()

    /**
     * Delete the JSON file for a dashboard from the GroupFolder.
     *
     * No-op when the file does not exist (satisfies the interface contract).
     *
     * @param string $dashboardUuid The dashboard UUID.
     * @param string $locale        Optional locale code.
     *
     * @return void
     *
     * @throws DashboardContentStorageException On unexpected I/O failure.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-3
     */
    public function delete(string $dashboardUuid, string $locale=''): void
    {
        $this->requireGroupFoldersApp();

        try {
            $folderId = $this->ensureLaunchPadGroupFolder();
            $root     = $this->getGroupFolderRoot(folderId: $folderId);

            $paths = [];
            if ($locale !== '') {
                $paths[] = $locale.'/'.$dashboardUuid.'.json';
            }

            $paths[] = $dashboardUuid.'.json';

            foreach ($paths as $path) {
                try {
                    $file = $root->get($path);
                    $file->delete();
                } catch (NotFoundException) {
                    // File does not exist — no-op per interface contract.
                }
            }
        } catch (DashboardContentStorageException $e) {
            throw $e;
        } catch (Throwable $t) {
            $this->logger->warning(
                'mydash: GroupFolderContentStorage.delete failed for {uuid}: {msg}',
                ['uuid' => $dashboardUuid, 'msg' => $t->getMessage()]
            );
            throw new DashboardContentStorageException(
                message: sprintf(
                    'Failed to delete dashboard content for UUID "%s" from GroupFolder (operation: delete): %s',
                    $dashboardUuid,
                    $t->getMessage()
                ),
                previous: $t
            );
        }//end try
    }//end delete()

    /**
     * Check whether a JSON file for a dashboard exists in the GroupFolder.
     *
     * Returns `false` (never throws) when the file is absent or the
     * GroupFolder is not accessible. REQ-GFSB-001.
     *
     * @param string $dashboardUuid The dashboard UUID.
     * @param string $locale        Optional locale code.
     *
     * @return bool True when the file exists.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-3
     */
    public function exists(string $dashboardUuid, string $locale=''): bool
    {
        try {
            $this->requireGroupFoldersApp();
            $folderId = $this->ensureLaunchPadGroupFolder();
            $root     = $this->getGroupFolderRoot(folderId: $folderId);

            $paths = [];
            if ($locale !== '') {
                $paths[] = $locale.'/'.$dashboardUuid.'.json';
            }

            $paths[] = $dashboardUuid.'.json';

            foreach ($paths as $path) {
                try {
                    $root->get($path);
                    return true;
                } catch (NotFoundException) {
                    continue;
                }
            }

            return false;
        } catch (Throwable) {
            return false;
        }//end try
    }//end exists()
}//end class
