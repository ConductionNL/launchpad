---
status: done
---

# groupfolder-storage-backend Specification

## Purpose
Abstracts dashboard content storage behind a unified read/write/delete interface so operators can choose between the default database backend and an optional Nextcloud GroupFolder backend. The GroupFolder backend stores dashboards as human-readable JSON files in an auto-created, admin-restricted folder, fails closed without silent fallback, and ships a one-time migration command — all transparently to existing API clients.
## Requirements
### Requirement: REQ-GFSB-001 Storage Interface Abstraction

The system MUST provide a unified `DashboardContentStorage` interface that abstracts the physical storage mechanism from the dashboard service layer. The interface MUST define three core operations: read, write, and delete.

@e2e exclude these scenarios assert which PHP collaborator the service layer delegates to (`getStorage()->read/write/delete/exists`). Delegation is invisible from a browser by construction — REQ-GFSB-010 requires the HTTP response to be byte-identical whichever backend served it, so a UI assertion cannot distinguish a passing case from a failing one. Verified unit coverage: `tests/Unit/Service/DashboardContentStorage/DbContentStorageTest.php` (`testReadReturnsDecodedContent`, `testWriteCallsMapperUpdate`, `testDeleteCallsUpdate`, `testExistsReturnsFalseWhenNotFound`, `testExistsReturnsTrueWhenContentNotEmpty`).

#### Scenario: Read dashboard content from active backend

- GIVEN a dashboard with UUID "dash-001" exists in the active storage backend
- WHEN `DashboardService::getDashboard("dash-001")` is called
- THEN the system MUST delegate to `getStorage()->read("dash-001")` to fetch the content
- AND the content (widgets array, layout metadata) MUST be returned as a parsed PHP array
- AND the HTTP response MUST include the full content in the `content` field

#### Scenario: Write dashboard content to active backend

- GIVEN a user creates or updates a dashboard with new widget placements
- WHEN `DashboardService::createDashboard()` or `updateDashboard()` is called with a content array
- THEN the system MUST delegate to `getStorage()->write(uuid, $content)` to persist the content
- AND the operation MUST be idempotent (rewriting identical content produces no error)
- AND the HTTP response MUST return HTTP 201 (create) or HTTP 200 (update)

#### Scenario: Delete dashboard content from active backend

- GIVEN a user deletes a dashboard
- WHEN `DashboardService::deleteDashboard(uuid)` is called
- THEN the system MUST delegate to `getStorage()->delete(uuid)` to remove the content
- AND the dashboard entity MUST also be deleted from the database via the mapper
- AND the HTTP response MUST return HTTP 204

#### Scenario: Check existence without raising exception

- GIVEN a dashboard UUID "unknown" does not exist in any backend
- WHEN `getStorage()->exists("unknown")` is called
- THEN the system MUST return `false` without raising an exception
- AND the caller can use this for optional-read patterns

### Requirement: REQ-GFSB-002 Database Backend Default Behavior

The database backend MUST implement the storage interface using the existing `oc_launchpad_dashboards` table, preserving all current behavior for operators who do not opt-in to GroupFolder storage.

#### Scenario: Database backend is the default

- GIVEN a fresh LaunchPad installation
- AND no `launchpad.content_storage` admin setting has been explicitly configured
- WHEN any dashboard operation occurs
- THEN the system MUST use `DbContentStorage` automatically
- AND dashboard content MUST be read from and written to the `content` field in `oc_launchpad_dashboards`
- AND no GroupFolder dependency is invoked

@e2e exclude "which class the factory returned, and which one it did not touch" has no browser surface: REQ-GFSB-010 requires the HTTP response to be identical either way, so a UI assertion here would be one that cannot fail — the same green whether the default held or not. The Setup Wizard's storage step is not a usable surface for it either, and not merely because it is unexciting: `GET /api/admin/setup-wizard/state` returns only `{complete, currentRecommendedStep, stepStatuses}`, so the modal's `data.contentStorage` is always `undefined` and the radio falls back to "database" on every instance regardless of the persisted setting. Verified unit coverage: `tests/Unit/Service/DashboardContentStorageFactoryTest.php::testGetStorageDefaultsToDbForUnknownValue` and `::testGetStorageReturnsDbWhenSettingIsDatabase`.

