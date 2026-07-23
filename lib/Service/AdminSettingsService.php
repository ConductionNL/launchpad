<?php

/**
 * AdminSettingsService
 *
 * Service for managing admin settings.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Service for managing admin settings.
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-7
 */
class AdminSettingsService
{
    /**
     * Constructor
     *
     * @param AdminSettingMapper $settingMapper The admin setting mapper.
     */
    public function __construct(
        private readonly AdminSettingMapper $settingMapper,
    ) {
    }//end __construct()

    /**
     * Default link-button-widget createFile extension allow-list
     * (REQ-LBN-004). Mirrored on
     * {@see \OCA\LaunchPad\Service\FileService::DEFAULT_ALLOWED_EXTENSIONS}
     * so the admin UI can render the default before
     * {@see FileService::getAllowedExtensions()} is consulted.
     *
     * @var array<int, string>
     */
    public const DEFAULT_LINK_CREATE_FILE_EXTENSIONS = [
        'txt',
        'md',
        'docx',
        'xlsx',
        'csv',
        'odt',
    ];

    /**
     * Get all admin settings with defaults.
     *
     * @return array The settings array.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-1
     */
    public function getSettings(): array
    {
        $settings = $this->settingMapper->getAllAsArray();

        $permKey  = AdminSetting::KEY_DEFAULT_PERMISSION_LEVEL;
        $permDef  = Dashboard::PERMISSION_ADD_ONLY;
        $userKey  = AdminSetting::KEY_ALLOW_USER_DASHBOARDS;
        $multiKey = AdminSetting::KEY_ALLOW_MULTIPLE_DASHBOARDS;
        $gridKey  = AdminSetting::KEY_DEFAULT_GRID_COLUMNS;
        $extKey   = AdminSetting::KEY_LINK_CREATE_FILE_EXTENSIONS;

        $storedExt = ($settings[$extKey] ?? null);
        if (is_array($storedExt) === false || count($storedExt) === 0) {
            $storedExt = self::DEFAULT_LINK_CREATE_FILE_EXTENSIONS;
        }

        $storageKey = AdminSetting::KEY_CONTENT_STORAGE;

        $sharePermKey   = AdminSetting::KEY_DEFAULT_SHARE_PERMISSION_LEVEL;
        $forcedShareKey = AdminSetting::KEY_FORCED_SHARE_GROUPS;
        $bridgeKey      = AdminSetting::KEY_LEGACY_WIDGET_BRIDGE_ENABLED;

        $forcedShareGroups = ($settings[$forcedShareKey] ?? null);
        if (is_array($forcedShareGroups) === false) {
            $forcedShareGroups = [];
        }

        // Dashboard-quota-limits REQ-QUOTA-001: both quotas default to
        // `0` (unlimited) so a fresh / upgraded instance behaves exactly
        // as before. Stored values are clamped on write, so a defensive
        // clamp on read is belt-and-braces against hand-edited rows.
        $maxDashKey   = AdminSetting::KEY_MAX_DASHBOARDS_PER_USER;
        $maxWidgetKey = AdminSetting::KEY_MAX_WIDGETS_PER_DASHBOARD;

        // tile-quick-search REQ-QSEARCH-004: fall back to the safe
        // 'none' default when unset, OR when a previously-stored value no
        // longer validates (e.g. an admin-edited DB row) — never surface
        // a value the frontend can't safely act on.
        $fallbackKey             = AdminSetting::KEY_QUICKSEARCH_FALLBACK_TARGET;
        $storedFallbackTarget    = $settings[$fallbackKey] ?? null;
        $quicksearchFallbackTarget = is_string($storedFallbackTarget) === true
            && $this->isValidQuicksearchFallbackTarget(value: $storedFallbackTarget) === true
                ? $storedFallbackTarget
                : self::DEFAULT_QUICKSEARCH_FALLBACK_TARGET;

        return [
            'defaultPermissionLevel'      => $settings[$permKey] ?? $permDef,
            // REQ-ASET-003 (extended): default `false` — admins MUST opt in
            // to personal dashboard creation.
            'allowUserDashboards'         => $settings[$userKey] ?? false,
            'allowMultipleDashboards'     => $settings[$multiKey] ?? true,
            'defaultGridColumns'          => $settings[$gridKey] ?? 12,
            'linkCreateFileExtensions'    => $storedExt,
            // REQ-GFSB-006: surface active storage backend in admin settings.
            'launchpad.content_storage'   => $settings[$storageKey] ?? 'database',
            // Dashboard-sharing spec: org-wide share defaults surfaced in
            // Beheer ▸ Sharing. Default permission level mirrors the
            // dashboard default; forced-share groups default to none.
            'defaultSharePermissionLevel' => $settings[$sharePermKey] ?? $permDef,
            'forcedShareGroups'           => array_values($forcedShareGroups),
            // Legacy-widget-bridge spec: bridge defaults to ON so existing
            // bridged placements keep rendering after upgrade.
            'legacyWidgetBridgeEnabled'   => $settings[$bridgeKey] ?? true,
            // Dashboard-quota-limits REQ-QUOTA-001: numeric governance
            // quotas. `0` = unlimited (no enforcement).
            'maxDashboardsPerUser'        => $this->clampQuota(value: $settings[$maxDashKey] ?? 0),
            'maxWidgetsPerDashboard'      => $this->clampQuota(value: $settings[$maxWidgetKey] ?? 0),
            // tile-quick-search REQ-QSEARCH-004: 'none' | 'unified-search'
            // | an https URL template containing '{query}'.
            'quicksearchFallbackTarget'   => $quicksearchFallbackTarget,
        ];
    }//end getSettings()

