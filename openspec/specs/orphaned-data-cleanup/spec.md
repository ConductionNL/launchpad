---
status: done
---

# Orphaned Data Cleanup Specification

## Purpose

Provide administrators with a comprehensive, safe, and auditable mechanism to scan for and remove orphaned LaunchPad data: expired locks and tokens, widget assets from deleted dashboards, metadata-value rows with missing field definitions, placements with no dashboard, tokens for deleted users, role assignments for deleted users/groups, and translations for deleted dashboards. The capability MUST support dry-run (safe preview), per-category selectivity (scan vs. auto-purge), background automation (daily safe-categories job), and audit trails (activity events). A registry pattern enables adding new cleanup categories without editing central code.

## Data Model

Orphaned data exists in multiple tables and file locations. This spec defines what constitutes orphaned and the cleanup strategy for each:

- **expired_locks**: rows in `oc_launchpad_dashboard_locks` where `updated_at` is older than `now - LOCK_TIMEOUT` (15 minutes).
- **expired_share_tokens**: rows in `oc_launchpad_dashboard_shares` whose `dashboard_id` no longer points at any row in `oc_launchpad_dashboards`. The LaunchPad share schema has no expiry/revocation columns at the time of writing; the dangling-FK orphan is the only orphan kind shares can currently produce. Future schema additions (revoked_at, expires_at) MAY extend this category without breaking the contract.
- **orphaned_widget_assets**: files in `LaunchPad/Imports/*` and `LaunchPad/icons/*` (Nextcloud file storage) not referenced by any current dashboard or widget config. Optional; may be skipped on installs where the file backend is not provisioned.
- **orphaned_metadata_values**: rows in `oc_launchpad_metadata_values` where `fieldId` does NOT exist in `oc_launchpad_metadata_fields`. Optional; available only on installs with the dashboard-metadata-fields feature.
- **orphaned_widget_placements**: rows in `oc_launchpad_widget_placements` whose `dashboard_id` does NOT exist in `oc_launchpad_dashboards`.
- **orphaned_conditional_rules**: rows in `oc_launchpad_conditional_rules` whose `widget_placement_id` does NOT exist in `oc_launchpad_widget_placements`.
- **orphaned_feed_tokens**: rows in `oc_launchpad_feed_tokens` where `userId` no longer exists in `oc_users`. Optional; available only on installs with the dashboard-rss-feeds feature.
- **orphaned_role_assignments**: rows in `oc_launchpad_role_assignments` where `userId` (or `groupId`) no longer exists in `oc_users` (or `oc_groups`). Optional; available only on installs with the admin-roles feature.
- **dangling_dashboard_translations**: rows in `oc_launchpad_dash_translations` where `dashboardUuid` no longer exists in `oc_launchpad_dashboards`. Optional; available only on installs with the dashboard-language-content feature.

Categories that map to optional features MUST report `isAvailable() === false` on installs where the feature is not present, so the orchestrator can skip them cleanly (REQ-CLN-001 "Scan handles missing tables gracefully") without erroring on a missing-table SQL fault. The shipped four categories (expired_locks, expired_share_tokens, orphaned_widget_placements, orphaned_conditional_rules) are always available because their tables are part of the core schema.

Cleanup is grouped into three safety tiers:

1. **Tier-A (Auto-safe)**: expired_locks, expired_share_tokens — always safe to auto-purge (no user-visible impact)
2. **Tier-B (Manual-safe)**: orphaned_widget_assets, orphaned_metadata_values, orphaned_widget_placements, orphaned_conditional_rules, orphaned_feed_tokens, dangling_dashboard_translations — safe to purge manually but not auto (require data validation first)
3. **Tier-C (Inspect-first)**: orphaned_role_assignments — purge only after inspection (role-based permissions at stake)

Default auto-purge list: Tier-A (expired_locks, expired_share_tokens). Returned by `CategoryRegistryService::getAutoSafeCategoryNames()` and used as the default for both the daily background job and the admin settings UI.

## Requirements


### Requirement: REQ-CLN-001 Scan CLI Command

Administrators MUST be able to run a CLI command that reports all orphaned items by category WITHOUT deleting.

#### Scenario: Scan finds orphaned items

- GIVEN a LaunchPad installation with: 3 expired locks, 2 orphaned share tokens, 1 orphaned widget placement
- WHEN an administrator runs `php occ launchpad:cleanup:scan`
- THEN the system MUST:
  - Query every registered category whose `isAvailable()` returns `true`
  - Display a table with one row per category, showing category name and count
  - Return exit code 1 (nonzero) to signal that orphans exist
