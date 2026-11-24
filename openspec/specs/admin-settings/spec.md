---
status: done
---

# Admin Settings Specification

## Purpose

Admin settings provide Nextcloud administrators with global configuration options for the LaunchPad app. These settings control system-wide behavior such as whether users can create their own dashboards, how many dashboards they can have, default permission levels for new dashboards, and default grid configuration. Settings are stored as key-value pairs in a dedicated database table and are applied as defaults or constraints across the entire LaunchPad installation.

## Data Model

### Admin Settings (oc_launchpad_admin_settings)
Settings are stored as key-value pairs:
- **id**: Auto-increment integer primary key
- **settingKey**: Unique string identifier for the setting (STRING)
- **settingValue**: JSON-encoded string value (STRING, nullable). Values are stored via `json_encode()` and retrieved via `json_decode()`.
- **updatedAt**: Timestamp (DATETIME)

### Defined Settings

| Setting Key (DB) | API Response Key | Type | Default | Description |
|------------------|------------------|------|---------|-------------|
| `allow_user_dashboards` | `allowUserDashboards` | boolean | `false` | Whether non-admin users can create their own personal dashboards. Admins MUST opt in (REQ-ASET-003). |
| `allow_multiple_dashboards` | `allowMultipleDashboards` | boolean | `true` | Whether users can have more than one dashboard |
| `default_permission_level` | `defaultPermissionLevel` | string | `add_only` | Default permission level for user-created dashboards |
| `default_grid_columns` | `defaultGridColumns` | integer | `12` | Default number of grid columns for new dashboards |
| `link_create_file_extensions` | `linkCreateFileExtensions` | `string[]` | `["txt","md","docx","xlsx","csv","odt"]` | Extensions the link-button widget's "create file" action may create (REQ-LBN-004). An empty stored list resolves to the default. |
| `default_share_permission_level` | `defaultSharePermissionLevel` | string | `add_only` | Org-wide default permission for a new share (dashboard-sharing spec) |
| `forced_share_groups` | `forcedShareGroups` | `string[]` | `[]` | Groups every new dashboard is force-shared with (dashboard-sharing spec) |
| `sharing_allowed_groups` | `sharingAllowedGroups` | `string[]` | `[]` | Groups whose members may share a dashboard; empty means everyone may (dashboard-sharing REQ-SHARE-014) |
| `sharing_recipient_scope` | `sharingRecipientScope` | string | `everyone` | Whom a dashboard may be shared with: `everyone`, or `shared-groups` (only people and groups the sharer shares a group with). Anything else reads as `everyone` (dashboard-sharing REQ-SHARE-014). |
| `legacy_widget_bridge_enabled` | `legacyWidgetBridgeEnabled` | boolean | `false` | Whether the workspace reads Nextcloud's widget registry (legacy-widget-bridge spec) |
| `max_dashboards_per_user` | `maxDashboardsPerUser` | integer | `0` | Personal dashboard quota per user, `0` = unlimited (REQ-QUOTA-001) |
| `max_widgets_per_dashboard` | `maxWidgetsPerDashboard` | integer | `0` | Placement quota per dashboard, `0` = unlimited (REQ-QUOTA-001) |
| `quicksearch_fallback_target` | `quicksearchFallbackTarget` | string | `none` | Quick-search no-match fallback: `none`, `unified-search` or an https URL template (REQ-QSEARCH-004). A stored value that no longer validates reads as `none`. |
| `group_order` | n/a (separate `/api/admin/groups` endpoints) | `string[]` (JSON) | `[]` | Ordered list of Nextcloud group IDs that are "active" for LaunchPad workspace routing (REQ-ASET-012). Read via `AdminSettingsService::getGroupOrder()`; written via `setGroupOrder()`. Corrupt JSON resolves to `[]`. |

NOTE: The DB stores settings with snake_case keys, but the API response returns camelCase keys. The factory default for `defaultPermissionLevel` is `add_only` (Dashboard::PERMISSION_ADD_ONLY), NOT `full`. The API update endpoint accepts each setting under the key the GET response uses (`defaultPermissionLevel`, `allowUserDashboards`, `allowMultipleDashboards`, `defaultGridColumns`, `linkCreateFileExtensions`) and under its short parameter name (`defaultPermLevel`, `allowUserDash`, `allowMultiDash`, `defaultGridCols`, `linkCreateFileExts`). When both arrive in one body the short name wins. See REQ-ASET-002.

## Requirements

### Requirement: Retrieve Admin Settings (REQ-ASET-001)

Administrators MUST be able to retrieve all current admin settings via the API. The endpoint returns a flat JSON object with the thirteen settings listed under Defined Settings, each under its API response key. `group_order` is not among them; it has its own endpoints (REQ-ASET-012).

#### Scenario: Get all settings with defaults
@e2e exclude needs a fresh installation with no settings row; the suite shares one instance and no endpoint deletes a stored setting (PUT treats null as not supplied). Pinned by tests/Unit/Service/AdminSettingsServiceTest.php::testGetSettingsReturnsExactlyTheDocumentedKeysAtTheirDefaults (which lists 22 keys, not thirteen; see the format-consistency test).
- GIVEN no admin settings have been explicitly configured (fresh installation)
- WHEN the admin sends GET /api/admin/settings
- THEN the system MUST return HTTP 200 with all thirteen settings at their default values:
  ```json
  {
    "defaultPermissionLevel": "add_only",
    "allowUserDashboards": false,
    "allowMultipleDashboards": true,
    "defaultGridColumns": 12,
    "linkCreateFileExtensions": ["txt", "md", "docx", "xlsx", "csv", "odt"],
    "defaultSharePermissionLevel": "add_only",
    "forcedShareGroups": [],
    "sharingAllowedGroups": [],
    "sharingRecipientScope": "everyone",
    "legacyWidgetBridgeEnabled": false,
    "maxDashboardsPerUser": 0,
    "maxWidgetsPerDashboard": 0,
    "quicksearchFallbackTarget": "none"
  }
  ```
- NOTE: `allowUserDashboards` defaults to `false` (REQ-ASET-003) — admins MUST opt in to personal dashboard creation.

#### Scenario: Get settings after modification
- GIVEN the admin has set `allowUserDashboards` to `false`
- WHEN the admin sends GET /api/admin/settings
- THEN the response MUST carry `"allowUserDashboards": false`
- AND the other twelve keys MUST still be present with their current values