    /**
     * Lower bound for the numeric quota settings (dashboard-quota-limits
     * REQ-QUOTA-001). `0` means unlimited.
     *
     * @var int
     */
    public const QUOTA_MIN = 0;

    /**
     * Upper bound for the numeric quota settings (dashboard-quota-limits
     * REQ-QUOTA-001). Values above this are clamped down.
     *
     * @var int
     */
    public const QUOTA_MAX = 10000;

    /**
     * Clamp an admin-supplied quota value into `[QUOTA_MIN, QUOTA_MAX]`
     * (dashboard-quota-limits REQ-QUOTA-001 / REQ-ASET-014).
     *
     * Non-integer / non-numeric input collapses to `0` (unlimited) so a
     * malformed body can never disable an instance by persisting garbage,
     * and a negative value clamps up to `0` rather than being rejected —
     * matching the lenient clamp contract the other numeric settings use.
     *
     * @param mixed $value The raw value (from the request body or a stored row).
     *
     * @return int The clamped non-negative integer.
     *
     * @spec openspec/changes/dashboard-quota-limits/specs/dashboard-quota-limits/spec.md#req-quota-001-quota-admin-settings
     */
    public function clampQuota(mixed $value): int
    {
        if (is_int($value) === false && is_numeric($value) === false) {
            return self::QUOTA_MIN;
        }

        $int = (int) $value;
        if ($int < self::QUOTA_MIN) {
            return self::QUOTA_MIN;
        }

        if ($int > self::QUOTA_MAX) {
            return self::QUOTA_MAX;
        }

        return $int;
    }//end clampQuota()

    /**
     * Valid values for the `launchpad.content_storage` setting (REQ-GFSB-006).
     *
     * @var list<string>
     */
    public const VALID_CONTENT_STORAGE_VALUES = ['database', 'groupfolder'];

    /**
     * tile-quick-search REQ-QSEARCH-004: no-match fallback disabled — Enter
     * on a zero-match query takes no navigation action.
     *
     * @var string
     */
    public const QUICKSEARCH_FALLBACK_NONE = 'none';

    /**
     * tile-quick-search REQ-QSEARCH-004: no-match fallback hands the query
     * to Nextcloud unified search.
     *
     * @var string
     */
    public const QUICKSEARCH_FALLBACK_UNIFIED_SEARCH = 'unified-search';

