<?php

/**
 * DashboardContentStorageInterface
 *
 * Unified abstraction for reading and writing dashboard widget placement
 * content. Two implementations are shipped:
 *
 *  - {@see DbContentStorage}          — persists content via WidgetPlacementMapper
 *    (default; no additional dependencies)
 *  - {@see GroupFolderContentStorage} — persists content as JSON files inside a
 *    managed Nextcloud GroupFolder named "LaunchPad"
 *
 * The active implementation is selected by
 * {@see \OCA\MyDash\Service\DashboardContentStorageFactory} based on the
 * `content_storage` admin setting.
 *
 * Content format: a plain PHP array representing the ordered list of widget
 * placements. Each element is the output of
 * {@see \OCA\MyDash\Db\WidgetPlacement::jsonSerialize()}.
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
 * Contract for pluggable dashboard content storage backends.
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-1
 */
interface DashboardContentStorageInterface
{
    /**
     * Read the serialized placement array for a dashboard.
     *
     * @param string $dashboardUuid The dashboard UUID.
     *
     * @return array The serialized placements (each element is a placement
     *               data array matching WidgetPlacement::jsonSerialize()).
     *
     * @throws DashboardNotFoundException        When the UUID does not exist in
     *                                           the active backend.
     * @throws DashboardContentStorageException  On any I/O or availability
     *                                           failure; callers map to HTTP 503.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-1
     */
    public function read(string $dashboardUuid): array;

    /**
     * Persist the serialized placement array for a dashboard.
     *
     * Idempotent: rewriting identical content MUST NOT raise an error.
     * On the GroupFolder backend the target GroupFolder is auto-created on
     * the first write if it does not yet exist.
     *
     * @param string $dashboardUuid The dashboard UUID.
     * @param array  $content       The placements to persist.
     *
     * @return void
     *
     * @throws DashboardContentStorageException On any I/O or availability
     *                                          failure; callers map to HTTP 503.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-1
     */
    public function write(string $dashboardUuid, array $content): void;

    /**
     * Remove all stored content for a dashboard.
     *
     * MUST be a no-op (not throw) when the content does not exist in the
     * active backend — this allows callers to delete without first calling
     * {@see self::exists()}.
     *
     * @param string $dashboardUuid The dashboard UUID.
     *
     * @return void
     *
     * @throws DashboardContentStorageException On any I/O or availability
     *                                          failure; callers map to HTTP 503.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-1
     */
    public function delete(string $dashboardUuid): void;

    /**
     * Check whether content for a dashboard exists in the active backend.
     *
     * MUST return `false` without raising an exception when the content is
     * absent — callers use this for optional-read patterns (e.g. skipping
     * already-migrated dashboards in the migration command).
     *
     * @param string $dashboardUuid The dashboard UUID.
     *
     * @return bool `true` when content exists, `false` otherwise.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-1
     */
    public function exists(string $dashboardUuid): bool;
}//end interface