#### Scenario: Non-admin user retrieves settings
- GIVEN a regular user "alice"
- WHEN she sends GET /api/admin/settings
- THEN the system MUST return HTTP 403
- AND admin settings MUST NOT be exposed to non-admin users

#### Scenario: Settings used by non-admin endpoints
- GIVEN the admin has set `allowUserDashboards` to `false`
- WHEN user "alice" sends POST /api/dashboard (to create a dashboard)
- THEN the system MUST internally check the `allowUserDashboards` setting via `PermissionService::canCreateDashboard()`
- AND the non-admin user MUST NOT need to call GET /api/admin/settings to experience the effect

#### Scenario: Settings response format consistency
- GIVEN the admin has configured various settings at different times
- WHEN GET /api/admin/settings is called
- THEN the response MUST always return exactly the thirteen keys listed under Defined Settings: `defaultPermissionLevel`, `allowUserDashboards`, `allowMultipleDashboards`, `defaultGridColumns`, `linkCreateFileExtensions`, `defaultSharePermissionLevel`, `forcedShareGroups`, `sharingAllowedGroups`, `sharingRecipientScope`, `legacyWidgetBridgeEnabled`, `maxDashboardsPerUser`, `maxWidgetsPerDashboard`, `quicksearchFallbackTarget`
- AND no additional keys MUST be present in the response
- NOTE: This scenario said "exactly four keys" long after the response had grown to twelve. The list above is taken from `AdminSettingsService::getSettings()`; a key added there MUST be added here and to the Defined Settings table.
- AND the response MUST be a flat JSON object (no nesting)

#### Scenario: The retired content storage key is not returned
@e2e exclude needs a content_storage row written straight into oc_launchpad_admin_settings; no endpoint writes that key (PUT ignores unknown keys) and "stays unread" is a database-only fact. Pinned by tests/Unit/Service/AdminSettingsServiceTest.php::testGetSettingsReturnsCamelCaseKeys and tests/Unit/Db/AdminSettingKeyTest.php::testContentStorageKeyIsRetired.
- GIVEN an instance that still holds a `content_storage` row written by the retired setup-wizard storage step
- WHEN the admin sends GET /api/admin/settings
- THEN the response MUST NOT contain `launchpad.content_storage`
- AND the stored row MUST stay unread (decision 131)

#### Scenario: The retired start-page key is not returned
- GIVEN an instance where `start_page_without_navigation` may still be stored from when the option existed (runtime-shell REQ-SHELL-009)
- WHEN the admin sends a PUT /api/admin/settings that still carries `"startPageWithoutNavigation": true`
- THEN the PUT MUST answer 200 and store nothing for that key
- AND a following GET /api/admin/settings MUST NOT contain `startPageWithoutNavigation`

### Requirement: Update Admin Settings (REQ-ASET-002)

Administrators MUST be able to update individual or multiple admin settings in a single PUT request.

#### Scenario: Update a single boolean setting
- GIVEN the admin wants to disable user dashboard creation
- WHEN they send PUT /api/admin/settings with body `{"allowUserDash": false}`
- THEN the system MUST update the `allowUserDashboards` setting to `false`
- AND the response MUST return HTTP 200 with `{"status": "ok"}`
- NOTE: The update endpoint accepts a setting under the key GET returns (`allowUserDashboards`, `allowMultipleDashboards`, `defaultGridColumns`, `linkCreateFileExtensions`, `defaultPermissionLevel`) as well as under its short parameter name (`allowUserDash`, `allowMultiDash`, `defaultGridCols`, `linkCreateFileExts`, `defaultPermLevel`). The response returns `{"status": "ok"}`, NOT the full settings object.
- NOTE: Until launchpad#605 this endpoint accepted ONLY the short names. Nextcloud leaves a parameter it cannot bind at its default, every one of these defaults to null, and null means "not supplied", so a caller that sent the GET keys wrote nothing and was still answered `{"status": "ok"}`. This note used to describe that as the contract.

#### Scenario: A settings object read from GET can be written back through PUT
- GIVEN the admin reads GET /api/admin/settings
- WHEN they change one value and send it back under the same key, for example PUT with body `{"allowUserDashboards": false}` or `{"defaultGridColumns": 8}`
- THEN the system MUST store the new value
- AND a following GET MUST return it
- AND this MUST hold for each of `allowUserDashboards`, `allowMultipleDashboards`, `defaultGridColumns`, `linkCreateFileExtensions` and `defaultPermissionLevel`

#### Scenario: Both spellings in one body resolve to the short one
- GIVEN the admin sends PUT /api/admin/settings with body `{"allowUserDash": true, "allowUserDashboards": false}`
- WHEN the settings are updated
- THEN `allowUserDashboards` MUST be `true`
- NOTE: The short name is the documented parameter and the one the admin UI sends, so an alias MUST NOT override it.

#### Scenario: Update multiple settings at once
- GIVEN the admin wants to change several settings
- WHEN they send PUT /api/admin/settings with body:
  ```json
  {
    "allowUserDash": true,
    "allowMultiDash": false,
    "defaultGridCols": 8
  }
  ```
- THEN the system MUST update all three specified settings
- AND settings not included in the request MUST remain unchanged

#### Scenario: Update with invalid permission level value
@e2e exclude never built: AdminSettingsService::updateSettings() stores defaultPermLevel unchecked (git log --all -S finds no validation for it in lib/), so "super_admin" is stored and answered 200. No PHPUnit or Newman test pins a 400.
- GIVEN the admin sends PUT /api/admin/settings with body `{"defaultPermLevel": "super_admin"}`
- THEN the system SHOULD return HTTP 400 with a validation error
- AND only `view_only`, `add_only`, and `full` SHOULD be accepted for this setting
- NOTE: Permission level validation is NOT currently implemented -- any string value is accepted

#### Scenario: Update with invalid grid columns value
@e2e exclude never built: defaultGridCols is stored unchecked (no range check exists in lib/ history), so 0 is stored and answered 200. No PHPUnit or Newman test pins a 400.
- GIVEN the admin sends PUT /api/admin/settings with body `{"defaultGridCols": 0}`
- THEN the system SHOULD return HTTP 400 with a validation error
- AND `defaultGridCols` SHOULD be a positive integer between 1 and 24
- NOTE: Grid columns validation is NOT currently implemented -- any integer value is accepted