    /**
     * Default `quicksearch_fallback_target` when no admin setting row
     * exists — no navigation on no-match Enter (REQ-QSEARCH-004 "No
     * fallback configured" scenario is the safe out-of-the-box default).
     *
     * @var string
     */
    public const DEFAULT_QUICKSEARCH_FALLBACK_TARGET = self::QUICKSEARCH_FALLBACK_NONE;

    /**
     * Validate a `quicksearch_fallback_target` value (REQ-QSEARCH-004
     * "Fallback template validation" scenario). Valid values are the two
     * literal targets, or an `https` URL template containing the
     * `{query}` placeholder.
     *
     * @param string|null $value the candidate value.
     *
     * @return bool true when `$value` is a valid fallback target.
     *
     * @spec openspec/changes/tile-quick-search/specs/tile-quick-search/spec.md
     */
    public function isValidQuicksearchFallbackTarget(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        if ($value === self::QUICKSEARCH_FALLBACK_NONE || $value === self::QUICKSEARCH_FALLBACK_UNIFIED_SEARCH) {
            return true;
        }

        return $this->isValidQuicksearchFallbackUrlTemplate(value: $value);
    }//end isValidQuicksearchFallbackTarget()

    /**
     * Validate the `https` + `{query}`-placeholder shape of a web-search
     * fallback URL template (REQ-QSEARCH-004). Substitutes a harmless
     * probe string for `{query}` before validating so a template like
     * `https://example.org/search?q={query}` — not a valid URL as
     * written — validates correctly.
     *
     * @param string $value the candidate URL template.
     *
     * @return bool true when `$value` is a valid `https` URL template
     *              containing `{query}`.
     */
    private function isValidQuicksearchFallbackUrlTemplate(string $value): bool
    {
        if (trim($value) === '' || str_contains(haystack: $value, needle: '{query}') === false) {
            return false;
        }

        $probe = str_replace(search: '{query}', replace: 'quicksearch-validation-probe', subject: $value);

        if (parse_url(url: $probe, component: PHP_URL_SCHEME) !== 'https') {
            return false;
        }

        return filter_var(value: $probe, filter: FILTER_VALIDATE_URL) !== false;
    }//end isValidQuicksearchFallbackUrlTemplate()

