# Delta: admin-settings

## MODIFIED Requirements

### Requirement: Retrieve Admin Settings (REQ-ASET-001)

Administrators MUST be able to retrieve all current admin settings via the API. The endpoint returns a flat JSON object with the twelve settings listed under Defined Settings, each under its API response key. `group_order` is not among them; it has its own endpoints (REQ-ASET-012).

#### Scenario: Get all settings with defaults
- GIVEN no admin settings have been explicitly configured (fresh installation)
- WHEN the admin sends GET /api/admin/settings
- THEN the system MUST return HTTP 200 with all twelve settings at their default values:
  ```json
  {
    "defaultPermissionLevel": "add_only",
    "allowUserDashboards": false,
    "allowMultipleDashboards": true,
    "defaultGridColumns": 12,
    "linkCreateFileExtensions": ["txt", "md", "docx", "xlsx", "csv", "odt"],
    "defaultSharePermissionLevel": "add_only",
    "forcedShareGroups": [],
    "legacyWidgetBridgeEnabled": false,
    "maxDashboardsPerUser": 0,
    "maxWidgetsPerDashboard": 0,
    "quicksearchFallbackTarget": "none",
    "startPageWithoutNavigation": false
  }
  ```
- NOTE: `allowUserDashboards` defaults to `false` (REQ-ASET-003) — admins MUST opt in to personal dashboard creation.

#### Scenario: Get settings after modification
- GIVEN the admin has set `allowUserDashboards` to `false`
- WHEN the admin sends GET /api/admin/settings
- THEN the response MUST carry `"allowUserDashboards": false`
- AND the other eleven keys MUST still be present with their current values

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
- THEN the response MUST always return exactly the twelve keys listed under Defined Settings: `defaultPermissionLevel`, `allowUserDashboards`, `allowMultipleDashboards`, `defaultGridColumns`, `linkCreateFileExtensions`, `defaultSharePermissionLevel`, `forcedShareGroups`, `legacyWidgetBridgeEnabled`, `maxDashboardsPerUser`, `maxWidgetsPerDashboard`, `quicksearchFallbackTarget`, `startPageWithoutNavigation`
- AND no additional keys MUST be present in the response
- NOTE: This scenario said "exactly four keys" long after the response had grown to twelve. The list above is taken from `AdminSettingsService::getSettings()`; a key added there MUST be added here and to the Defined Settings table.
- AND the response MUST be a flat JSON object (no nesting)

#### Scenario: The retired content storage key is not returned
- GIVEN an instance that still holds a `content_storage` row written by the retired setup-wizard storage step
- WHEN the admin sends GET /api/admin/settings
- THEN the response MUST NOT contain `launchpad.content_storage`
- AND the stored row MUST stay unread (decision 131)
