<?php

/**
 * AdminSettingKey Enum
 *
 * Canonical list of all admin-setting keys stored in oc_launchpad_admin_settings.
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
 *  - Setup wizard:         SETUP_WIZARD_COMPLETE
 *  - Quick search:         QUICKSEARCH_FALLBACK_TARGET (tile-quick-search),
 *                          SEARCH_SHORTCUTS (search-ai-prefix-shortcuts)
 *  - Sharing:              DEFAULT_SHARE_PERMISSION_LEVEL, FORCED_SHARE_GROUPS,
 *                          SHARING_ALLOWED_GROUPS, SHARING_RECIPIENT_SCOPE,
 *                          SHARING_ALLOWED, PUBLIC_LINKS_ALLOWED,
 *                          LEAVER_HANDOVER
 *  - Announcements:        ANNOUNCEMENT_EDITOR_GROUPS
 *  - Limits:               MAX_DASHBOARDS_PER_USER, MAX_WIDGETS_PER_DASHBOARD
 *  - Widgets:              LEGACY_WIDGET_BRIDGE_ENABLED,
 *                          FILTER_TELEMETRY_ENABLED
 *  - Public start page:    PUBLIC_START_ENABLED, PUBLIC_START_SHARE_TOKEN
 *  - Tiles:                TILE_ALLOWED_SCHEMES, SSO_LAUNCH_TEMPLATES,
 *                          OFFICE_NETWORKS
 *  - Look:                 CUSTOM_CSS
 *  - Communities:          PINNED_COMMUNITIES
 *  - Default app:          DEFAULT_APP_ENABLED
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
 * The enum value is the string stored in oc_launchpad_admin_settings.setting_key.
 *
 * @spec openspec/changes/launchpad-adopt-or-abstractions/tasks.md#task-10
 */
enum AdminSettingKey: string {
	case DEFAULT_PERMISSION_LEVEL = 'default_permission_level';
	case ALLOW_USER_DASHBOARDS = 'allow_user_dashboards';
	case ALLOW_MULTIPLE_DASHBOARDS = 'allow_multiple_dashboards';
	case DEFAULT_GRID_COLUMNS = 'default_grid_columns';
	case GROUP_ORDER = 'group_order';
	case LINK_CREATE_FILE_EXTENSIONS = 'link_create_file_extensions';
	case COMMENTS_ENABLED_DEFAULT = 'comments_enabled_default';
	case FOOTER_ENABLED = 'footer_enabled';
	case FOOTER_HTML = 'footer_html';
	case FOOTER_CONFIG = 'footer_config';
	case FOOTER_BACKGROUND_COLOR = 'footer_background_color';
	case FOOTER_TEXT_COLOR = 'footer_text_color';
	case SETUP_WIZARD_COMPLETE = 'setup_wizard_complete';
	case DEFAULT_SHARE_PERMISSION_LEVEL = 'default_share_permission_level';
	case FORCED_SHARE_GROUPS = 'forced_share_groups';
	// Engagement-announcements D6: groups whose members may write announcements, besides administrators.
	case ANNOUNCEMENT_EDITOR_GROUPS = 'announcement_editor_groups';
	case SHARING_ALLOWED_GROUPS = 'sharing_allowed_groups';
	case SHARING_RECIPIENT_SCOPE = 'sharing_recipient_scope';
	// REQ-SHARE-015: who may share, `everyone`, `groups` or `none`, and whether public links may be created.
	case SHARING_ALLOWED = 'sharing_allowed';
	case PUBLIC_LINKS_ALLOWED = 'public_links_allowed';
	// REQ-KIOSK-007: whether a dashboard's Sharing tab may make a screen link; on when not set.
	case KIOSK_SCREEN_LINKS_ALLOWED = 'kiosk_screen_links_allowed';
	// REQ-SHARE-016: where a leaver's dashboards go, `{manager: bool, fallbackUid: string}`.
	case LEAVER_HANDOVER = 'leaver_handover';
	case LEGACY_WIDGET_BRIDGE_ENABLED = 'legacy_widget_bridge_enabled';
	case MAX_DASHBOARDS_PER_USER = 'max_dashboards_per_user';
	case MAX_WIDGETS_PER_DASHBOARD = 'max_widgets_per_dashboard';
	case QUICKSEARCH_FALLBACK_TARGET = 'quicksearch_fallback_target';
	case SEARCH_SHORTCUTS = 'search_shortcuts';
	case PUBLIC_START_ENABLED = 'public_start_enabled';
	case PUBLIC_START_SHARE_TOKEN = 'public_start_share_token';
	case TILE_ALLOWED_SCHEMES = 'tile_allowed_schemes';
	case SSO_LAUNCH_TEMPLATES = 'sso_launch_templates';
	case CUSTOM_CSS = 'custom_css';
	// Drill-down-cross-widget-filter REQ-CWF-010: org-level opt-in for filter telemetry, off by default.
	case FILTER_TELEMETRY_ENABLED = 'filter_telemetry_enabled';
	case OFFICE_NETWORKS = 'office_networks';
	case PINNED_COMMUNITIES = 'pinned_communities';
	// REQ-PSP-003: LaunchPad is the default app after sign-in unless switched off; on by default.
	case DEFAULT_APP_ENABLED = 'default_app_enabled';
}//end enum