#### Scenario: Update with invalid boolean value
@e2e exclude never built: no boolean check exists; Nextcloud casts "maybe" to true for the bool parameter and it is stored with 200. No PHPUnit or Newman test pins a 400.
- GIVEN the admin sends PUT /api/admin/settings with body `{"allowUserDash": "maybe"}`
- THEN the system SHOULD return HTTP 400 with a validation error
- AND boolean settings SHOULD only accept `true` or `false`
- NOTE: Boolean validation is NOT currently implemented -- PHP type coercion handles the value

#### Scenario: Non-admin cannot update settings
- GIVEN a regular user "alice"
- WHEN she sends PUT /api/admin/settings with any body
- THEN the system MUST return HTTP 403
- AND no settings MUST be modified
- NOTE: Admin-only access is enforced because the AdminController does NOT have a `#[NoAdminRequired]` attribute

#### Scenario: Update with unknown setting key
- GIVEN the admin sends PUT /api/admin/settings with body `{"unknownSetting": "value"}`
- THEN the system MUST ignore the unknown key (unrecognized parameters are simply not matched to method arguments)
- AND known settings MUST NOT be affected

### Requirement: Allow User Dashboards Setting — runtime gating + envelope (REQ-ASET-003)

The setting `allow_user_dashboards` (boolean stored as `'0'` / `'1'`, default `'0'`) MUST gate every endpoint that **creates** a personal (`type='user'`) dashboard. Read endpoints, update endpoints, and existing personal dashboards MUST remain accessible regardless of the flag's value. Toggling the flag MUST NOT mutate any dashboard records.

The endpoints listed below MUST evaluate the flag at request time and, when it equals `'0'`, MUST return HTTP 403 with response body `{status: 'error', error: 'personal_dashboards_disabled', message: <translated string>}`:

- `POST /api/dashboard` (when payload omits `type` or sets `type='user'`)
- `POST /api/dashboards/{uuid}/fork` (always — fork target is always `type='user'`; the route itself is owned by the `fork-current-as-personal` capability and inherits this gating)

Endpoints that MUST NOT check the flag (so existing personal dashboards remain functional):

- `GET /api/dashboards/visible`
- `GET /api/dashboard/{id}`
- `PUT /api/dashboard/{id}` (existing personal dashboard updates)
- `DELETE /api/dashboard/{id}` (users can still clean up their old personal dashboards)
- `POST /api/dashboard/active`
- `POST /api/dashboard/{id}/activate`
- All `group_shared` and `admin_template` endpoints

Admins MAY always create dashboards regardless of the flag (the `PermissionService::canCreateDashboard()` companion check is layered defense-in-depth; admins bypass it through their group membership).

#### Scenario: Flag off blocks personal dashboard creation
- GIVEN admin setting `allow_user_dashboards = '0'`
- WHEN user "alice" sends `POST /api/dashboard` with body `{"name": "My Test"}`
- THEN the system MUST return HTTP 403 with `{status: 'error', error: 'personal_dashboards_disabled', message: 'Personal dashboards are not enabled by your administrator'}`
- AND no row MUST be inserted into `oc_launchpad_dashboards`

#### Scenario: Flag off blocks fork
- GIVEN admin setting `allow_user_dashboards = '0'`
- AND alice can read group-shared dashboard `S`
- WHEN she sends `POST /api/dashboards/{S.uuid}/fork`
- THEN the system MUST return HTTP 403 with the same `personal_dashboards_disabled` error envelope

#### Scenario: Flag off does not break existing personal dashboards
- GIVEN alice has 2 existing personal dashboards `P1`, `P2`
- AND admin toggles `allow_user_dashboards` from `'1'` to `'0'`
- WHEN alice opens the workspace page
- THEN `P1` and `P2` MUST still appear in `GET /api/dashboards/visible`
- AND alice MUST be able to `PUT /api/dashboard/{P1.id}` and `DELETE` them
- AND alice MUST be able to set either as her active dashboard via `POST /api/dashboard/active`
- AND only `POST /api/dashboard` and fork endpoints MUST return 403

#### Scenario: Toggling does not destructively mutate data
- GIVEN alice has 1 personal dashboard `P1` (active)
- AND admin toggles `allow_user_dashboards` to `'0'` and back to `'1'`
- THEN `P1` MUST still exist with all original fields and placements
- AND `P1.isActive` MUST still be `1` (unchanged)
- AND no rows in `oc_launchpad_dashboards` or `oc_launchpad_widget_placements` MUST have been touched

#### Scenario: Default value when setting is missing
@e2e exclude needs the allow_user_dashboards row to be absent; no endpoint deletes a stored setting and the shared instance has one. Pinned by tests/Unit/Service/DashboardServiceAllowFlagTest.php::testDefaultValueBlocksCreation and tests/Unit/Service/AdminSettingsServiceTest.php::testGetSettingsReturnsDefaults.
- GIVEN a fresh LaunchPad install with no row for `allow_user_dashboards` in `oc_launchpad_admin_settings`
- WHEN any code reads the setting (via `DashboardService::getAllowUserDashboards()`, `PermissionService::canCreateDashboard()`, or `AdminSettingsService::getSettings()`)
- THEN it MUST evaluate to `false` (creation blocked) — the secure default

#### Scenario: Admins can always create dashboards
- GIVEN `allow_user_dashboards` is set to `false`
- WHEN a Nextcloud admin sends POST /api/dashboard with body `{"name": "Admin Dashboard"}`
- THEN the system MUST allow the creation
- AND the admin setting MUST NOT restrict admin users

#### Scenario: Frontend hides create affordance when disabled
- GIVEN `allow_user_dashboards` is set to `false`
- WHEN user "alice" views the LaunchPad workspace
- THEN the "Create dashboard…" entry in `DashboardConfigMenu` MUST NOT be rendered
- AND the empty-state "Create dashboard" button in `Views.vue` MUST NOT be rendered
- AND the empty-state description MUST read "Personal dashboards are not enabled by your administrator"
- AND if the call is invoked anyway (stale UI, direct API call) the backend MUST still return the 403 `personal_dashboards_disabled` envelope (defense in depth)

### Requirement: Allow Multiple Dashboards Setting (REQ-ASET-004)

When `allow_multiple_dashboards` is false, users MUST be limited to one dashboard.