- AND the output MUST include rows for every shipped category with their counts and a TOTAL row

@e2e exclude needs `occ launchpad:cleanup:scan`, which the e2e suite cannot run (no shell into the instance), and three expired locks plus orphaned shares and placements that no HTTP path can create: every delete cascades and a lock expires only 15 minutes after its heartbeat. Pinned by tests/Unit/Command/CleanupScanCommandTest.php::testScanOutput (table rows, TOTAL row, exit code 1); the per-category counting by tests/Unit/Service/OrphanedDataCleanupServiceTest.php::testScanAggregatesCountsAcrossRegistry.

#### Scenario: Scan finds no orphans

- GIVEN a clean LaunchPad installation with no orphaned data
- WHEN an administrator runs `php occ launchpad:cleanup:scan`
- THEN the system MUST display a table with all categories showing count 0
- AND return exit code 0 (success, no orphans)

@e2e exclude needs `occ launchpad:cleanup:scan`, which the e2e suite cannot run. Pinned by tests/Unit/Command/CleanupScanCommandTest.php::testScanOutput (all-zero table ending in `TOTAL 0`, exit code 0).

#### Scenario: Scan handles missing tables gracefully

- GIVEN a LaunchPad installation where a feature (e.g., dashboard-rss-feeds) is not yet enabled
- WHEN `php occ launchpad:cleanup:scan` is run
- THEN the system MUST skip categories whose `CleanupCategoryInterface::isAvailable()` returns `false`
- AND list the skipped categories under a "Skipped categories" comment line
- AND return 0 if no other orphans exist

@e2e exclude needs `occ` and a category whose `isAvailable()` is false; all four shipped categories are always available (CategoryRegistryService), so no instance can show a skipped one. Pinned by tests/Unit/Service/OrphanedDataCleanupServiceTest.php::testScanSkipsUnavailableCategories and the `Skipped categories` line in tests/Unit/Command/CleanupScanCommandTest.php::testScanOutput.

### Requirement: REQ-CLN-002 Purge CLI Command

Administrators MUST be able to run a CLI command to DELETE orphaned items, with confirmation and per-category selectivity.

#### Scenario: Purge all categories with confirmation

- GIVEN a LaunchPad installation with orphaned data across multiple categories
- WHEN an administrator runs `php occ launchpad:cleanup:purge` (no --yes flag)
- THEN the system MUST prompt interactively listing every effective category
- AND if the user confirms (`y`), delete all orphaned items and display a summary `Purged N items across M categories in Xms.`
- AND if the user declines, display `Purge cancelled.` and exit 0 without deleting

@e2e exclude needs an interactive `occ launchpad:cleanup:purge` session, which the e2e suite cannot run. Pinned by the `confirmed` and `declined` cases of tests/Unit/Command/CleanupPurgeCommandTest.php::testRunsMatchTheRecording.

#### Scenario: Purge with --yes flag (non-interactive)

- GIVEN orphaned data exists
- WHEN an administrator runs `php occ launchpad:cleanup:purge --yes`
- THEN the system MUST delete all orphaned items immediately (no prompt) and exit 0

@e2e exclude needs `occ launchpad:cleanup:purge --yes`, which the e2e suite cannot run. Pinned by the `with skipped` case of tests/Unit/Command/CleanupPurgeCommandTest.php::testRunsMatchTheRecording; the same deletion over HTTP is cited by purge-categories-via-api.

#### Scenario: Purge specific category

- GIVEN orphaned data across multiple categories
- WHEN an administrator runs `php occ launchpad:cleanup:purge --category=expired_locks --yes`
- THEN the system MUST delete only orphans in `expired_locks`
- AND display `Purged N items from category 'expired_locks' in Xms.`

@e2e exclude needs `occ launchpad:cleanup:purge --category=...`, which the e2e suite cannot run. Pinned by the `one category` case of tests/Unit/Command/CleanupPurgeCommandTest.php::testRunsMatchTheRecording.

#### Scenario: Purge non-existent category

- GIVEN a valid cleanup setup
- WHEN an administrator runs `php occ launchpad:cleanup:purge --category=invalid_category --yes`
- THEN the system MUST display an error naming the invalid category, list valid categories, and return exit code 1