#### Scenario: Database backend reads existing dashboards

- GIVEN the database contains a dashboard with UUID "dash-legacy" and content `{"widgets": [...]}`
- WHEN `DbContentStorage::read("dash-legacy")` is called
- THEN the system MUST fetch the dashboard entity via `DashboardMapper::findByUuid()`
- AND the `content` field MUST be JSON-decoded and returned as a PHP array
- AND the operation MUST not create a GroupFolder or depend on the `groupfolders` app

@e2e exclude names the private collaborator (`DashboardMapper::findByUuid()`) and asserts a NEGATIVE — that no GroupFolder was created — neither of which a browser can see; a rendered dashboard looks the same whichever mapper call produced it. Verified unit coverage: `tests/Unit/Service/DashboardContentStorage/DbContentStorageTest.php::testReadReturnsDecodedContent` and `::testReadReturnsEmptyArrayWhenContentIsNull`.

#### Scenario: Database backend handles missing dashboard gracefully

- GIVEN a dashboard UUID "dash-missing" does not exist in the database
- WHEN `DbContentStorage::read("dash-missing")` is called
- THEN the system MUST throw `DashboardNotFoundException` (extending `DashboardContentStorageException`)
- AND the exception message MUST be descriptive and logged for debugging

@e2e exclude asserts a PHP exception CLASS and its inheritance, plus a log line — the browser sees an HTTP error page, not a class name, so a UI assertion could not tell `DashboardNotFoundException` from any other failure. Verified unit coverage: `tests/Unit/Service/DashboardContentStorage/DbContentStorageTest.php::testReadThrowsDashboardNotFoundExceptionWhenUuidMissing` and `tests/Unit/Service/DashboardContentStorage/DashboardContentStorageExceptionTest.php::testDashboardNotFoundExceptionExtendsDashboardContentStorageException`.

#### Scenario: Database backend writes and overwrites

- GIVEN a dashboard entity exists in the database
- WHEN `DbContentStorage::write(uuid, {"widgets": [...], "new": true})` is called
- THEN the system MUST update the dashboard entity's `content` field
- AND call `DashboardMapper::update()` to persist the change
- AND rewriting with identical content MUST not raise an error

@e2e exclude asserts which mapper method is called, which is invisible from a browser. Verified unit coverage: `tests/Unit/Service/DashboardContentStorage/DbContentStorageTest.php::testWriteCallsMapperUpdate`. The user-visible half of this — that a dashboard content write persists and survives a reload — is already proven end-to-end by `tests/e2e/widget-collision-placement.spec.ts` ("REQ-GRID-005: widget positions survive a page reload"), which CI runs.

### Requirement: REQ-GFSB-003 GroupFolder Backend Auto-Creation and ACL

The GroupFolder backend MUST automatically create a managed GroupFolder named "LaunchPad" on first use, with restrictive ACL rules ensuring only administrators have file-level access.

@e2e exclude the `groupfolders` Nextcloud app is not installed on the Playwright CI fixture — `.github/workflows/code-quality.yml` provisions only `ConductionNL/openregister` through `additional-apps`, and neither seed script installs it — so no browser reaching that instance can exercise the GroupFolder backend at all. The server refuses the selection outright: `AdminController::setWizardStorage()` returns HTTP 400 "GroupFolder app is not installed." whenever `SetupWizardService::hasGroupfolderApp()` is false. Verified unit coverage of the auto-create path: `tests/Unit/Service/DashboardContentStorage/GroupFolderContentStorageTest.php::testWriteCreatesGroupFolderWhenMissing`.