#### Scenario: Second dashboard creation blocked
- GIVEN `allowMultipleDashboards` is set to `false`
- AND user "alice" already has 1 dashboard
- WHEN she sends POST /api/dashboard with body `{"name": "Second Dashboard"}`
- THEN the system MUST return HTTP 403 with a message indicating multiple dashboards are not allowed
- AND no dashboard MUST be created

#### Scenario: First dashboard creation allowed
- GIVEN `allowMultipleDashboards` is set to `false`
- AND user "bob" has no dashboards
- WHEN he sends POST /api/dashboard with body `{"name": "My Dashboard"}`
- THEN the system MUST allow the creation (this is his first dashboard)
- AND the response MUST return HTTP 201

#### Scenario: Multiple dashboards allowed (default)
- GIVEN `allowMultipleDashboards` is set to `true` (default)
- AND user "alice" already has 3 dashboards
- WHEN she sends POST /api/dashboard with body `{"name": "Fourth Dashboard"}`
- THEN the system MUST allow the creation
- AND the response MUST return HTTP 201

#### Scenario: Existing dashboards preserved when setting is disabled
- GIVEN user "alice" has 3 dashboards
- AND the admin sets `allow_multiple_dashboards` to `false`
- WHEN alice views her dashboards via GET /api/dashboards
- THEN all 3 existing dashboards MUST still be returned
- AND alice MUST NOT be able to create additional dashboards
- AND alice MUST be able to delete dashboards to get down to 1

#### Scenario: A new template reaches only users on their first access
- GIVEN `allowMultipleDashboards` is set to `false`
- AND user "alice" has 1 dashboard
- AND a new admin template targets the group of alice and of "bob", who has never opened LaunchPad
- WHEN alice and bob open LaunchPad
- THEN the system MUST NOT create a template copy for alice, who keeps her 1 dashboard: templates are distributed on first access only (REQ-TMPL-005), whatever this setting says
- AND bob MUST receive the template copy as his first dashboard
- AND an admin who wants the template on existing users' dashboards uses re-sync (REQ-TMPL-005 is unaffected by it)

### Requirement: Default Permission Level Setting (REQ-ASET-005)

The `defaultPermissionLevel` setting MUST be applied as a fallback when resolving effective permission levels. The factory default is `add_only` (Dashboard::PERMISSION_ADD_ONLY).

#### Scenario: Default permission level applied to new dashboard
- GIVEN `defaultPermissionLevel` is set to `add_only`
- WHEN user "alice" sends POST /api/dashboard with body `{"name": "My Dashboard"}`
- THEN the created dashboard MUST have `permissionLevel: "full"` (user-created dashboards use `DashboardFactory::create()` which hardcodes `PERMISSION_FULL`)
- AND the `defaultPermissionLevel` admin setting acts as a fallback in `PermissionService::getEffectivePermissionLevel()` only when the dashboard's own `permissionLevel` is empty

#### Scenario: Factory default permission (add_only)
@e2e exclude needs a dashboard with no permissionLevel and no template, which no endpoint creates (DashboardFactory sets full, template copies take the template level). Pinned by tests/Unit/Service/PermissionServiceAccessGoldenTest.php (dashboard 8: empty level, stored default add_only). Note: with the row absent PermissionService::getEffectivePermissionLevel() falls back to full, not add_only.
- GIVEN `defaultPermissionLevel` is at its factory default of `add_only`
- WHEN `PermissionService::getEffectivePermissionLevel()` is called for a dashboard with no `permissionLevel` set and no `basedOnTemplate`
- THEN the effective level MUST resolve to `add_only` (the admin default)
- NOTE: The factory default is `add_only` (Dashboard::PERMISSION_ADD_ONLY), NOT `full`

#### Scenario: Default permission does not affect template copies
- GIVEN `defaultPermissionLevel` is set to `view_only`
- AND an admin template exists with `permissionLevel: "full"`
- WHEN a user receives a copy of that template
- THEN the copy MUST have `permissionLevel: "full"` (from the template)
- AND the global default MUST NOT override the template's permission level

#### Scenario: Admin can override default for individual templates
- GIVEN `defaultPermissionLevel` is set to `add_only`
- WHEN the admin creates a template with `permissionLevel: "full"`
- THEN the template MUST have `permissionLevel: "full"`
- AND the global default MUST NOT constrain template configuration

#### Scenario: Permission resolution chain
- GIVEN a dashboard with `basedOnTemplate: 42` and the template has `permissionLevel: "add_only"`
- WHEN `PermissionService::getEffectivePermissionLevel()` is called
- THEN the system MUST check in order: (1) source template's `permissionLevel`, (2) dashboard's own `permissionLevel`, (3) admin default setting
- AND the first non-empty value in the chain MUST be returned

### Requirement: Default Grid Columns Setting (REQ-ASET-006)

The `defaultGridColumns` setting MUST be applied to new dashboards when no explicit gridColumns is specified. The admin page offers it as a select of 6, 8 and 12 columns.

#### Scenario: Default grid columns applied
- GIVEN `defaultGridColumns` is set to `8`
- WHEN user "alice" sends POST /api/dashboard with body `{"name": "My Dashboard"}`
- THEN the created dashboard MUST have `gridColumns: 8`

#### Scenario: Explicit grid columns overrides default
- GIVEN `defaultGridColumns` is set to `8`
- WHEN user "alice" sends POST /api/dashboard with body `{"name": "My Dashboard", "gridColumns": 12}`
- THEN the created dashboard MUST have `gridColumns: 12` (explicit value takes precedence)

#### Scenario: Default grid columns for template copies
- GIVEN `defaultGridColumns` is set to `8`
- AND an admin template exists with `gridColumns: 12`
- WHEN a user receives a copy of that template
- THEN the copy MUST have `gridColumns: 12` (from the template)
- AND the global default MUST NOT override the template's grid configuration

### Requirement: Settings Persistence (REQ-ASET-007)

Admin settings MUST be persisted across server restarts and app updates.

#### Scenario: Settings survive server restart
@e2e exclude needs restarting the Nextcloud server mid-test, which the shared e2e instance cannot do. No PHPUnit or Newman test pins it.
- GIVEN the admin has configured `allowUserDashboards: false`
- WHEN the Nextcloud server is restarted
- THEN GET /api/admin/settings MUST still return `allowUserDashboards: false`
- AND the setting MUST NOT revert to its default value