@e2e exclude needs `occ`, which the e2e suite cannot run. Pinned by the `unknown category` case of tests/Unit/Command/CleanupPurgeCommandTest.php::testRunsMatchTheRecording; the HTTP equivalent is cited by unknown-category-yields-http-400.

### Requirement: REQ-CLN-003 Dry-Run Safety Mode

The purge CLI command MUST support a `--dry-run` flag that reports what WOULD be deleted without actually deleting. The scan command is always non-destructive.

#### Scenario: Purge with dry-run

- GIVEN orphaned data exists
- WHEN an administrator runs `php occ launchpad:cleanup:purge --dry-run --yes`
- THEN the system MUST execute the deletion queries inside a transaction wrapper that is rolled back before commit
- AND display `DRY-RUN: Would purge N items across M categories in Xms.`
- AND leave all data untouched

@e2e exclude needs `occ launchpad:cleanup:purge --dry-run`, which the e2e suite cannot run. The rollback is pinned by tests/Unit/Service/OrphanedDataCleanupServiceTest.php::testDryRunRollsBackAndDoesNotEmitEvent and the output by the `all categories dry run` case of tests/Unit/Command/CleanupPurgeCommandTest.php::testRunsMatchTheRecording; the HTTP dry run is cited by purge-with-dryrun-true.

#### Scenario: Dry-run with category filter

- GIVEN orphans in multiple categories
- WHEN an administrator runs `php occ launchpad:cleanup:purge --category=expired_locks --dry-run --yes`
- THEN the system MUST report `DRY-RUN: Would purge N items from category 'expired_locks' in Xms.`
- AND delete nothing

@e2e exclude needs `occ`, which the e2e suite cannot run. Pinned by tests/Unit/Command/CleanupPurgeCommandTest.php::testRunsMatchTheRecording (category and dry-run handling) and tests/Unit/Service/OrphanedDataCleanupServiceTest.php::testDryRunRollsBackAndDoesNotEmitEvent.

### Requirement: REQ-CLN-004 Scan API Endpoint

Administrators MUST be able to call an HTTP API endpoint to retrieve scan results as JSON, with caching.

#### Scenario: GET /api/admin/cleanup/scan returns current orphan counts

- GIVEN a LaunchPad installation with orphans
- WHEN an administrator (admin role) sends `GET /api/admin/cleanup/scan`
- THEN the system MUST return HTTP 200 with a JSON body containing `byCategory`, `totalRows`, `durationMs`, `dryRun`, `scannedAt`, `skipped`, `cached`, and `cachedAt` fields
- AND, when no cache entry is present, perform a fresh scan and return `cached: false`

#### Scenario: Subsequent scan API call returns cached results

- GIVEN the previous scan completed within `CACHE_TTL_SECONDS` (300s)
- WHEN an administrator sends `GET /api/admin/cleanup/scan` again
- THEN the system MUST return the same counts with `cached: true` and `cachedAt` set to the original `scannedAt`

#### Scenario: Cache is invalidated after purge

- GIVEN a cached scan
- WHEN an administrator calls `POST /api/admin/cleanup/purge` with `dryRun=false`
- THEN the next `GET /api/admin/cleanup/scan` MUST perform a fresh scan and return `cached: false`

#### Scenario: Non-admin user is denied access

- GIVEN an unauthenticated or non-admin user
- WHEN they send `GET /api/admin/cleanup/scan`
- THEN the system MUST return HTTP 403 Forbidden

### Requirement: REQ-CLN-005 Purge API Endpoint

Administrators MUST be able to POST to an API endpoint to trigger purge with per-category selectivity and dry-run support.

#### Scenario: Purge categories via API

- GIVEN orphaned data exists
- WHEN an administrator sends `POST /api/admin/cleanup/purge` with body `{"categories": ["expired_locks", "expired_share_tokens"], "dryRun": false}`
- THEN the system MUST delete the orphans in those categories and return HTTP 200 with `purgedByCategory`, `totalRows`, `durationMs`, `dryRun`, and `skipped` fields

#### Scenario: Purge with dryRun=true

- GIVEN orphaned data exists
- WHEN an administrator sends `POST /api/admin/cleanup/purge` with `dryRun: true`
- THEN the system MUST return the same envelope shape with `dryRun: true` and leave all data untouched

#### Scenario: Empty categories array purges all categories

- GIVEN a body `{"categories": [], "dryRun": false}`
- WHEN the administrator sends `POST /api/admin/cleanup/purge`
- THEN the system MUST treat the empty list as "all registered categories" and return per-category counts for the full set

