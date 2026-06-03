<?php

/**
 * DashboardContentStorageFactory
 *
 * Reads the `content_storage` admin setting and instantiates the appropriate
 * {@see DashboardContentStorageInterface} implementation. When the setting is
 * absent or set to `'db'` (the default), {@see DbContentStorage} is returned.
 * When set to `'groupfolder'`, {@see GroupFolderContentStorage} is returned.
 *
 * @category Service
 * @package  OCA\MyDash\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-4
 */

declare(strict_types=1);

namespace OCA\MyDash\Service;

use OCA\MyDash\Db\AdminSettingKey;
use OCA\MyDash\Db\AdminSettingMapper;
use OCA\MyDash\Service\DashboardContentStorage\DashboardContentStorageInterface;
use OCA\MyDash\Service\DashboardContentStorage\DbContentStorage;
use OCA\MyDash\Service\DashboardContentStorage\GroupFolderContentStorage;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Factory that instantiates the active storage backend.
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-4
 */
class DashboardContentStorageFactory
{

    /**
     * Enum value for the database backend (canonical).
     *
     * @var string
     */
    public const BACKEND_DB = 'db';

    /**
     * Legacy alias for the database backend (written by the setup wizard).
     *
     * The wizard uses `'database'` while the admin-settings spec uses `'db'`.
     * Both resolve to the same {@see DbContentStorage} implementation.
     *
     * @var string
     */
    public const BACKEND_DB_LEGACY = 'database';

    /**
     * Enum value for the GroupFolder backend.
     *
     * @var string
     */
    public const BACKEND_GROUPFOLDER = 'groupfolder';

    /**
     * Valid backend values accepted by admin settings validation.
     *
     * @var array<int, string>
     */
    public const VALID_BACKENDS = [
        self::BACKEND_DB,
        self::BACKEND_DB_LEGACY,
        self::BACKEND_GROUPFOLDER,
    ];

    /**
     * Constructor.
     *
     * @param AdminSettingMapper        $settingMapper Admin setting mapper (reads
     *                                                 the `content_storage` key).
     * @param DbContentStorage          $dbStorage     Pre-wired DB backend
     *                                                 (injected by DI
     *                                                 container).
     * @param GroupFolderContentStorage $gfStorage     Pre-wired GroupFolder backend.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-4
     */
    public function __construct(
        private readonly AdminSettingMapper $settingMapper,
        private readonly DbContentStorage $dbStorage,
        private readonly GroupFolderContentStorage $gfStorage,
    ) {
    }//end __construct()

    /**
     * Return the active storage backend based on the admin setting.
     *
     * When `launchpad.content_storage = 'groupfolder'`, the GroupFolder
     * backend is returned. In all other cases (including missing setting),
     * the DB backend is returned as the safe default. REQ-GFSB-002.
     *
     * @return DashboardContentStorageInterface The active backend.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-4
     */
    public function getStorage(): DashboardContentStorageInterface
    {
        $backend = $this->getBackendSetting();
        if ($backend === self::BACKEND_GROUPFOLDER) {
            return $this->gfStorage;
        }

        // Both 'db' and the legacy 'database' value resolve to the DB backend.
        return $this->dbStorage;
    }//end getStorage()

    /**
     * Read the persisted `content_storage` admin setting value.
     *
     * Returns `'db'` when no value is stored (safe default). REQ-GFSB-002.
     *
     * @return string The setting value (`'db'` or `'groupfolder'`).
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-4
     */
    public function getBackendSetting(): string
    {
        try {
            $setting = $this->settingMapper->findByKey(
                key: AdminSettingKey::CONTENT_STORAGE->value
            );
            $value   = (string) ($setting->getValueDecoded() ?? self::BACKEND_DB);
            if (in_array($value, self::VALID_BACKENDS, strict: true) === true) {
                return $value;
            }

            return self::BACKEND_DB;
        } catch (DoesNotExistException) {
            return self::BACKEND_DB;
        }
    }//end getBackendSetting()

    /**
     * Set the `content_storage` admin setting.
     *
     * @param string $backend One of `'db'` or `'groupfolder'`.
     *
     * @return void
     *
     * @throws \InvalidArgumentException When the value is not a valid backend.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-4
     */
    public function setBackendSetting(string $backend): void
    {
        if (in_array($backend, self::VALID_BACKENDS, strict: true) === false) {
            throw new \InvalidArgumentException(
                sprintf(
                    "Invalid value for launchpad.content_storage. Must be '%s' or '%s'.",
                    self::BACKEND_DB,
                    self::BACKEND_GROUPFOLDER
                )
            );
        }

        $this->settingMapper->setSetting(
            key: AdminSettingKey::CONTENT_STORAGE->value,
            value: $backend
        );
    }//end setBackendSetting()
}//end class