#### Scenario: Settings survive app update
@e2e exclude needs upgrading the installed app mid-test. No PHPUnit or Newman test pins it.
- GIVEN the admin has configured custom settings
- WHEN the LaunchPad app is updated to a new version
- THEN all previously configured settings MUST be preserved
- AND new settings introduced in the update MUST use their default values

#### Scenario: Factory reset behavior
- GIVEN the admin wants to reset all settings to defaults
- WHEN they send PUT /api/admin/settings with all default values:
  ```json
  {
    "allowUserDash": true,
    "allowMultiDash": true,
    "defaultPermLevel": "add_only",
    "defaultGridCols": 12
  }
  ```
- THEN all settings MUST be reset to their factory defaults
- AND the response MUST confirm the update

### Requirement: Admin Settings UI (REQ-ASET-008)

The admin settings MUST be accessible via a Nextcloud admin panel page. The
form MUST save each change as soon as it is made; it has no Save button.

#### Scenario: Admin settings page is registered
- GIVEN a Nextcloud admin user
- WHEN they navigate to Settings > Administration
- THEN a "LaunchPad" entry MUST appear in the admin settings navigation
- AND clicking it MUST display the LaunchPad admin settings page

#### Scenario: Regular user cannot access admin settings page
- GIVEN a regular (non-admin) Nextcloud user
- WHEN they navigate to Settings
- THEN the "LaunchPad" entry MUST NOT appear in their settings navigation
- AND direct URL access to the admin settings page MUST return HTTP 403

#### Scenario: Settings form layout
- GIVEN the admin opens the LaunchPad admin settings page
- THEN the page MUST display:
  - A toggle for "Allow user dashboards" (on/off)
  - A toggle for "Allow multiple dashboards" (on/off)
  - A dropdown for "Default permission level" (view_only, add_only, full)
  - A select for "Default grid columns" offering 6, 8 and 12 (Ruben, 21 Nov 2026, following the product)
- AND each setting MUST show its current value
- AND the form MUST NOT show a Save button, since every change is saved at once

#### Scenario: Each change is saved at once
- GIVEN the admin opens the LaunchPad admin settings page
- WHEN the admin switches "Allow user dashboards" off
- THEN the page MUST send the change to PUT /api/admin/settings straight away, without a Save step
- AND GET /api/admin/settings MUST reflect the change

### Requirement: Settings Impact on Existing Data (REQ-ASET-009)

Admin settings changes MUST only affect future operations and MUST NOT retroactively modify existing dashboards.

#### Scenario: Changing default permission level does not modify existing dashboards
- GIVEN user "alice" has a dashboard with `permissionLevel: "full"`
- WHEN the admin changes `defaultPermissionLevel` to `view_only`
- THEN alice's existing dashboard MUST retain `permissionLevel: "full"`
- AND the new default MUST only apply to dashboards created after the change

#### Scenario: Changing grid columns default does not modify existing dashboards
- GIVEN user "alice" has a dashboard with `gridColumns: 12`
- WHEN the admin changes `defaultGridColumns` to `8`
- THEN alice's existing dashboard MUST retain `gridColumns: 12`
- AND only newly created dashboards MUST use the new default of `8`

#### Scenario: Disabling user dashboards does not delete existing dashboards
- GIVEN 50 users each have personal dashboards
- WHEN the admin sets `allowUserDashboards` to `false`
- THEN all 50 existing dashboards MUST be preserved
- AND users MUST continue to access their existing dashboards
- AND only new dashboard creation MUST be blocked

### Requirement: Settings Concurrency (REQ-ASET-010)

Concurrent admin settings updates MUST be handled safely.

#### Scenario: Simultaneous updates from two admin sessions
- GIVEN admin user A sets `allowUserDash: false` at the same time admin user B sets `allowMultiDash: false`
- WHEN both PUT requests are processed
- THEN each setting MUST be independently updated without data loss
- AND the final state MUST reflect both changes (last-write-wins per individual setting key)

#### Scenario: Rapid successive updates
- GIVEN the admin clicks Save multiple times quickly
- WHEN multiple PUT requests are sent in rapid succession
- THEN the system MUST process each request independently
- AND the final state MUST reflect the last request's values

### Requirement: Settings API Error Handling (REQ-ASET-011)

The settings API MUST return consistent error responses for various failure scenarios.

#### Scenario: Database connection failure during settings retrieval
@e2e exclude needs the database taken down under a running instance shared by the suite. No PHPUnit or Newman test pins it (AdminController::getSettings() has no try/catch).
- GIVEN the database is temporarily unavailable
- WHEN the admin sends GET /api/admin/settings
- THEN the system MUST return HTTP 500 with an error message
- AND the error MUST NOT expose internal database details

#### Scenario: Database connection failure during settings update
@e2e exclude needs the database taken down under a running instance shared by the suite. No PHPUnit or Newman test pins it.
- GIVEN the database is temporarily unavailable
- WHEN the admin sends PUT /api/admin/settings with valid data
- THEN the system MUST return HTTP 500 with an error message
- AND no partial updates MUST be persisted

#### Scenario: Empty request body for update
- GIVEN the admin sends PUT /api/admin/settings with an empty body `{}`
- WHEN the request is processed
- THEN the system MUST return HTTP 200 with `{"status": "ok"}`
- AND no settings MUST be modified (all parameters are null, so no updates are applied)

### Requirement: Group order setting (REQ-ASET-012)

The system MUST persist an ordered list of Nextcloud group IDs as the global setting `group_order` (JSON `string[]`, default `[]`). The order MUST be preserved exactly as provided. The setting determines which groups are "active" for LaunchPad workspace routing (REQ-TMPL-012). Corrupt or unparseable JSON in the database MUST resolve to `[]` at read time without throwing — the resolver and admin UI MUST never see a fatal error from a malformed value.

#### Scenario: Persist ordered list

- GIVEN admin sends `POST /api/admin/groups` with body `{"groups": ["engineering", "all-staff", "marketing"]}`
- THEN the setting `group_order` MUST be persisted as the JSON string `["engineering","all-staff","marketing"]`
- AND a subsequent `GET /api/admin/groups` MUST return the same order in `active`

#### Scenario: Empty list clears active groups

- GIVEN admin sends `POST /api/admin/groups` with body `{"groups": []}`
- THEN the setting MUST be persisted as `[]`
- AND `resolvePrimaryGroup` MUST return `'default'` for every user (per REQ-TMPL-012)