#### Scenario: GroupFolder is auto-created on first write

- GIVEN the `launchpad.content_storage` setting is `'groupfolder'`
- AND no "LaunchPad" GroupFolder yet exists
- WHEN `GroupFolderContentStorage::write(uuid, {...})` is called for the first time
- THEN the system MUST create a GroupFolder named "LaunchPad" via the `groupfolders` app API
- AND the GroupFolder MUST be created with ACL rules:
  - Administrators: read, write, delete
  - All other users: no default access (dashboard permissions mediate visibility)
- AND the write operation MUST proceed to store the content in the newly created folder

#### Scenario: Subsequent writes reuse existing GroupFolder

- GIVEN the "LaunchPad" GroupFolder already exists with correct ACL rules
- WHEN `GroupFolderContentStorage::write(uuid, {...})` is called
- THEN the system MUST NOT attempt to create the GroupFolder again
- AND the write MUST proceed directly to persisting the content

#### Scenario: GroupFolder creation is idempotent

- GIVEN `ensureLaunchPadGroupFolder()` is called twice in rapid succession
- THEN the system MUST return the same GroupFolder ID both times
- AND no duplicate GroupFolder MUST be created
- AND the operation MUST be thread-safe (atomic read-or-create pattern)

#### Scenario: ACL isolation from user dashboard permissions

- GIVEN a GroupFolder with administrator-only file access
- AND a non-administrator user "alice" has `view_full` permission on a dashboard (from the `dashboards` capability)
- WHEN alice's browser calls `GET /api/dashboard/{uuid}` to read the dashboard content
- THEN the system MUST:
  - Check alice's dashboard permission via `PermissionService`
  - If authorized, fetch the content via `GroupFolderContentStorage`
  - Never expose filesystem-level GroupFolder ACL to the user
- AND alice MUST NOT be able to directly access the GroupFolder via the Files API (file-level ACL is restrictive)

### Requirement: REQ-GFSB-004 GroupFolder File Structure and Multi-Locale Support

The GroupFolder backend MUST organize dashboard content as JSON files in a structured directory hierarchy that supports optional locale-based separation, enabling future multi-language dashboard configurations.

@e2e exclude these scenarios assert the private path-resolution and serialisation details of `GroupFolderContentStorage` (`resolvePath()` returning `LaunchPad/<uuid>.json` vs `LaunchPad/<locale>/<uuid>.json`, and the `json_encode` flags used on write). Nothing about a file's path or its pretty-printing reaches the browser — REQ-GFSB-010 requires the HTTP response to be identical either way — and the `groupfolders` app is not installed on the Playwright CI fixture, so the backend cannot be selected there in the first place. NOTE: the locale branch of `resolvePath()` currently has no unit test either; that is a real gap in `tests/Unit/Service/DashboardContentStorage/GroupFolderContentStorageTest.php`, and it is a unit-test gap, not an e2e one.

#### Scenario: File path resolution without locale

- GIVEN a dashboard UUID "abc-123-def-456" exists
- AND the system has no locale preference set (or locale is empty)
- WHEN `GroupFolderContentStorage` resolves the file path
- THEN the path MUST be `LaunchPad/abc-123-def-456.json`
- AND reading this file MUST return the full dashboard content object

#### Scenario: File path resolution with locale

- GIVEN a dashboard UUID "abc-123-def-456" and a locale preference "nl" (Dutch)
- WHEN `GroupFolderContentStorage` resolves the file path
- THEN the path MUST be `LaunchPad/nl/abc-123-def-456.json`
- AND the system MUST create the `LaunchPad/nl/` directory if it does not exist
- AND reading this file MUST return the locale-specific dashboard content

#### Scenario: Fallback when locale-specific file is missing

