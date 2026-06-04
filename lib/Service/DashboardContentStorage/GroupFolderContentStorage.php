<?php

/**
 * GroupFolderContentStorage
 *
 * GroupFolder-backed implementation of the DashboardContentStorage interface.
 * Stores dashboard content as JSON files under the "LaunchPad" GroupFolder.
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

use OCP\App\IAppManager;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use Psr\Log\LoggerInterface;

/**
 * GroupFolder-backed implementation of DashboardContentStorageInterface (REQ-GFSB-003).
 *
 * Content is stored as JSON files in a dedicated GroupFolder named `LaunchPad`.
 * Without a locale the file lives at `LaunchPad/<uuid>.json`; with a locale it
 * lives at `LaunchPad/<locale>/<uuid>.json`. Locale-specific reads fall back to
 * the locale-neutral path when the locale-specific file is absent.
 *
 * @SuppressWarnings(PHPMD.CyclomaticComplexity) write() handles all locale/path/conflict branches atomically.
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-3
 */
class GroupFolderContentStorage implements DashboardContentStorageInterface
{

    /**
     * The `groupfolders` app ID required by this backend.
     *
     * @var string
     */
    private const GROUPFOLDERS_APP_ID = 'groupfolders';

    /**
     * The name of the top-level GroupFolder used by this backend.
     *
     * @var string
     */
    private const FOLDER_NAME = 'LaunchPad';

    /**
     * Constructor.
     *
     * @param IRootFolder     $rootFolder Nextcloud virtual file system root.
     * @param IAppManager     $appManager Used to verify the groupfolders app
     *                                    is installed.
     * @param LoggerInterface $logger     PSR-3 logger.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-3
     */
    public function __construct(
        private readonly IRootFolder $rootFolder,
        private readonly IAppManager $appManager,
        private readonly LoggerInterface $logger,
    ) {
    }//end __construct()

    /**
     * Resolve the storage path for a given UUID and optional locale.
     *
     * Returns `LaunchPad/<uuid>.json` when no locale is provided, and
     * `LaunchPad/<locale>/<uuid>.json` when one is.
     *
     * @param string      $uuid   The dashboard UUID.
     * @param string|null $locale The optional locale code.
     *
     * @return string The resolved relative path within the root folder.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-3
     */
    private function resolvePath(string $uuid, ?string $locale): string
    {
        if ($locale === null || $locale === '') {
            return self::FOLDER_NAME.'/'.$uuid.'.json';
        }

        return self::FOLDER_NAME.'/'.$locale.'/'.$uuid.'.json';
    }//end resolvePath()

    /**
     * Ensure the `LaunchPad` GroupFolder exists, creating it when absent.
     *
     * Verifies that the `groupfolders` Nextcloud app is installed before any
     * file-system operation. Creates the top-level folder via `newFolder()`
     * when `get()` raises `NotFoundException`.
     *
     * @return Node The `LaunchPad` folder node.
     *
     * @throws GroupFoldersNotInstalledException When the groupfolders app is absent.
     * @throws DashboardContentStorageException  On any other file-system failure.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-3
     */
    private function ensureLaunchPadGroupFolder(): Node
    {
        if ($this->appManager->isInstalled(appId: self::GROUPFOLDERS_APP_ID) === false) {
            throw new GroupFoldersNotInstalledException();
        }

        try {
            return $this->rootFolder->get(path: '/'.self::FOLDER_NAME);
        } catch (NotFoundException) {
            // Folder does not exist yet — create it.
        } catch (\Throwable $e) {
            $this->logger->error(
                message: 'GroupFolderContentStorage: failed to get LaunchPad folder.',
                context: ['exception' => $e]
            );
            throw new DashboardContentStorageException(
                message: 'Failed to get LaunchPad GroupFolder: '.$e->getMessage(),
                previous: $e
            );
        }//end try

        try {
            return $this->rootFolder->newFolder(path: '/'.self::FOLDER_NAME);
        } catch (\Throwable $e) {
            $this->logger->error(
                message: 'GroupFolderContentStorage: failed to create LaunchPad folder.',
                context: ['exception' => $e]
            );
            throw new DashboardContentStorageException(
                message: 'Failed to create LaunchPad GroupFolder: '.$e->getMessage(),
                previous: $e
            );
        }//end try
    }//end ensureLaunchPadGroupFolder()

