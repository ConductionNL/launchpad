<?php

/**
 * DbContentStorage
 *
 * Database-backed implementation of the DashboardContentStorage interface.
 * Stores dashboard content in the `content` column of `oc_launchpad_dashboards`.
 *
 * @category Service
 * @package  OCA\LaunchPad\Service\DashboardContentStorage
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\DashboardContentStorage;

use OCA\LaunchPad\Db\DashboardMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use Psr\Log\LoggerInterface;

/**
 * Database-backed implementation of DashboardContentStorageInterface (REQ-GFSB-002).
 *
 * Content is stored as JSON in the `content` column of `oc_launchpad_dashboards`.
 * The locale parameter is accepted for interface compatibility but is not used
 * because the DB backend stores a single content blob per dashboard (not
 * locale-separated). Locale-separated content is a GroupFolder-only concern.
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-2
 */
class DbContentStorage implements DashboardContentStorageInterface
{
    /**
     * Constructor.
     *
     * @param DashboardMapper $dashboardMapper Dashboard persistence mapper.
     * @param LoggerInterface $logger          PSR-3 logger.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-2
     */
    public function __construct(
        private readonly DashboardMapper $dashboardMapper,
        private readonly LoggerInterface $logger,
    ) {
    }//end __construct()

    /**
     * Read dashboard content by UUID from the database.
     *
     * Finds the dashboard entity by UUID and returns `json_decode` of its
     * `content` field. The `$locale` parameter is accepted for interface
     * compatibility but ignored — DB storage is not locale-separated.
     *
     * @param string      $uuid   The dashboard UUID.
     * @param string|null $locale Optional locale code (ignored by this backend).
     *
     * @return array The decoded content array, or an empty array when content
     *               is null or empty.
     *
     * @throws DashboardNotFoundException       When no dashboard with the UUID exists.
     * @throws DashboardContentStorageException On any other persistence failure.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-2
     */
    public function read(string $uuid, ?string $locale=null): array
    {
        try {
            $dashboard = $this->dashboardMapper->findByUuid(uuid: $uuid);
        } catch (DoesNotExistException $e) {
            throw new DashboardNotFoundException(
                message: 'Dashboard not found: '.$uuid,
                previous: $e
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                message: 'DbContentStorage: failed to read dashboard content.',
                context: ['uuid' => $uuid, 'exception' => $e]
            );
            throw new DashboardContentStorageException(
                message: 'Failed to read dashboard content: '.$e->getMessage(),
                previous: $e
            );
        }//end try

        // phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
        $raw = $dashboard->getContent();
        // phpcs:enable

        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode(json: $raw, associative: true);
        if (is_array($decoded) === true) {
            return $decoded;
        }

        return [];
    }//end read()

    /**
     * Write dashboard content by UUID to the database.
     *
     * Finds the entity by UUID, JSON-encodes the supplied content array,
     * sets it on the entity, and calls the mapper's `update()`. The
     * `$locale` parameter is accepted for interface compatibility but ignored
     * — DB storage is not locale-separated.
     *
     * @param string      $uuid    The dashboard UUID.
     * @param array       $content The content to persist (JSON-serialisable).
     * @param string|null $locale  Optional locale code (ignored by this backend).
     *
     * @return void
     *
     * @throws DashboardNotFoundException       When no dashboard with the UUID exists.
     * @throws DashboardContentStorageException On any other persistence failure.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-2
     */
    public function write(string $uuid, array $content, ?string $locale=null): void
    {
        try {
            $dashboard = $this->dashboardMapper->findByUuid(uuid: $uuid);
        } catch (DoesNotExistException $e) {
            throw new DashboardNotFoundException(
                message: 'Dashboard not found: '.$uuid,
                previous: $e
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                message: 'DbContentStorage: failed to locate dashboard for write.',
                context: ['uuid' => $uuid, 'exception' => $e]
            );
            throw new DashboardContentStorageException(
                message: 'Failed to locate dashboard for write: '.$e->getMessage(),
                previous: $e
            );
        }//end try

        try {
            $encoded = json_encode(value: $content, flags: JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            // Entity setters resolve via __call which uses $args[0]; named
            // args would break the magic forwarding.
            // phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
            $dashboard->setContent($encoded);
            // phpcs:enable

            $this->dashboardMapper->update(entity: $dashboard);
        } catch (\Throwable $e) {
            $this->logger->error(
                message: 'DbContentStorage: failed to write dashboard content.',
                context: ['uuid' => $uuid, 'exception' => $e]
            );
            throw new DashboardContentStorageException(
                message: 'Failed to write dashboard content: '.$e->getMessage(),
                previous: $e
            );
        }//end try
    }//end write()

    /**
     * Delete dashboard content by UUID (soft delete — sets content to null).
     *
     * Finds the entity, sets its `content` field to null, and calls the
     * mapper's `update()`. Does NOT throw when the UUID does not exist, in
     * line with the interface contract.
     *
     * @param string      $uuid   The dashboard UUID.
     * @param string|null $locale Optional locale code (ignored by this backend).
     *
     * @return void
     *
     * @throws DashboardContentStorageException On any storage failure other
     *                                          than "not found".
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-2
     */
    public function delete(string $uuid, ?string $locale=null): void
    {
        try {
            $dashboard = $this->dashboardMapper->findByUuid(uuid: $uuid);
        } catch (DoesNotExistException) {
            // Not found — soft-delete semantics require no-op here.
            return;
        } catch (\Throwable $e) {
            $this->logger->error(
                message: 'DbContentStorage: failed to locate dashboard for delete.',
                context: ['uuid' => $uuid, 'exception' => $e]
            );
            throw new DashboardContentStorageException(
                message: 'Failed to locate dashboard for delete: '.$e->getMessage(),
                previous: $e
            );
        }//end try

        try {
            // Entity setters resolve via __call — named args break magic forwarding.
            // phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
            $dashboard->setContent(null);
            // phpcs:enable

            $this->dashboardMapper->update(entity: $dashboard);
        } catch (\Throwable $e) {
            $this->logger->error(
                message: 'DbContentStorage: failed to clear dashboard content.',
                context: ['uuid' => $uuid, 'exception' => $e]
            );
            throw new DashboardContentStorageException(
                message: 'Failed to clear dashboard content: '.$e->getMessage(),
                previous: $e
            );
        }//end try
    }//end delete()

    /**
     * Check whether content exists for the given UUID without throwing.
     *
     * Tries to find the dashboard by UUID and returns true when a row
     * exists and has non-null/non-empty content. Returns false for any
     * "not found" outcome without raising an exception.
     *
     * @param string      $uuid   The dashboard UUID.
     * @param string|null $locale Optional locale code (ignored by this backend).
     *
     * @return boolean True when content exists; false otherwise.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-2
     */
    public function exists(string $uuid, ?string $locale=null): bool
    {
        try {
            $dashboard = $this->dashboardMapper->findByUuid(uuid: $uuid);
        } catch (DoesNotExistException) {
            return false;
        } catch (\Throwable $e) {
            $this->logger->warning(
                message: 'DbContentStorage: exists() check failed.',
                context: ['uuid' => $uuid, 'exception' => $e]
            );
            return false;
        }//end try

        // phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
        $raw = $dashboard->getContent();
        // phpcs:enable

        return ($raw !== null && $raw !== '');
    }//end exists()
}//end class