- GIVEN a dashboard UUID with a locale preference "nl" but no `LaunchPad/nl/{uuid}.json` file exists
- AND a fallback file `LaunchPad/{uuid}.json` exists
- WHEN `GroupFolderContentStorage::read()` is called
- THEN the system MUST:
  - Attempt to read `LaunchPad/nl/{uuid}.json` first
  - Fall back to `LaunchPad/{uuid}.json` if the locale-specific file does not exist
  - OR raise `DashboardNotFoundException` if neither exists (decision: implement fallback if operationally useful; document choice)
- NOTE: Fallback behavior is optional; current implementation can skip this and always require exact locale match

#### Scenario: Content is JSON-encoded on write

- GIVEN a dashboard content array `{"widgets": [...], "layout": {...}}`
- WHEN `GroupFolderContentStorage::write(uuid, $content)` is called
- THEN the system MUST JSON-encode the array via `json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)`
- AND write the JSON string to the file at the resolved path
- AND the file MUST be human-readable for manual inspection by administrators

### Requirement: REQ-GFSB-005 Dependency Check and Error Handling

The system MUST verify that the `groupfolders` Nextcloud app is installed before attempting GroupFolder operations, and MUST fail with a clear, actionable error if the app is not available.

@e2e exclude every scenario here is fault injection — the `groupfolders` app removed mid-operation, permission denied, disk full, a network error on remote storage. None of those states can be induced from a browser, and reaching them at all needs `launchpad.content_storage = 'groupfolder'`, which the Playwright CI fixture cannot select because the `groupfolders` app is not installed there. Verified unit coverage of the app-missing branch: `tests/Unit/Service/DashboardContentStorage/GroupFolderContentStorageTest.php` (`testReadThrowsGroupFoldersNotInstalledExceptionWhenAppMissing`, `testExistsReturnsFalseWhenAppMissing`) and `tests/Unit/Service/DashboardContentStorage/DashboardContentStorageExceptionTest.php` for the exception hierarchy and the exact message constant.

#### Scenario: GroupFolders app is required but missing

- GIVEN a LaunchPad instance with `launchpad.content_storage = 'groupfolder'`
- AND the `groupfolders` Nextcloud app is not installed
- WHEN a dashboard read/write/delete operation is attempted
- THEN the system MUST throw `GroupFoldersNotInstalledException` extending `DashboardContentStorageException`
- AND the exception message MUST be: "The 'groupfolders' Nextcloud app is required for GroupFolder storage backend but is not installed. Please install it via the app store or contact your administrator."
- AND the HTTP response MUST return HTTP 503 (Service Unavailable)

#### Scenario: GroupFolders app becomes unavailable mid-operation

- GIVEN `GroupFolderContentStorage` is in use and the app was previously installed
- AND the `groupfolders` app is suddenly disabled or uninstalled
- WHEN a read/write operation encounters a failure related to the missing app
- THEN the system MUST catch the underlying error
- AND throw `DashboardContentStorageException` with HTTP 503 status
- AND log the error at WARN level with full context
- AND NOT silently fall back to the database backend

#### Scenario: All I/O errors are wrapped in storage exception

- GIVEN any I/O operation on the GroupFolder fails (permission denied, disk full, network error for remote storage)
- WHEN `GroupFolderContentStorage` encounters the error
- THEN the system MUST catch the underlying exception
- AND throw or re-throw `DashboardContentStorageException` with a descriptive message including the operation (read/write/delete) and the dashboard UUID
- AND HTTP 503 MUST be returned to the client with error key `dashboard_content_storage_unavailable`

### Requirement: REQ-GFSB-006 Admin Setting for Backend Selection

The system MUST provide an admin-accessible setting that controls which storage backend is used for all dashboard operations. The setting MUST be persistent and validated on update.

#### Scenario: Retrieve current storage backend setting

- GIVEN an administrator navigates to LaunchPad admin settings
- WHEN the admin fetches `GET /api/admin/settings`
- THEN the response MUST include `{"launchpad.content_storage": "db"}` (or `"groupfolder"` if changed)
- AND the response MUST include all other admin settings unchanged