    /**
     * Read dashboard content from the GroupFolder.
     *
     * Resolves the file path, reads and JSON-decodes the content. When a locale
     * is supplied and the locale-specific file does not exist, automatically
     * falls back to the locale-neutral path before raising
     * `DashboardNotFoundException`.
     *
     * @param string      $uuid   The dashboard UUID.
     * @param string|null $locale Optional locale code.
     *
     * @return array The decoded content array.
     *
     * @throws DashboardNotFoundException          When no file is found for the UUID.
     * @throws GroupFoldersNotInstalledException   When the groupfolders app is absent.
     * @throws DashboardContentStorageException    On any other file-system failure.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-3
     */
    public function read(string $uuid, ?string $locale=null): array
    {
        if ($this->appManager->isInstalled(appId: self::GROUPFOLDERS_APP_ID) === false) {
            throw new GroupFoldersNotInstalledException();
        }

        // Attempt locale-specific path first, then fall back to neutral.
        $paths = [];
        if ($locale !== null && $locale !== '') {
            $paths[] = $this->resolvePath(uuid: $uuid, locale: $locale);
        }

        $paths[] = $this->resolvePath(uuid: $uuid, locale: null);

        foreach ($paths as $path) {
            try {
                $node = $this->rootFolder->get(path: '/'.$path);
            } catch (NotFoundException) {
                // Try the next candidate path.
                continue;
            } catch (\Throwable $e) {
                $this->logger->error(
                    message: 'GroupFolderContentStorage: error accessing file.',
                    context: ['path' => $path, 'exception' => $e]
                );
                throw new DashboardContentStorageException(
                    message: 'Failed to access GroupFolder file: '.$e->getMessage(),
                    previous: $e
                );
            }//end try

            if (($node instanceof File) === false) {
                continue;
            }

            try {
                $raw     = $node->getContent();
                $decoded = json_decode(json: $raw, associative: true);
                if (is_array($decoded) === true) {
                    return $decoded;
                }

                return [];
            } catch (\Throwable $e) {
                $this->logger->error(
                    message: 'GroupFolderContentStorage: failed to read file content.',
                    context: ['path' => $path, 'exception' => $e]
                );
                throw new DashboardContentStorageException(
                    message: 'Failed to read GroupFolder file content: '.$e->getMessage(),
                    previous: $e
                );
            }//end try
        }//end foreach

        throw new DashboardNotFoundException(
            message: 'Dashboard content not found in GroupFolder: '.$uuid
        );
    }//end read()