#### Scenario: Replace-wholesale, not merge

- GIVEN current `group_order = ["a", "b", "c"]`
- WHEN admin sends `POST /api/admin/groups` with body `{"groups": ["c", "b"]}`
- THEN the setting MUST become exactly `["c", "b"]`
- AND `"a"` MUST be removed (no implicit merge)

#### Scenario: Corrupt DB JSON falls back to empty array
@e2e exclude needs a hand-written corrupt group_order row; no endpoint stores invalid JSON. Pinned by tests/Unit/Service/AdminSettingsServiceTest.php::testGetGroupOrderReturnsEmptyOnCorruptValue.

- GIVEN the row `group_order` exists in `oc_launchpad_admin_settings` with `setting_value = '{not-json'`
- WHEN any caller invokes `AdminSettingsService::getGroupOrder()`
- THEN the method MUST return `[]`
- AND MUST NOT throw an exception
- AND `GET /api/admin/groups` MUST return `active: []` in this state

#### Scenario: Default when setting absent
@e2e exclude needs the group_order row to be absent; no endpoint deletes it. Pinned by tests/Unit/Service/AdminSettingsServiceTest.php::testGetGroupOrderReturnsEmptyWhenRowAbsent.

- GIVEN no `group_order` row has ever been written to `oc_launchpad_admin_settings`
- WHEN `AdminSettingsService::getGroupOrder()` is called
- THEN it MUST return `[]` (factory default)

### Requirement: List groups for admin UI (REQ-ASET-013)

The system MUST expose `GET /api/admin/groups` returning `{active: [id…], inactive: [id…], allKnown: [{id, displayName}…]}`:

- `active`: the persisted `group_order` list (in admin-chosen order, preserved exactly).
- `inactive`: every Nextcloud group ID NOT in `active`, sorted by display name (case-insensitive).
- `allKnown`: full descriptor list (`{id, displayName}`) for every Nextcloud group currently known, so the UI can render display names without a second round-trip.

Stale IDs (present in `active` but no longer in Nextcloud) MUST remain in `active` so the admin can see and remove them, but MUST NOT appear in `allKnown` (no display name available). The UI is expected to render stale IDs with a "(removed)" affix.

#### Scenario: Lists are disjoint and exhaustive

- GIVEN Nextcloud has groups `["a", "b", "c", "d"]` and `group_order = ["b", "d"]`
- WHEN admin sends `GET /api/admin/groups`
- THEN the response MUST be `{active: ["b", "d"], inactive: ["a", "c"], allKnown: [{id:"a",displayName:"..."}, {id:"b",displayName:"..."}, {id:"c",displayName:"..."}, {id:"d",displayName:"..."}]}`
- AND `active ∪ inactive` MUST equal the set of IDs in `allKnown`
- AND `active ∩ inactive` MUST be empty

#### Scenario: Order in `active` matches admin-chosen order

- GIVEN `group_order = ["zebra", "alpha", "marigold"]`
- WHEN admin sends `GET /api/admin/groups`
- THEN `active` MUST be exactly `["zebra", "alpha", "marigold"]` (no alphabetical re-sort)
- AND `inactive` MUST be sorted alphabetically by `displayName`

#### Scenario: Empty group_order — all groups inactive

- GIVEN `group_order = []` and Nextcloud has groups `["a", "b"]`
- WHEN admin sends `GET /api/admin/groups`
- THEN `active` MUST be `[]`
- AND `inactive` MUST contain both `"a"` and `"b"`

#### Scenario: Stale group IDs surface in active list

- GIVEN `group_order = ["deleted-group", "engineering"]` and Nextcloud no longer has `"deleted-group"`
- WHEN admin sends `GET /api/admin/groups`
- THEN the response's `active` MUST still include `"deleted-group"` (so admin can see and remove it)
- AND `allKnown` MUST NOT include it (no display name to show)
- AND `inactive` MUST NOT include it
- NOTE: The UI SHOULD render stale IDs with a "(removed)" affix.

### Requirement: Admin guard and payload validation (REQ-ASET-014)

`POST /api/admin/groups` MUST be admin-only (`IGroupManager::isAdmin`). Non-admins MUST receive HTTP 403 with no side effects. `GET /api/admin/groups` MUST also be admin-only because the inactive list reveals every group on the system.

The `POST` payload MUST be validated:
- Top-level `groups` key MUST exist and MUST be a JSON array.
- Every element MUST be a non-empty string.
- Duplicate IDs in the payload MUST be deduplicated (first occurrence wins, preserving order).
- Validation failures MUST return HTTP 400 with no side effects on the persisted setting.

Unknown (not currently in Nextcloud) IDs MUST NOT cause validation failure — they are tolerated and persisted (per REQ-ASET-013 stale-ID handling).

#### Scenario: Non-admin POST rejected

- GIVEN user "alice" who is not an administrator
- WHEN she sends `POST /api/admin/groups` with any body
- THEN the system MUST return HTTP 403
- AND the persisted `group_order` MUST be unchanged

#### Scenario: Non-admin GET rejected

- GIVEN user "alice" who is not an administrator
- WHEN she sends `GET /api/admin/groups`
- THEN the system MUST return HTTP 403

#### Scenario: Missing `groups` key rejected

- GIVEN admin sends `POST /api/admin/groups` with body `{}`
- THEN the system MUST return HTTP 400
- AND the persisted `group_order` MUST be unchanged

#### Scenario: Non-string element rejected

- GIVEN admin sends `POST /api/admin/groups` with body `{"groups": ["engineering", 42, "marketing"]}`
- THEN the system MUST return HTTP 400
- AND the persisted `group_order` MUST be unchanged

#### Scenario: Duplicate IDs deduplicated

- GIVEN admin sends `POST /api/admin/groups` with body `{"groups": ["a", "b", "a", "c"]}`
- THEN the persisted `group_order` MUST be exactly `["a", "b", "c"]` (first occurrence kept, duplicates removed)
- AND the response MUST be HTTP 200

#### Scenario: Unknown IDs accepted

- GIVEN admin sends `POST /api/admin/groups` with body `{"groups": ["does-not-exist", "engineering"]}`
- AND `"does-not-exist"` is not a known Nextcloud group
- THEN the request MUST succeed (HTTP 200)
- AND `group_order` MUST be persisted as `["does-not-exist", "engineering"]`