@e2e exclude the scenario asserts a JSON field in a `GET /api/admin/settings` body, which Newman owns here and already requests (`tests/integration/launchpad.postman_collection.json`, folder "Admin - Settings"). LaunchPad renders that value nowhere a browser could read it: the Setup Wizard's storage step is the only UI for the backend, and it does not in fact display the persisted value — `GET /api/admin/setup-wizard/state` returns only `{complete, currentRecommendedStep, stepStatuses}`, so `SetupWizardModal.loadState()` reads `data.contentStorage` as `undefined` and pins the radio to "database" on every instance. Asserting the radio would therefore assert the fallback, not the setting. That mismatch is a product bug and is reported separately; it is not closed by an e2e annotation.

#### Scenario: Change storage backend setting

- GIVEN the current setting is `launchpad.content_storage = "db"`
- WHEN the admin sends `PUT /api/admin/settings` with body `{"launchpad.content_storage": "groupfolder"}`
- THEN the system MUST validate the value (enum: `db` or `groupfolder`)
- AND persist the new setting in the admin settings table
- AND return HTTP 200 with the updated setting
- AND all subsequent dashboard operations MUST use the new backend

@e2e exclude the only value a change could move to is `groupfolder`, and the Playwright CI fixture cannot accept it: the `groupfolders` app is not installed there, so `AdminController::setWizardStorage()` answers HTTP 400 "GroupFolder app is not installed." for the only value a change could move to. The `PUT /api/admin/settings` half is an HTTP contract this repository routes to Newman, whose collection already exercises `PUT /api/admin/settings` (`tests/integration/launchpad.postman_collection.json`, folder "Admin - Settings").

#### Scenario: Invalid storage backend value is rejected

- GIVEN the admin sends a PUT request with `{"launchpad.content_storage": "redis"}`
- WHEN the endpoint processes the request
- THEN the system MUST reject the value as invalid
- AND return HTTP 400 with error message `"Invalid value for launchpad.content_storage. Must be 'db' or 'groupfolder'."`
- AND the setting MUST NOT be changed

@e2e exclude a browser cannot send `"redis"`: the only UI for this setting is the Setup Wizard's storage step, which offers exactly two radio inputs (`storage-database`, `storage-groupfolder`) and can therefore never produce an out-of-enum value. Rejecting one requires a hand-built HTTP request, which is Newman's job here. The validation itself lives at `lib/Service/AdminSettingsService.php` and emits that exact message string.

#### Scenario: Non-admin cannot change backend setting

- GIVEN a regular user "alice" (non-administrator)
- WHEN she sends `PUT /api/admin/settings` with body `{"launchpad.content_storage": "..."}` (any value)
- THEN the system MUST return HTTP 403 (Forbidden)
- AND the setting MUST NOT be changed

@e2e exclude a non-admin never reaches this control through a browser — the Setup Wizard lives inside `/settings/admin/launchpad`, which Nextcloud's own admin-settings framework refuses to render for a non-admin, so the 403 under test is the app's and the browser would only ever demonstrate the framework's. Proving the app's own guard needs a hand-built request as a non-admin, which is Newman's job here; its collection already asserts non-admin rejection on `/api/role-feature-permissions`.

### Requirement: REQ-GFSB-007 Fail-Closed Guarantee and No Silent Fallback

The system MUST never silently fall back from a configured backend to a different one. If the configured backend is unavailable, the operation MUST fail with a clear error and not attempt to use an alternative backend.

@e2e exclude fail-closed is an assertion about what does NOT happen (no second backend is tried) under a fault that has to be injected — a deleted GroupFolder, a stripped ACL, a lost database connection. A browser cannot induce any of those, and the absence of a fallback attempt leaves no trace in the rendered page. The third scenario is explicitly about the tests themselves rather than about product behaviour. Verified backend-selection coverage: `tests/Unit/Service/DashboardContentStorageFactoryTest.php` (`testGetStorageReturnsDbWhenSettingIsDatabase`, `testGetStorageReturnsGroupFolderWhenSettingIsGroupfolder`, `testGetStorageDefaultsToDbForUnknownValue`) — the factory returns exactly one backend and has no fallback branch to take.