    /**
     * Update admin settings.
     *
     * @param string|null $defaultPermLevel            Default permission level.
     * @param bool|null   $allowUserDash               Allow user dashboards.
     * @param bool|null   $allowMultiDash              Allow multiple dashboards.
     * @param int|null    $defaultGridCols             Default grid columns.
     * @param array|null  $linkCreateFileExts          link-button-widget
     *                                                 createFile
     *                                                 extension
     *                                                 allow-list
     *                                                 (REQ-LBN-004).
     * @param string|null $contentStorage              Content storage backend
     *                                                 (`database` or
     *                                                 `groupfolder`).
     *                                                 REQ-GFSB-006.
     * @param string|null $defaultSharePermissionLevel Org-wide default share
     *                                                 permission level
     *                                                 (dashboard-sharing spec).
     * @param array|null  $forcedShareGroups           Groups every new dashboard is
     *                                                 force-shared with
     *                                                 (dashboard-sharing spec).
     * @param bool|null   $legacyWidgetBridgeEnabled   Enable / disable the
     *                                                 legacy widget bridge
     *                                                 (legacy-widget-bridge
     *                                                 spec).
     * @param int|null    $maxDashboardsPerUser        Maximum personal
     *                                                 dashboards per user
     *                                                 (`0` = unlimited;
     *                                                 clamped to
     *                                                 `[0, 10000]`).
     *                                                 dashboard-quota-limits
     *                                                 REQ-QUOTA-001.
     * @param int|null    $maxWidgetsPerDashboard      Maximum placements per
     *                                                 dashboard (`0` =
     *                                                 unlimited; clamped to
     *                                                 `[0, 10000]`).
     *                                                 dashboard-quota-limits
     *                                                 REQ-QUOTA-001.
     * @param string|null $quicksearchFallbackTarget   No-match fallback for
     *                                                 the on-dashboard quick
     *                                                 search: `'none'`,
     *                                                 `'unified-search'`, or
     *                                                 an `https` URL
     *                                                 template containing
     *                                                 `{query}`.
     *                                                 tile-quick-search
     *                                                 REQ-QSEARCH-004.
     *
     * @return void
     *
     * @throws \InvalidArgumentException When `$contentStorage`,
     *                                   `$defaultSharePermissionLevel`, or
     *                                   `$quicksearchFallbackTarget` is not
     *                                   a valid value.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-2
     */
    public function updateSettings(
        ?string $defaultPermLevel=null,
        ?bool $allowUserDash=null,
        ?bool $allowMultiDash=null,
        ?int $defaultGridCols=null,
        ?array $linkCreateFileExts=null,
        ?string $contentStorage=null,
        ?string $defaultSharePermissionLevel=null,
        ?array $forcedShareGroups=null,
        ?bool $legacyWidgetBridgeEnabled=null,
        ?int $maxDashboardsPerUser=null,
        ?int $maxWidgetsPerDashboard=null,
        ?string $quicksearchFallbackTarget=null
    ): void {
        if ($defaultPermLevel !== null) {
            $this->settingMapper->setSetting(
                key: AdminSetting::KEY_DEFAULT_PERMISSION_LEVEL,
                value: $defaultPermLevel
            );
        }

        if ($allowUserDash !== null) {
            $this->settingMapper->setSetting(
                key: AdminSetting::KEY_ALLOW_USER_DASHBOARDS,
                value: $allowUserDash
            );
        }

        if ($allowMultiDash !== null) {
            $this->settingMapper->setSetting(
                key: AdminSetting::KEY_ALLOW_MULTIPLE_DASHBOARDS,
                value: $allowMultiDash
            );
        }

        if ($defaultGridCols !== null) {
            $this->settingMapper->setSetting(
                key: AdminSetting::KEY_DEFAULT_GRID_COLUMNS,
                value: $defaultGridCols
            );
        }

        if ($linkCreateFileExts !== null) {
            $this->settingMapper->setSetting(
                key: AdminSetting::KEY_LINK_CREATE_FILE_EXTENSIONS,
                value: $this->normaliseExtensions(input: $linkCreateFileExts)
            );
        }

        if ($contentStorage !== null) {
            if (in_array(needle: $contentStorage, haystack: self::VALID_CONTENT_STORAGE_VALUES, strict: true) === false) {
                throw new \InvalidArgumentException(
                    message: "Invalid value for launchpad.content_storage. Must be 'db' or 'groupfolder'."
                );
            }

            $this->settingMapper->setSetting(
                key: AdminSetting::KEY_CONTENT_STORAGE,
                value: $contentStorage
            );
        }

        // Dashboard-quota-limits REQ-QUOTA-001 / REQ-ASET-014: persist the
        // numeric quotas clamped into `[0, 10000]` so a negative or
        // out-of-range value is never stored.
        if ($maxDashboardsPerUser !== null) {
            $this->settingMapper->setSetting(
                key: AdminSetting::KEY_MAX_DASHBOARDS_PER_USER,
                value: $this->clampQuota(value: $maxDashboardsPerUser)
            );
        }

        if ($maxWidgetsPerDashboard !== null) {
            $this->settingMapper->setSetting(
                key: AdminSetting::KEY_MAX_WIDGETS_PER_DASHBOARD,
                value: $this->clampQuota(value: $maxWidgetsPerDashboard)
            );
        }

        // tile-quick-search REQ-QSEARCH-004 "Fallback template validation"
        // scenario: reject an invalid target at save time rather than
        // silently storing something the frontend can't act on.
        if ($quicksearchFallbackTarget !== null) {
            if ($this->isValidQuicksearchFallbackTarget(value: $quicksearchFallbackTarget) === false) {
                throw new InvalidArgumentException(
                    message: "Invalid value for quicksearchFallbackTarget. Must be 'none', 'unified-search', or an https URL template containing '{query}'."
                );
            }

            $this->settingMapper->setSetting(
                key: AdminSetting::KEY_QUICKSEARCH_FALLBACK_TARGET,
                value: $quicksearchFallbackTarget
            );
        }

        $this->persistSharingAndBridgeSettings(
            defaultSharePermissionLevel: $defaultSharePermissionLevel,
            forcedShareGroups: $forcedShareGroups,
            legacyWidgetBridgeEnabled: $legacyWidgetBridgeEnabled
        );
    }//end updateSettings()