#### Scenario: Unknown category yields HTTP 400

- GIVEN a body referencing an unregistered category name
- WHEN the administrator sends `POST /api/admin/cleanup/purge`
- THEN the system MUST return HTTP 400 with an `error` message and a `validCategories` list

#### Scenario: Non-admin user is denied access

- GIVEN a non-admin user
- WHEN they send `POST /api/admin/cleanup/purge`
- THEN the system MUST return HTTP 403 Forbidden

### Requirement: REQ-CLN-006 Per-Category Breakdown

The scan and purge results MUST include a per-category breakdown so administrators understand exactly what is being removed.

#### Scenario: Scan output shows detailed breakdown

- GIVEN orphaned data in multiple categories
- WHEN an administrator calls `php occ launchpad:cleanup:scan`
- THEN the output table MUST display one row per category with name and count, in registration order
- AND a TOTAL row MUST follow with the sum of all categories

@e2e exclude asserts the `occ launchpad:cleanup:scan` table, which the e2e suite cannot run. Pinned by tests/Unit/Command/CleanupScanCommandTest.php::testScanOutput (rows in registration order, then TOTAL); registration order over HTTP is asserted by the scan test in tests/e2e/orphaned-data-cleanup.spec.ts.

#### Scenario: Purge API returns per-category counts

- GIVEN a `POST /api/admin/cleanup/purge` request
- WHEN the purge completes
- THEN the response MUST include a `purgedByCategory` object mapping category name to count, plus `totalRows`

### Requirement: REQ-CLN-007 Background Job for Auto-Purge

A scheduled daily job MUST run and automatically purge a safe-to-auto-purge subset of categories.

#### Scenario: Daily cleanup job runs and purges Tier-A categories

- GIVEN the `launchpad` app config `cleanup_auto_purge_categories` is empty (factory default)
- AND the `OrphanedDataCleanupJob` is registered with a 24-hour interval
- WHEN the scheduled time arrives
- THEN the job MUST resolve the auto-purge list via `CategoryRegistryService::getAutoSafeCategoryNames()` (Tier-A)
- AND call `OrphanedDataCleanupService::purge` with that list and `source='job'`
- AND the orchestrator MUST emit one activity event when the total purge count is non-zero

@e2e exclude needs the daily OrphanedDataCleanupJob to run, and a background-job tick cannot be triggered for a specific job over HTTP. Pinned by tests/Unit/BackgroundJob/OrphanedDataCleanupJobTest.php::testCategoriesThePurgeRuns (empty config purges the Tier-A list) and tests/Unit/BackgroundJob/OrphanedDataCleanupJobScheduleTest.php::testRunsDailyAndIsNotTimeSensitive / testPurgeIsARealJobRunWithoutAUser (24h interval, `source='job'`).

#### Scenario: Admin overrides auto-purge category list

- GIVEN the admin sets `cleanup_auto_purge_categories` to a JSON-encoded list (e.g. `["expired_locks"]`)
- WHEN the daily job runs
- THEN the job MUST purge only the categories in the configured list
- AND skip unknown / non-string entries silently

@e2e exclude needs the daily background job to run, which cannot be triggered over HTTP. Pinned by tests/Unit/BackgroundJob/OrphanedDataCleanupJobTest.php::testCategoriesThePurgeRuns (the `["rules","",5,"nope","shares"]` case drops unknown and non-string entries).

#### Scenario: Auto-purge job is skipped if no categories enabled

- GIVEN `cleanup_auto_purge_categories` is set to an empty JSON array (`"[]"`)
- WHEN the daily job is scheduled to run
- THEN the job MUST resolve the list to `[]`, log `launchpad.cleanup.job_skipped reason=no_categories_enabled`, and return without invoking the orchestrator

@e2e exclude needs the daily background job to run, which cannot be triggered over HTTP. Pinned by tests/Unit/BackgroundJob/OrphanedDataCleanupJobScheduleTest.php::testAnEmptyConfiguredListPurgesNothing and the `job_skipped reason=no_categories_enabled` case of tests/Unit/BackgroundJob/OrphanedDataCleanupJobTest.php::testCategoriesThePurgeRuns.

### Requirement: REQ-CLN-008 Safe-to-Auto-Purge List

The implementation MUST define which categories are safe enough to auto-purge without admin inspection. The shipped Tier-A list MUST contain exactly the two zero-impact categories: `expired_locks` and `expired_share_tokens`. Each category exposes its tier through `CleanupCategoryInterface::isSafeToPurgeAutomatically()`.

