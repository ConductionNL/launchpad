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

    // ------------------------------------------------------------------
    // BC string aliases for AdminSettingKey enum values.
    // These constants remain here so existing call-sites do not need
    // updating; prefer AdminSettingKey::<CASE>->value in new code.
    // @see \OCA\MyDash\Db\AdminSettingKey
    // @spec openspec/changes/launchpad-adopt-or-abstractions/tasks.md#task-10
    // ------------------------------------------------------------------

    /**
     * BC alias for AdminSettingKey::DEFAULT_PERMISSION_LEVEL.
     *
     * @var string
     *
     * @see AdminSettingKey::DEFAULT_PERMISSION_LEVEL
     */
    public const KEY_DEFAULT_PERMISSION_LEVEL = AdminSettingKey::DEFAULT_PERMISSION_LEVEL->value;

    /**
     * BC alias for AdminSettingKey::ALLOW_USER_DASHBOARDS.
     *
     * @var string
     *
     * @see AdminSettingKey::ALLOW_USER_DASHBOARDS
     */
    public const KEY_ALLOW_USER_DASHBOARDS = AdminSettingKey::ALLOW_USER_DASHBOARDS->value;

    /**
     * BC alias for AdminSettingKey::ALLOW_MULTIPLE_DASHBOARDS.
     *
     * @var string
     *
     * @see AdminSettingKey::ALLOW_MULTIPLE_DASHBOARDS
     */
    public const KEY_ALLOW_MULTIPLE_DASHBOARDS = AdminSettingKey::ALLOW_MULTIPLE_DASHBOARDS->value;

    /**
     * BC alias for AdminSettingKey::DEFAULT_GRID_COLUMNS.
     *
     * @var string
     *
     * @see AdminSettingKey::DEFAULT_GRID_COLUMNS
     */
    public const KEY_DEFAULT_GRID_COLUMNS = AdminSettingKey::DEFAULT_GRID_COLUMNS->value;

    /**
     * BC alias for AdminSettingKey::GROUP_ORDER.
     *
     * @var string
     *
     * @see AdminSettingKey::GROUP_ORDER
     */
    public const KEY_GROUP_ORDER = AdminSettingKey::GROUP_ORDER->value;

    /**
     * BC alias for AdminSettingKey::LINK_CREATE_FILE_EXTENSIONS.
     *
     * @var string
     *
     * @see AdminSettingKey::LINK_CREATE_FILE_EXTENSIONS
     */
    public const KEY_LINK_CREATE_FILE_EXTENSIONS = AdminSettingKey::LINK_CREATE_FILE_EXTENSIONS->value;

    /**
     * BC alias for AdminSettingKey::COMMENTS_ENABLED_DEFAULT.
     *
     * @var string
     *
     * @see AdminSettingKey::COMMENTS_ENABLED_DEFAULT
     */
    public const KEY_COMMENTS_ENABLED_DEFAULT = AdminSettingKey::COMMENTS_ENABLED_DEFAULT->value;

    /**
     * BC alias for AdminSettingKey::FOOTER_ENABLED.
     *
     * @var string
     *
     * @see AdminSettingKey::FOOTER_ENABLED
     */
    public const KEY_FOOTER_ENABLED = AdminSettingKey::FOOTER_ENABLED->value;

    /**
     * BC alias for AdminSettingKey::FOOTER_HTML.
     *
     * @var string
     *
     * @see AdminSettingKey::FOOTER_HTML
     */
    public const KEY_FOOTER_HTML = AdminSettingKey::FOOTER_HTML->value;

    /**
     * BC alias for AdminSettingKey::FOOTER_CONFIG.
     *
     * @var string
     *
     * @see AdminSettingKey::FOOTER_CONFIG
     */
    public const KEY_FOOTER_CONFIG = AdminSettingKey::FOOTER_CONFIG->value;

    /**
     * BC alias for AdminSettingKey::FOOTER_BACKGROUND_COLOR.
     *
     * @var string
     *
     * @see AdminSettingKey::FOOTER_BACKGROUND_COLOR
     */
    public const KEY_FOOTER_BACKGROUND_COLOR = AdminSettingKey::FOOTER_BACKGROUND_COLOR->value;

    /**
     * BC alias for AdminSettingKey::FOOTER_TEXT_COLOR.
     *
     * @var string
     *
     * @see AdminSettingKey::FOOTER_TEXT_COLOR
     */
    public const KEY_FOOTER_TEXT_COLOR = AdminSettingKey::FOOTER_TEXT_COLOR->value;

    /**
     * BC alias for AdminSettingKey::SETUP_WIZARD_COMPLETE.
     *
     * @var string
     *
     * @see AdminSettingKey::SETUP_WIZARD_COMPLETE
     */
    public const KEY_SETUP_WIZARD_COMPLETE = AdminSettingKey::SETUP_WIZARD_COMPLETE->value;

    /**
     * BC alias for AdminSettingKey::CONTENT_STORAGE.
     *
     * @var string
     *
     * @see AdminSettingKey::CONTENT_STORAGE
     */
    public const KEY_CONTENT_STORAGE = AdminSettingKey::CONTENT_STORAGE->value;

    /**
     * BC alias for AdminSettingKey::DEFAULT_SHARE_PERMISSION_LEVEL.
     *
     * @var string
     *
     * @see AdminSettingKey::DEFAULT_SHARE_PERMISSION_LEVEL
     */
    public const KEY_DEFAULT_SHARE_PERMISSION_LEVEL = AdminSettingKey::DEFAULT_SHARE_PERMISSION_LEVEL->value;

    /**
     * BC alias for AdminSettingKey::FORCED_SHARE_GROUPS.
     *
     * @var string
     *
     * @see AdminSettingKey::FORCED_SHARE_GROUPS
     */
    public const KEY_FORCED_SHARE_GROUPS = AdminSettingKey::FORCED_SHARE_GROUPS->value;

    /**
     * BC alias for AdminSettingKey::LEGACY_WIDGET_BRIDGE_ENABLED.
     *
     * @var string
     *
     * @see AdminSettingKey::LEGACY_WIDGET_BRIDGE_ENABLED
     */
    public const KEY_LEGACY_WIDGET_BRIDGE_ENABLED = AdminSettingKey::LEGACY_WIDGET_BRIDGE_ENABLED->value;

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