    /**
     * Persist the dashboard-sharing + legacy-widget-bridge admin settings.
     *
     * Extracted from {@see self::updateSettings()} so the main entry point
     * stays under the cyclomatic-complexity threshold. Each value is only
     * written when non-null (partial-patch contract).
     *
     * @param string|null $defaultSharePermissionLevel Org-wide default share
     *                                                 permission level.
     * @param array|null  $forcedShareGroups           Forced-share group ids.
     * @param bool|null   $legacyWidgetBridgeEnabled   Bridge enable flag.
     *
     * @return void
     *
     * @throws \InvalidArgumentException When the share permission level is invalid.
     */
    private function persistSharingAndBridgeSettings(
        ?string $defaultSharePermissionLevel,
        ?array $forcedShareGroups,
        ?bool $legacyWidgetBridgeEnabled
    ): void {
        if ($defaultSharePermissionLevel !== null) {
            if (in_array(needle: $defaultSharePermissionLevel, haystack: self::VALID_SHARE_PERMISSION_LEVELS, strict: true) === false) {
                throw new InvalidArgumentException(
                    message: "Invalid value for defaultSharePermissionLevel. Must be 'view_only', 'add_only' or 'full'."
                );
            }

            $this->settingMapper->setSetting(
                key: AdminSetting::KEY_DEFAULT_SHARE_PERMISSION_LEVEL,
                value: $defaultSharePermissionLevel
            );
        }

        if ($forcedShareGroups !== null) {
            $this->settingMapper->setSetting(
                key: AdminSetting::KEY_FORCED_SHARE_GROUPS,
                value: $this->normaliseGroupList(input: $forcedShareGroups)
            );
        }

        if ($legacyWidgetBridgeEnabled !== null) {
            $this->settingMapper->setSetting(
                key: AdminSetting::KEY_LEGACY_WIDGET_BRIDGE_ENABLED,
                value: $legacyWidgetBridgeEnabled
            );
        }
    }//end persistSharingAndBridgeSettings()

    /**
     * Valid org-wide share permission levels (dashboard-sharing spec). Mirrors
     * the per-dashboard permission levels on {@see Dashboard}.
     *
     * @var list<string>
     */
    public const VALID_SHARE_PERMISSION_LEVELS = [
        Dashboard::PERMISSION_VIEW_ONLY,
        Dashboard::PERMISSION_ADD_ONLY,
        Dashboard::PERMISSION_FULL,
    ];

    /**
     * Normalise an admin-supplied group-id list: drop non-strings and empty
     * entries, trim whitespace, and deduplicate while preserving order. Used
     * for the forced-share-group setting so a hand-crafted request body can
     * never persist garbage (dashboard-sharing spec).
     *
     * @param array<int, mixed> $input The raw group-id list.
     *
     * @return list<string> The cleaned, deduplicated group-id list.
     */
    private function normaliseGroupList(array $input): array
    {
        $result = [];
        foreach ($input as $entry) {
            if (is_string($entry) === false) {
                continue;
            }

            $trimmed = trim($entry);
            if ($trimmed === '') {
                continue;
            }

            $result[$trimmed] = true;
        }

        return array_keys($result);
    }//end normaliseGroupList()