#### Scenario: GroupFolder backend is unavailable, no fallback to database

- GIVEN `launchpad.content_storage = 'groupfolder'` is configured
- AND the GroupFolder is deleted or becomes unreachable (ACL stripped, Nextcloud storage issue)
- WHEN a user attempts to read or write a dashboard
- THEN the system MUST NOT attempt to fall back to database storage
- AND MUST return HTTP 503 with error key `dashboard_content_storage_unavailable`
- AND the error message MUST indicate the configured backend is unavailable

#### Scenario: Database backend failure does not trigger GroupFolder attempt

- GIVEN `launchpad.content_storage = 'db'` is configured
- AND a database error occurs (e.g., connection lost, table locked)
- WHEN a dashboard operation fails
- THEN the system MUST NOT attempt to use GroupFolder as a fallback
- AND MUST return HTTP 503 or appropriate database error
- AND the failure MUST be logged with full context

#### Scenario: Fail-closed behavior is tested and enforced

- GIVEN the tests for storage layer exception handling
- WHEN a configured backend throws an exception
- THEN the system MUST NOT mask it or attempt fallback
- AND the exception MUST propagate to the controller with HTTP 503

### Requirement: REQ-GFSB-008 One-Time Migration Command

The system MUST provide a console command that migrates all existing dashboards from the database backend to the GroupFolder backend in a single operation, with idempotent semantics allowing safe re-execution.

@e2e exclude the subject is an `occ` console command (`launchpad:storage:migrate-to-groupfolder`, `lib/Command/MigrateStorageToGroupFolder.php`) — its console output, its progress log and its process exit code have no browser surface, and its target backend needs the `groupfolders` app the Playwright CI fixture does not install. Verified unit coverage: `tests/Unit/Command/MigrateStorageToGroupFolderTest.php` (`testHandleMigratesAllDashboards`, `testHandleSkipsAlreadyMigratedDashboards`, `testHandleExitsWithErrorOnPartialFailure`). The retention scenario states an either/or with no MUST and asserts no single behaviour.

#### Scenario: Migration command copies all dashboards

- GIVEN a LaunchPad instance with 10 dashboards stored in the database
- WHEN an administrator runs `launchpad:storage:migrate-to-groupfolder` via the console
- THEN the system MUST:
  - Query all dashboard records via `DashboardMapper::findAll()`
  - For each dashboard, read its content from the database
  - Write the content to the GroupFolder backend via `GroupFolderContentStorage`
  - Log progress (e.g., "Migrated 5/10 dashboards")
- AND the command MUST exit with code 0 on success
- AND the console output MUST confirm the total count migrated

#### Scenario: Migration skips already-migrated dashboards

- GIVEN some dashboards have already been migrated to GroupFolder
- WHEN the migration command is run again
- THEN the system MUST detect that the content already exists in GroupFolder (via `exists()` check)
- AND skip re-copying it without raising an error
- AND log a message like "Dashboard {uuid} already migrated, skipping"
- AND the command MUST remain idempotent and exit with code 0

#### Scenario: Migration handles errors gracefully

- GIVEN a migration is in progress and encounters a write error on dashboard 7 of 10
- WHEN the error occurs (e.g., GroupFolder permission issue)
- THEN the system MUST:
  - Log the error with the dashboard UUID and details
  - Continue processing remaining dashboards (not fail-fast)
  - Output a summary at the end: "Migrated 6/10, 1 error"
  - Return exit code 1 to signal partial failure
- AND the operator can re-run the command to retry failed dashboards

#### Scenario: Retention policy after migration (optional decision point)

