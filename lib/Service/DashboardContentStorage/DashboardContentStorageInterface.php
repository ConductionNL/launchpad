<?php

/**
 * DashboardContentStorageInterface
 *
 * Abstraction layer for pluggable dashboard content storage. Implementations
 * MUST throw DashboardContentStorageException (or a subclass) for any
 * failure; callers MUST NOT silently fall back to an alternate backend.
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
 * Unified interface for dashboard content persistence (REQ-GFSB-001).
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-1
 */
interface DashboardContentStorageInterface
{
    /**
     * Read dashboard content by UUID.
     *
     * @param string      $uuid   The dashboard UUID.
     * @param string|null $locale Optional locale code (e.g. 'nl'). When
     *                            supplied the backend SHOULD attempt a
     *                            locale-specific read first and fall back
     *                            to the locale-neutral path.
     *
     * @return array The decoded content array.
     *
     * @throws DashboardNotFoundException          When the UUID does not exist.
     * @throws DashboardContentStorageException    On any other storage failure.
     * @throws GroupFoldersNotInstalledException   When the GroupFolder app is absent.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-1
     */
    public function read(string $uuid, ?string $locale=null): array;

    /**
     * Write (create or overwrite) dashboard content.
     *
     * The operation MUST be idempotent: calling write with identical content
     * a second time MUST NOT raise an error.
     *
     * @param string      $uuid    The dashboard UUID.
     * @param array       $content The content to persist (JSON-serialisable).
     * @param string|null $locale  Optional locale code. When supplied the
     *                             content is stored in a locale-specific path.
     *
     * @return void
     *
     * @throws DashboardContentStorageException  On any storage failure.
     * @throws GroupFoldersNotInstalledException When the GroupFolder app is absent.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-1
     */
    public function write(string $uuid, array $content, ?string $locale=null): void;

    /**
     * Delete dashboard content by UUID.
     *
     * MUST NOT throw when the UUID does not exist (soft delete semantics).
     *
     * @param string      $uuid   The dashboard UUID.
     * @param string|null $locale Optional locale code. When supplied only the
     *                            locale-specific file is removed; pass null to
     *                            remove all locale variants.
     *
     * @return void
     *
     * @throws DashboardContentStorageException  On any storage failure.
     * @throws GroupFoldersNotInstalledException When the GroupFolder app is absent.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-1
     */
    public function delete(string $uuid, ?string $locale=null): void;

    /**
     * Check whether content for the given UUID exists without throwing.
     *
     * @param string      $uuid   The dashboard UUID.
     * @param string|null $locale Optional locale code. When supplied checks
     *                            for the locale-specific variant.
     *
     * @return boolean True when the content exists; false otherwise.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-1
     */
    public function exists(string $uuid, ?string $locale=null): bool;
}//end interface