#### Scenario: Default auto-purge list is Tier-A only

- GIVEN a fresh LaunchPad installation with no `cleanup_auto_purge_categories` config value
- WHEN the daily job resolves its auto-purge list
- THEN the system MUST default to `["expired_locks", "expired_share_tokens"]` as returned by `CategoryRegistryService::getAutoSafeCategoryNames()`

@e2e exclude the list is resolved inside the daily background job, which cannot be triggered over HTTP. Pinned by tests/Unit/Service/Cleanup/CategoryRegistryServiceTest.php::testGetAutoSafeCategoryNamesReturnsTierAOnly and the empty-config case of tests/Unit/BackgroundJob/OrphanedDataCleanupJobTest.php::testCategoriesThePurgeRuns.

#### Scenario: Admin opts into more aggressive auto-purge

- GIVEN the admin sets `cleanup_auto_purge_categories` to include a Tier-B category
- WHEN the daily job runs
- THEN it MUST purge that category in addition to the configured Tier-A entries

@e2e exclude needs the daily background job to run, which cannot be triggered over HTTP. Pinned by tests/Unit/BackgroundJob/OrphanedDataCleanupJobTest.php::testCategoriesThePurgeRuns (a configured list including a Tier-B category is purged as given).

### Requirement: REQ-CLN-009 Audit Logging

Every successful real (non-dry-run) purge that affects at least one row MUST emit exactly one Nextcloud Activity event with structured metadata. Dry-run purges MUST NOT emit an Activity event. Both real and dry-run purges write a structured PSR-3 log line for cluster operators.

#### Scenario: Purge CLI command emits activity event

- GIVEN an administrator runs `php occ launchpad:cleanup:purge --category=expired_locks --yes` and deletes 3 locks
- WHEN the purge completes
- THEN the system MUST publish exactly one activity event with:
  - app: `launchpad`
  - type: `launchpad_cleanup_purge`
  - subject parameters: `totalRows`, `byCategory`, `durationMs`, `source: 'cli'`
- AND the event MUST appear in the Nextcloud Activity log visible to admins

@e2e exclude needs `occ` and a purge that deletes at least one row; no HTTP path can create an orphan (every delete cascades, locks expire only after 15 minutes). Pinned by tests/Unit/Service/OrphanedDataCleanupServiceTest.php::testRealPurgeEmitsOneActivityEvent (source `cli`).

#### Scenario: API purge emits activity event

- GIVEN an administrator calls `POST /api/admin/cleanup/purge` and deletes 5 items
- WHEN the purge succeeds
- THEN one activity event MUST be emitted with `source: 'api'` and the authenticated admin's UID under `affectedUser`/`author`