### Requirement: Initial-state mirror of the allow-user-dashboards flag (REQ-ASET-015)

The current value of `allow_user_dashboards` MUST be pushed as initial state `allowUserDashboards: bool` on every workspace and admin page render so the frontend can hide the "+ New Dashboard" affordance and any "Fork as personal" affordance without an extra round-trip. The push MUST happen via `InitialStateBuilder::setAllowUserDashboards()` (REQ-INIT-002) — direct calls to `IInitialState::provideInitialState()` are forbidden by the `lint:initial-state` CI guard.

#### Scenario: Initial state matches setting on the workspace page
- GIVEN admin setting `allow_user_dashboards = '1'`
- WHEN any user loads the workspace page (`PageController::index`)
- THEN the page initial state MUST include `allowUserDashboards: true`
- AND the workspace `provide` MUST expose the same value to descendants

#### Scenario: Initial state matches setting on the admin page
- GIVEN admin setting `allow_user_dashboards = '0'`
- WHEN an admin loads the LaunchPad admin settings page (`LaunchPadAdmin::getForm`)
- THEN the admin initial state MUST include `allowUserDashboards: false`

#### Scenario: Frontend honours the flag
- GIVEN initial state has `allowUserDashboards: false`
- WHEN the workspace renders
- THEN the "Create dashboard…" entry in `DashboardConfigMenu` MUST NOT be visible
- AND the empty-state "Create dashboard" button in `Views.vue` MUST NOT be visible
- AND any "Fork as personal" affordance MUST NOT be visible
- AND attempting to invoke the underlying actions via direct API call MUST still hit the 403 (defense in depth — REQ-ASET-003)

#### Scenario: Toast surfaces the stable error code on direct API invocation
- GIVEN the frontend `useDashboardStore.createDashboard` is invoked
- AND the backend returns HTTP 403 with `error: 'personal_dashboards_disabled'`
- THEN the store MUST surface a localised toast via `@nextcloud/dialogs::showError` reading the `Personal dashboards are not enabled by your administrator` translation
- AND the original error MUST be re-thrown so callers can short-circuit

### Requirement: Program schemes and single sign-on launch templates are admin settings (REQ-ASET-020)

The admin settings MUST hold `tileAllowedSchemes` (stored as `tile_allowed_schemes`), a list of lower-case URL schemes a program tile may use, and `ssoLaunchTemplates` (stored as `sso_launch_templates`), a list of `{key, name, urlTemplate}` where `urlTemplate` is an https address containing `{appId}` exactly once. Both MUST be empty by default. A write MUST be refused with HTTP 400, naming the entry, when a scheme is not lower-case or is one of `javascript`, `data`, `file`, `vbscript`, `about` or `blob`, or when a template is not https, lacks or repeats `{appId}`, carries another placeholder or credentials, or reuses a key; a refused write MUST leave both lists as they were. A stored value that no longer validates MUST read as empty. With these two, `GET /api/admin/settings` (REQ-ASET-001) returns fourteen keys; the REQ-ASET-001 list is updated when this change is archived.

#### Scenario: A script scheme cannot be allowed

- **GIVEN** an administrator on the LaunchPad admin page
- **WHEN** they save `javascript` as an allowed scheme
- **THEN** the response is 400 naming `javascript` and the allowed list is unchanged

#### Scenario: A launch template must be https with the app id

- **GIVEN** an administrator defining a launch template
- **WHEN** they save `http://idp.example.nl/{appId}`
- **THEN** the response is 400 and no template is stored

### Requirement: One list of allowed websites with a purpose per site (REQ-ASET-021)

The General tab (board LpBeheerAlgemeen) SHALL hold one block "Allowed websites" ("Toegestane websites"): a list of
hosts, each with its purpose: Embed ("Insluiten"), Fetch ("Ophalen") or Embed and fetch, with "Add website" and Save.
The list SHALL be stored as one admin setting. Embed SHALL allow the host for the embedded page widget and for images
from outside; Fetch SHALL allow it for live tiles, health pings and external calendar feeds. Each of those features
SHALL check a URL against the hosts allowed for its purpose and SHALL stay fail-closed: a host not on the list, or
on it for the other purpose only, SHALL be refused as before. A host SHALL be saved lower case, without scheme, path
or port, and an entry that is not a host SHALL be refused with a message. On the first upgrade, the hosts of the
existing per-feature allow-lists SHALL be merged into the list with the purpose they had, so nothing that worked
stops working.

#### Scenario: Power BI may be embedded, not called

- **GIVEN** the list holds `app.powerbi.com` with purpose Embed
- **WHEN** an author places an embedded page from `https://app.powerbi.com/...`
- **THEN** the widget saves and renders
- **AND** a live tile with a URL on `app.powerbi.com` is refused at save time

#### Scenario: The KCC API may be called, not embedded

- **GIVEN** the list holds `kcc-api.zuiddrecht.nl` with purpose Fetch
- **WHEN** an author configures a live tile on `https://kcc-api.zuiddrecht.nl/wachttijd`
- **THEN** the tile fetches its value
- **AND** an embedded page on that host is refused

#### Scenario: Existing lists survive the upgrade

- **GIVEN** before the upgrade `livetile_allowed_hosts` held `kcc-api.zuiddrecht.nl` and the embed list held `app.powerbi.com`
- **WHEN** the app is upgraded
- **THEN** the Allowed websites block lists `kcc-api.zuiddrecht.nl` with Fetch and `app.powerbi.com` with Embed

### Requirement: The administration is a Launchpad page over the full width (REQ-ADM-030)

The Launchpad administration SHALL be a page of Launchpad itself at `/apps/launchpad/admin/<tab>`, with Launchpad's top bar, the title "Launchpad administration: <tab>", the path "Administration settings / Launchpad / <tab>", the tabs over the full width and no left menu. The page SHALL check on the server, before it renders, that the reader is an administrator or a delegated Launchpad administrator.

#### Scenario: Open the administration

- **GIVEN** beheer is a Launchpad administrator
- **WHEN** she chooses Settings, Administration settings in the More menu
- **THEN** the page opens without Nextcloud's settings menu, on the tab General

#### Scenario: Not an administrator

- **WHEN** Pieter opens `/apps/launchpad/admin/general`
- **THEN** the server answers 403 and renders nothing of the page

