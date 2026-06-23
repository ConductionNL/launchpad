<?php

/**
 * AdminSettingKey Enum
 *
 * Canonical list of all admin-setting keys stored in oc_mydash_admin_settings.
 * Use this enum as the single source of truth for key names; AdminSetting
 * keeps backward-compatible string constants that alias the enum values.
 *
 * Groups:
 *  - Dashboard defaults:   DEFAULT_PERMISSION_LEVEL, ALLOW_USER_DASHBOARDS,
 *                          ALLOW_MULTIPLE_DASHBOARDS, DEFAULT_GRID_COLUMNS
 *  - Workspace routing:    GROUP_ORDER
 *  - Link-button widget:   LINK_CREATE_FILE_EXTENSIONS
 *  - Comments:             COMMENTS_ENABLED_DEFAULT
 *  - Footer:               FOOTER_ENABLED, FOOTER_HTML, FOOTER_CONFIG,
 *                          FOOTER_BACKGROUND_COLOR, FOOTER_TEXT_COLOR
 *  - Setup wizard:         SETUP_WIZARD_COMPLETE, CONTENT_STORAGE
 *
 * @category Db
 * @package  OCA\LaunchPad\Db
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/launchpad-adopt-or-abstractions/tasks.md#task-10
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Db;

/**
 * All admin-setting keys used by LaunchPad.
 *
 * The enum value is the string stored in oc_mydash_admin_settings.setting_key.
 *
 * @spec openspec/changes/launchpad-adopt-or-abstractions/tasks.md#task-10
 */
enum AdminSettingKey: string
{
    case DEFAULT_PERMISSION_LEVEL  = 'default_permission_level';
    case ALLOW_USER_DASHBOARDS     = 'allow_user_dashboards';
    case ALLOW_MULTIPLE_DASHBOARDS = 'allow_multiple_dashboards';
    case DEFAULT_GRID_COLUMNS      = 'default_grid_columns';
    case GROUP_ORDER = 'group_order';
    case LINK_CREATE_FILE_EXTENSIONS = 'link_create_file_extensions';
    case COMMENTS_ENABLED_DEFAULT    = 'comments_enabled_default';
    case FOOTER_ENABLED = 'footer_enabled';
    case FOOTER_HTML    = 'footer_html';
    case FOOTER_CONFIG  = 'footer_config';
    case FOOTER_BACKGROUND_COLOR = 'footer_background_color';
    case FOOTER_TEXT_COLOR       = 'footer_text_color';
    case SETUP_WIZARD_COMPLETE   = 'setup_wizard_complete';
    case CONTENT_STORAGE         = 'content_storage';
    case DEFAULT_SHARE_PERMISSION_LEVEL = 'default_share_permission_level';
    case FORCED_SHARE_GROUPS            = 'forced_share_groups';
    case LEGACY_WIDGET_BRIDGE_ENABLED   = 'legacy_widget_bridge_enabled';
    case MAX_DASHBOARDS_PER_USER        = 'max_dashboards_per_user';
    case MAX_WIDGETS_PER_DASHBOARD      = 'max_widgets_per_dashboard';
}//end enum