    /**
     * Write dashboard content to the GroupFolder as a JSON file.
     *
     * Ensures the `LaunchPad` folder exists, creates any locale subdirectory
     * when needed, then writes (or overwrites) the JSON file.
     *
     * @param string      $uuid    The dashboard UUID.
     * @param array       $content The content to persist (JSON-serialisable).
     * @param string|null $locale  Optional locale code. When supplied the file
     *                             is written under `LaunchPad/<locale>/`.
     *
     * @return void
     *
     * @throws GroupFoldersNotInstalledException When the groupfolders app is absent.
     * @throws DashboardContentStorageException  On any file-system failure.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-3
     */
    public function write(string $uuid, array $content, ?string $locale=null): void
    {
        $this->ensureLaunchPadGroupFolder();

        $path = $this->resolvePath(uuid: $uuid, locale: $locale);

        // Ensure locale subdirectory exists when writing a locale-specific file.
        if ($locale !== null && $locale !== '') {
            $localeDirPath = '/'.self::FOLDER_NAME.'/'.$locale;
            try {
                $this->rootFolder->get(path: $localeDirPath);
            } catch (NotFoundException) {
                try {
                    $this->rootFolder->newFolder(path: $localeDirPath);
                } catch (\Throwable $e) {
                    $this->logger->error(
                        message: 'GroupFolderContentStorage: failed to create locale subdirectory.',
                        context: ['localeDirPath' => $localeDirPath, 'exception' => $e]
                    );
                    throw new DashboardContentStorageException(
                        message: 'Failed to create locale directory: '.$e->getMessage(),
                        previous: $e
                    );
                }//end try
            } catch (\Throwable $e) {
                $this->logger->error(
                    message: 'GroupFolderContentStorage: failed to check locale subdirectory.',
                    context: ['localeDirPath' => $localeDirPath, 'exception' => $e]
                );
                throw new DashboardContentStorageException(
                    message: 'Failed to check locale directory: '.$e->getMessage(),
                    previous: $e
                );
            }//end try
        }//end if

        try {
            $encoded = json_encode(
                value: $content,
                flags: JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
            );

            try {
                // File exists — overwrite.
                $existingNode = $this->rootFolder->get(path: '/'.$path);
                if ($existingNode instanceof File) {
                    $existingNode->putContent(data: $encoded);
                }
            } catch (NotFoundException) {
                // File does not exist — determine the parent folder and create it.
                $parentPath = '/'.self::FOLDER_NAME;
                if ($locale !== null && $locale !== '') {
                    $parentPath = '/'.self::FOLDER_NAME.'/'.$locale;
                }

                $parentNode = $this->rootFolder->get(path: $parentPath);
                if ($parentNode instanceof Folder) {
                    $parentNode->newFile(path: $uuid.'.json', content: $encoded);
                }
            }//end try
        } catch (\Throwable $e) {
            $this->logger->error(
                message: 'GroupFolderContentStorage: failed to write content file.',
                context: ['path' => $path, 'exception' => $e]
            );
            throw new DashboardContentStorageException(
                message: 'Failed to write GroupFolder content file: '.$e->getMessage(),
                previous: $e
            );
        }//end try
    }//end write()

    /**
     * Delete the content file for the given UUID from the GroupFolder.
     *
     * Silently ignores the case where the file does not exist (soft-delete
     * semantics required by the interface contract).
     *
     * @param string      $uuid   The dashboard UUID.
     * @param string|null $locale Optional locale code. When supplied only the
     *                            locale-specific file is removed. Pass null to
     *                            remove the locale-neutral file.
     *
     * @return void
     *
     * @throws GroupFoldersNotInstalledException When the groupfolders app is absent.
     * @throws DashboardContentStorageException  On any file-system failure other
     *                                           than "not found".
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-3
     */
    public function delete(string $uuid, ?string $locale=null): void
    {
        if ($this->appManager->isInstalled(appId: self::GROUPFOLDERS_APP_ID) === false) {
            throw new GroupFoldersNotInstalledException();
        }

        $path = $this->resolvePath(uuid: $uuid, locale: $locale);

        try {
            $file = $this->rootFolder->get(path: '/'.$path);
            $file->delete();
        } catch (NotFoundException) {
            // File does not exist — soft-delete requires a no-op here.
        } catch (\Throwable $e) {
            $this->logger->error(
                message: 'GroupFolderContentStorage: failed to delete content file.',
                context: ['path' => $path, 'exception' => $e]
            );
            throw new DashboardContentStorageException(
                message: 'Failed to delete GroupFolder content file: '.$e->getMessage(),
                previous: $e
            );
        }//end try
    }//end delete()

    /**
     * Check whether a content file for the given UUID exists.
     *
     * Returns false for any "not found" or error outcome without raising
     * an exception, in line with the interface contract.
     *
     * @param string      $uuid   The dashboard UUID.
     * @param string|null $locale Optional locale code. When supplied checks
     *                            for the locale-specific variant.
     *
     * @return boolean True when the file exists; false otherwise.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-3
     */
    public function exists(string $uuid, ?string $locale=null): bool
    {
        if ($this->appManager->isInstalled(appId: self::GROUPFOLDERS_APP_ID) === false) {
            return false;
        }

        $path = $this->resolvePath(uuid: $uuid, locale: $locale);

        try {
            $this->rootFolder->get(path: '/'.$path);
            return true;
        } catch (NotFoundException) {
            return false;
        } catch (\Throwable $e) {
            $this->logger->warning(
                message: 'GroupFolderContentStorage: exists() check failed.',
                context: ['path' => $path, 'exception' => $e]
            );
            return false;
        }//end try
    }//end exists()
}//end class