### Requirement: Nextcloud's settings point to the page (REQ-ADM-031)

The Launchpad entry under Nextcloud's administration settings SHALL say where the administration is and link to the page.

#### Scenario: From Nextcloud's settings

- **WHEN** an administrator opens Administration settings, Launchpad in Nextcloud
- **THEN** she sees one line and a link that opens the Launchpad administration page

## Non-Functional Requirements

- **Performance**: GET /api/admin/settings MUST return within 100ms. Settings lookups during user operations (e.g., `PermissionService::canCreateDashboard()`) query the `AdminSettingMapper` each time; caching is NOT currently implemented.
- **Security**: All admin settings endpoints MUST require Nextcloud admin authentication. Settings MUST NOT be exposed to non-admin users in any way (not even setting keys).
- **Data integrity**: Settings MUST survive server restarts and app updates. Missing settings MUST fall back to documented defaults without errors.
- **Accessibility**: The admin settings form MUST have proper labels, be keyboard-navigable, and meet WCAG AA standards. Toggle states MUST be communicated to screen readers.
- **Localization**: All setting labels, descriptions, validation messages, and success/error notifications MUST support English and Dutch.

### Current Implementation Status

**Fully implemented:**
- REQ-ASET-001 (Retrieve Admin Settings): `AdminSettingsService::getSettings()` in `lib/Service/AdminSettingsService.php` returns all 4 settings with documented defaults. `AdminController::getSettings()` in `lib/Controller/AdminController.php` exposes GET /api/admin/settings. Non-admin access is blocked because AdminController lacks `#[NoAdminRequired]`.
- REQ-ASET-002 (Update Admin Settings): `AdminSettingsService::updateSettings()` accepts abbreviated camelCase params (`defaultPermLevel`, `allowUserDash`, `allowMultiDash`, `defaultGridCols`). `AdminController::updateSettings()` returns `{"status": "ok"}`.
- REQ-ASET-003 (Allow User Dashboards — runtime gating): `DashboardService::getAllowUserDashboards()` and `DashboardService::assertPersonalDashboardsAllowed()` in `lib/Service/DashboardService.php` are the canonical reader and gate. `DashboardApiController::create()` calls the assert FIRST and returns the stable `{status:'error', error:'personal_dashboards_disabled', message:...}` envelope mapped from `PersonalDashboardsDisabledException` (`lib/Exception/PersonalDashboardsDisabledException.php`). The defense-in-depth `PermissionService::canCreateDashboard()` and `AdminSettingsService::getSettings()` defaults also return `false` so a missing row blocks creation everywhere.
- REQ-ASET-015 (Initial-state mirror): `PageController::index` and `LaunchPadAdmin::getForm` both call `DashboardService::getAllowUserDashboards()` and push the value via `InitialStateBuilder::setAllowUserDashboards()`. The frontend reads it via `loadInitialState('workspace' | 'admin')` and provides it down the tree (REQ-INIT-004); `DashboardConfigMenu`, `Views.vue`, and `AdminSettings.vue` inject it. `useDashboardStore.createDashboard` surfaces the 403 envelope as a localised toast via `@nextcloud/dialogs::showError`.
- REQ-ASET-004 (Allow Multiple Dashboards): `PermissionService::canHaveMultipleDashboards()` checks the setting. `DashboardApiController::checkCreatePermissions()` counts existing dashboards and returns 403 if multiples are disallowed.
- REQ-ASET-005 (Default Permission Level): `DashboardFactory::create()` in `lib/Service/DashboardFactory.php` hardcodes `PERMISSION_FULL` for user-created dashboards. The admin default setting is used as fallback by `PermissionService::getEffectivePermissionLevel()`.
- REQ-ASET-007 (Settings Persistence): Settings are stored in `oc_launchpad_admin_settings` table via `AdminSettingMapper`. Defaults are returned in-code when DB rows are absent.
- REQ-ASET-008 (Admin Settings UI): `LaunchPadAdmin` in `lib/Settings/LaunchPadAdmin.php` implements `ISettings`, `LaunchPadAdminSection` in `lib/Settings/LaunchPadAdminSection.php` implements `IIconSection`. Frontend in `src/components/admin/AdminSettings.vue` renders toggles, dropdowns, and save logic.
- REQ-ASET-012 (Group order setting): `AdminSettingsService::getGroupOrder()` and `setGroupOrder()` in `lib/Service/AdminSettingsService.php` read/write the `group_order` row via `AdminSettingMapper`. Defensive read returns `[]` on missing or corrupt JSON. `AdminSetting::KEY_GROUP_ORDER` is the canonical constant.
- REQ-ASET-013 (List groups for admin UI): `AdminSettingsController::listGroups()` in `lib/Controller/AdminSettingsController.php` exposes `GET /api/admin/groups` and assembles the disjoint `{active, inactive, allKnown}` split from `IGroupManager::search('')` and `getGroupOrder()`. Stale (unknown) IDs surface in `active` only.
- REQ-ASET-014 (Admin guard and payload validation): `AdminSettingsController::assertAdmin()` calls `IGroupManager::isAdmin($userSession->getUser()->getUID())` on both endpoints; `updateGroupOrder()` rejects missing or non-array `groups` keys with HTTP 400 and lets `setGroupOrder` enforce non-empty-string entries (HTTP 400 on `InvalidArgumentException`). Unknown group IDs are tolerated.

**Not yet implemented:**
- REQ-ASET-002 validation: No server-side validation for permission level values (any string accepted), grid column range (any integer accepted), or boolean type coercion. Documented as NOTEs in the spec.
- REQ-ASET-003 fork endpoint: The `POST /api/dashboards/{uuid}/fork` route is owned by the separate `fork-current-as-personal` capability and does not yet exist; gating will be added there with the same `PersonalDashboardsDisabledException` envelope.
- REQ-ASET-008 localization: UI labels use `t('launchpad', ...)` translation function but actual Dutch translations are not verified in l10n files.

### Standards & References
- Nextcloud Admin Settings API: `OCP\Settings\ISettings`, `OCP\Settings\IIconSection`
- Nextcloud AppConfig pattern (though this app uses a custom DB table instead of `IAppConfig`)
- WCAG 2.1 AA for the admin settings form (keyboard navigation, labels, focus indicators)
- WAI-ARIA: Toggle states for checkbox switches communicated via `NcCheckboxRadioSwitch`