- GIVEN migration has completed successfully
- THEN either:
  - (A) The system deletes the content from the database (clean cutover), OR
  - (B) The system leaves the database content in place for rollback safety
- NOTE: Decision is implementation-specific; document the choice in the command help text and release notes. Recommended: option (A) with a dry-run flag to preview changes.

### Requirement: REQ-GFSB-009 Transparent Backend Switching During Transition

Existing dashboards MUST remain readable from their original backend during a transition period, allowing operators to gradually migrate content without service disruption.

@e2e exclude both scenarios require `launchpad.content_storage = 'groupfolder'` to be the active setting, which the Playwright CI fixture cannot reach: the `groupfolders` app is not installed there, so `AdminController::setWizardStorage()` answers HTTP 400 "GroupFolder app is not installed." to any attempt to select it. The first scenario is additionally undecided in the spec itself — it carries a NOTE deferring whether dual-backend reads are supported at all, so there is no settled behaviour to assert.

#### Scenario: Database-backed dashboard is readable during GroupFolder transition

- GIVEN `launchpad.content_storage = 'db'` and dashboard "D1" with content in the database
- WHEN the operator switches the setting to `launchpad.content_storage = 'groupfolder'` but does not run the migration command
- AND a user requests `GET /api/dashboard/D1`
- THEN the system MUST still read from the database (the configured backend is now GroupFolder, but D1 has not been migrated yet)
- NOTE: This scenario requires the API to attempt both backends in order (try configured, fall back to alternate for read-only). Decide during implementation whether to support this or require migration before switching.

#### Scenario: Create new dashboards with the currently configured backend

- GIVEN `launchpad.content_storage = 'groupfolder'` is now configured
- AND a user creates a new dashboard "D2" after switching
- WHEN the new dashboard is created
- THEN the system MUST persist its content to the GroupFolder backend, not the database
- AND existing dashboards remain in their original backend until explicitly migrated

### Requirement: REQ-GFSB-010 No API Changes Required

The storage backend MUST be transparent to all existing API clients. Dashboard read/write/delete endpoints MUST not change their contracts, error codes (except for the new HTTP 503 case), or response formats.

@e2e exclude these are HTTP response-shape assertions about `/api/dashboard*` — JSON field names and status codes — which this repository routes to Newman rather than Playwright by standing convention (`playwright.config.ts` excludes `**/api-direct/**` for exactly this reason, and `tests/integration/launchpad.postman_collection.json` is the contract suite that runs in the same workflow). A rendered dashboard cannot show that a response is byte-identical to what a different backend would have produced. The 503 scenario also needs the configured backend to be unavailable, which is fault injection on a backend CI does not provision. NOTE: no test currently asserts the `dashboard_content_storage_unavailable` error key emitted at `lib/Controller/DashboardApiController.php`; that is an uncovered branch and it belongs to the unit or Newman suite, not to a browser.

#### Scenario: API response format is unchanged

- GIVEN a client calls `GET /api/dashboard/{uuid}`
- AND the dashboard content is stored in either backend
- WHEN the endpoint returns a response
- THEN the response format MUST be identical: same JSON structure, same field names, same HTTP status codes
- AND the client MUST not be able to determine which backend was used

#### Scenario: Create endpoint response is unchanged

- GIVEN a client calls `POST /api/dashboard` with widget content
- WHEN the system uses the configured backend (db or groupfolder)
- THEN the response MUST return HTTP 201 and the full dashboard object
- AND the response format MUST be identical to the current implementation

#### Scenario: New error response for unavailable storage backend

- GIVEN the configured backend is unavailable
- WHEN any dashboard operation is attempted
- THEN the system MUST return HTTP 503 (Service Unavailable)
- AND the response body MUST include `{"error": "dashboard_content_storage_unavailable", "message": "..."}`
- AND this is the ONLY new error code; all other HTTP codes remain as they are today

