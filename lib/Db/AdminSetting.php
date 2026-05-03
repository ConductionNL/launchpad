<?php

/**
 * AdminSetting Entity
 *
 * Represents an admin setting entity.
 *
 * @category  Database
 * @package   OCA\MyDash\Db
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12 EUPL-1.2
 * @version   GIT:auto
 * @link      https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\MyDash\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * Admin setting entity for storing key-value configuration.
 *
 * @method string getSettingKey()
 * @method void setSettingKey(string $settingKey)
 * @method string|null getSettingValue()
 * @method void setSettingValue(?string $settingValue)
 * @method string|null getUpdatedAt()
 * @method void setUpdatedAt(?string $updatedAt)
 */
class AdminSetting extends Entity implements JsonSerializable
{

    /**
     * Setting key for default permission level.
     *
     * @var string
     */
    public const KEY_DEFAULT_PERMISSION_LEVEL = 'default_permission_level';

    /**
     * Setting key for allowing user dashboards.
     *
     * @var string
     */
    public const KEY_ALLOW_USER_DASHBOARDS = 'allow_user_dashboards';

    /**
     * Setting key for allowing multiple dashboards.
     *
     * @var string
     */
    public const KEY_ALLOW_MULTIPLE_DASHBOARDS = 'allow_multiple_dashboards';

    /**
     * Setting key for default grid columns.
     *
     * @var string
     */
    public const KEY_DEFAULT_GRID_COLUMNS = 'default_grid_columns';

    /**
     * Setting key for the admin-chosen group priority order
     * (REQ-ASET-012). Persisted as a JSON string list of Nextcloud
     * group IDs in the order the admin chose; corrupt JSON resolves
     * to `[]` at the service layer (defensive read). MyDash treats
     * these as in scope for workspace routing.
     *
     * @var string
     */
    public const KEY_GROUP_ORDER = 'group_order';

    /**
     * Setting key for the link-button-widget createFile extension allow-list.
     *
     * Stored as a JSON array of lowercase extensions without dots
     * (e.g. `["txt","md","docx"]`). Default values are returned by
     * {@see \OCA\MyDash\Service\FileService::getAllowedExtensions()}.
     *
     * @var string
     */
    public const KEY_LINK_CREATE_FILE_EXTENSIONS = 'link_create_file_extensions';

    /**
     * Setting key tracking first-run setup wizard completion (REQ-WIZ-001).
     *
     * Stored as JSON `true` once the admin clicks "Finish" in the wizard or
     * the `mydash:setup` CLI command runs to completion. Defaults to `false`
     * (banner visible) when the row is missing.
     *
     * @var string
     */
    public const KEY_SETUP_WIZARD_COMPLETE = 'setup_wizard_complete';

    /**
     * Setting key for the dashboard content storage backend (REQ-WIZ-003).
     *
     * Stored as a JSON string: either `"database"` (default) or
     * `"groupfolder"` once the admin completes Step 2. The
     * `groupfolder-storage-backend` capability is the eventual consumer;
     * the wizard merely persists the choice.
     *
     * @var string
     */
    public const KEY_CONTENT_STORAGE = 'content_storage';

    /**
     * Setting key for the structured-mode footer configuration (REQ-WIZ-007).
     *
     * Stored as a JSON object describing footer layout + items, written by
     * the `footer-customization` capability. The wizard simply reads the
     * presence of a non-empty value to derive Step 6's "done" status.
     *
     * @var string
     */
    public const KEY_FOOTER_CONFIG = 'footer_config';

    /**
     * The setting key.
     *
     * @var string
     */
    protected string $settingKey = '';

    /**
     * The setting value.
     *
     * @var string|null
     */
    protected ?string $settingValue = null;

    /**
     * The update timestamp (ISO-8601 / 'c' format).
     *
     * @var string|null
     */
    protected ?string $updatedAt = null;

    /**
     * Constructor
     *
     * Registers column types for proper ORM handling.
     *
     * @return void
     */
    public function __construct()
    {
        $this->addType(fieldName: 'id', type: 'integer');
    }//end __construct()

    /**
     * Get setting value as decoded JSON.
     *
     * @return mixed The decoded value.
     */
    public function getValueDecoded(): mixed
    {
        if ($this->settingValue === null) {
            return null;
        }

        $decoded = json_decode(json: $this->settingValue, associative: true);
        return $decoded;
    }//end getValueDecoded()

    /**
     * Set setting value from any value (will be JSON encoded).
     *
     * @param mixed $value The value to encode and store.
     *
     * @return void
     */
    public function setValueEncoded(mixed $value): void
    {
        // Entity setters resolve via __call which forwards $args[0]; named
        // parameters MUST NOT be used here (Entity __call would receive
        // $args = ['paramName' => $value] and use the wrong key).
        // phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
        $this->setSettingValue(json_encode($value));
    }//end setValueEncoded()

    /**
     * Serialize to JSON.
     *
     * @return array The serialized admin setting.
     */
    public function jsonSerialize(): array
    {
        return [
            'id'        => $this->getId(),
            'key'       => $this->settingKey,
            'value'     => $this->getValueDecoded(),
            'updatedAt' => $this->updatedAt,
        ];
    }//end jsonSerialize()
}//end class