@e2e exclude an event is published only when the purge deletes at least one row, and no HTTP path can create an orphan to delete (every delete cascades, see dashboard-cascade-events; a lock expires only 15 minutes after its heartbeat). Pinned by tests/Unit/Service/OrphanedDataCleanupServiceTest.php::testRealPurgeEmitsOneActivityEvent and tests/Unit/Controller/AdminCleanupControllerCoverageTest.php::testNonStringAndEmptyCategoryEntriesAreDroppedSilently (the controller passes the acting admin's UID and `source: 'api'`). Gap: neither asserts the setAffectedUser/setAuthor arguments.

#### Scenario: Dry-run purge does NOT emit activity event

- GIVEN a purge with `dryRun=true` that would delete 5 items
- WHEN the purge completes without touching data
- THEN NO activity event MUST be emitted

@e2e exclude the absence of an event only means something when the dry run would delete rows, and no HTTP path can create an orphan. Pinned by tests/Unit/Service/OrphanedDataCleanupServiceTest.php::testDryRunRollsBackAndDoesNotEmitEvent (`publish` never called for a 5-row dry run).

#### Scenario: Background job purge emits activity event

- GIVEN the daily background job runs and auto-purges N > 0 items
- WHEN the purge completes
- THEN exactly one activity event MUST be emitted with `source: 'job'`

@e2e exclude needs the daily background job to run and delete rows; neither can be caused over HTTP. Pinned by tests/Unit/BackgroundJob/OrphanedDataCleanupJobScheduleTest.php::testPurgeIsARealJobRunWithoutAUser (`source='job'`) and tests/Unit/Service/OrphanedDataCleanupServiceTest.php::testRealPurgeEmitsOneActivityEvent.

### Requirement: REQ-CLN-010 Cache Invalidation

Scan results MUST be cached in the distributed cache (`ICacheFactory::createDistributed`) under the key `launchpad.cleanup.scan` for `CACHE_TTL_SECONDS` (300s); the cache MUST be invalidated on any successful (non-dry-run) purge. Partial scans (with a non-empty category-name filter) bypass the cache entirely so they cannot pollute the full-set entry.

#### Scenario: Scan results are cached for 5 minutes

- GIVEN a fresh full scan stored in the cache at T
- WHEN another scan is requested at T+30s (within TTL)
- THEN the system MUST return cached results with `cached: true` and `cachedAt` equal to the original `scannedAt`

#### Scenario: Cache expires after the TTL

- GIVEN a cached result from T
- WHEN a new scan is requested at T+CACHE_TTL_SECONDS+1
- THEN the system MUST discard the cache and perform a fresh scan returning `cached: false`

@e2e exclude needs the server clock to move past CACHE_TTL_SECONDS (300s): the suite cannot set the instance clock, and a five-minute sleep in the serial shared suite is not acceptable. Expiry itself is the ICache backend's job. Gap: no PHPUnit test asserts the `ttl: 300` argument OrphanedDataCleanupService::setCachedScanResult passes.

#### Scenario: Real purge invalidates cache

- GIVEN a cached scan result
- WHEN an administrator calls `POST /api/admin/cleanup/purge` with `dryRun=false` (success)
- THEN the cache MUST be cleared immediately (`ICache::remove(key: 'launchpad.cleanup.scan')`)

#### Scenario: Dry-run purge does NOT invalidate cache

- GIVEN a cached scan result
- WHEN an administrator calls `POST /api/admin/cleanup/purge` with `dryRun=true`
- THEN the cache MUST remain untouched

### Requirement: REQ-CLN-011 Registry Pattern Extensibility

Adding a new cleanup category MUST require only creating one new class implementing `CleanupCategoryInterface` and adding it to `CategoryRegistryService::__construct`; no modifications to the orchestration service, controller, CLI commands, or background job classes.

#### Scenario: New category can be added without editing core classes

- GIVEN a new feature introduces a new orphan type
- WHEN a developer creates a class implementing `CleanupCategoryInterface` and binds it into `CategoryRegistryService`
- THEN the system MUST automatically include the new category in scans, purges, the daily auto-purge default list (if Tier-A), CLI listings, and API responses
- AND no changes to `OrphanedDataCleanupService`, `AdminCleanupController`, `CleanupScanCommand`, `CleanupPurgeCommand`, or `OrphanedDataCleanupJob` are required

@e2e exclude a source-structure property (which classes a developer must edit), not runtime behaviour an instance can show. Pinned by tests/Unit/Service/OrphanedDataCleanupServiceTest.php::testScanAggregatesCountsAcrossRegistry (the orchestrator runs any category the registry returns) and tests/Unit/Service/Cleanup/CategoryRegistryServiceTest.php::testGetCategoryNamesReturnsRegisteredOrder.

#### Scenario: Category interface contract

- GIVEN the `CleanupCategoryInterface` with methods `getName()`, `getDisplayName()`, `isSafeToPurgeAutomatically()`, `isAvailable()`, `scan()`, and `purge(bool $dryRun = false)`
- WHEN a new class implements this interface
- THEN the registry MUST discover it via constructor injection and the orchestrator MUST invoke it without modification to calling code

@e2e exclude a code contract between CleanupCategoryInterface, the registry and the orchestrator; an instance only ever runs the four shipped classes. Pinned by tests/Unit/Service/Cleanup/CategoryRegistryServiceTest.php::testGetCategoriesReturnsKeyedMap and the four tests/Unit/Service/Cleanup/*CategoryTest.php files.

#### Scenario: Backwards-compatible category additions

- GIVEN an existing installation running cleanup regularly
- WHEN a new category is added via a code update
- THEN existing CLI commands, API endpoints, and the background job MUST work unchanged with the new category, picking it up automatically on the next request

@e2e exclude needs a code update that adds a category, which an e2e run against a fixed build cannot perform. Pinned by tests/Unit/Service/OrphanedDataCleanupServiceTest.php::testScanAggregatesCountsAcrossRegistry and tests/Unit/Service/OrphanedDataCleanupServiceCoverageTest.php::testPurgeSkipsUnregisteredAndUnavailableCategories (callers iterate whatever the registry holds).