    /**
     * Get the persisted admin-chosen group priority order (REQ-ASET-012).
     *
     * Defensive read: returns `[]` when the row is missing, when the value
     * is not a JSON-encoded list of strings, or when JSON decoding fails.
     * MUST never throw — corrupt data in the database MUST resolve to the
     * factory default so admin UI and downstream resolvers stay alive.
     *
     * @return string[] The ordered list of group IDs, or `[]` when missing
     *                  or corrupt.
     *
     * @spec openspec/specs/admin-settings/spec.md
     */
    public function getGroupOrder(): array
    {
        $raw = $this->settingMapper->getValue(
            key: AdminSetting::KEY_GROUP_ORDER,
            default: null
        );

        if (is_array($raw) === false) {
            // Corrupt or missing — fall back to empty list (REQ-ASET-012).
            return [];
        }

        $result = [];
        foreach ($raw as $entry) {
            // Drop any element that isn't a non-empty string. The persist
            // path validates this, but a hand-edited DB row could still
            // contain garbage.
            if (is_string($entry) === true && $entry !== '') {
                $result[] = $entry;
            }
        }

        return array_values(array_unique($result));
    }//end getGroupOrder()

    /**
     * Persist the admin-chosen group priority order (REQ-ASET-012,
     * REQ-ASET-014).
     *
     * Replaces the persisted value wholesale — no merge semantics. Every
     * element MUST be a non-empty string; throws
     * {@see \InvalidArgumentException} otherwise so the controller can
     * surface HTTP 400. Duplicates are deduplicated (first occurrence
     * wins, preserving order). Unknown (not-currently-in-Nextcloud) IDs
     * are tolerated by design (REQ-ASET-014) — the runtime resolver in
     * `group-routing` drops them.
     *
     * @param array<int, mixed> $groupIds The ordered group IDs (mixed so the
     *                                    runtime defensive check has work
     *                                    to do; static analysis cannot
     *                                    reach the IGroupManager → service
     *                                    payload origin).
     *
     * @return void
     *
     * @throws InvalidArgumentException When any element is not a
     *                                  non-empty string.
     *
     * @spec openspec/specs/admin-settings/spec.md
     */
    public function setGroupOrder(array $groupIds): void
    {
        $deduplicated = [];
        foreach ($groupIds as $entry) {
            if (is_string($entry) === false || $entry === '') {
                throw new InvalidArgumentException(
                    message: 'group_order entries must be non-empty strings'
                );
            }

            if (in_array(needle: $entry, haystack: $deduplicated, strict: true) === true) {
                continue;
            }

            $deduplicated[] = $entry;
        }

        $this->settingMapper->setSetting(
            key: AdminSetting::KEY_GROUP_ORDER,
            value: $deduplicated
        );
    }//end setGroupOrder()

    /**
     * Normalise an admin-supplied extension allow-list.
     *
     * Lowercases each entry, strips leading dots, drops anything that
     * is not a bare alphanumeric token, de-duplicates, and falls back
     * to the default allow-list when the input collapses to empty —
     * the admin cannot brick the feature by saving an empty list.
     *
     * @param array $input Raw admin input.
     *
     * @return array<int, string> The normalised allow-list.
     */
    private function normaliseExtensions(array $input): array
    {
        $normalised = [];
        foreach ($input as $value) {
            if (is_string($value) === false) {
                continue;
            }

            $token = strtolower(string: ltrim(string: trim(string: $value), characters: '.'));
            if ($token === '') {
                continue;
            }

            if (preg_match(pattern: '/^[a-z0-9]+$/', subject: $token) !== 1) {
                continue;
            }

            $normalised[$token] = $token;
        }

        if (count($normalised) === 0) {
            return self::DEFAULT_LINK_CREATE_FILE_EXTENSIONS;
        }

        return array_values(array: $normalised);
    }//end normaliseExtensions()
}//end class
