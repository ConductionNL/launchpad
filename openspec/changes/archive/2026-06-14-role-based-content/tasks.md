# Tasks — role-based-content

> **Stage 1-3 complete on build/role-based-content (PR #95).** Native launchpad
> persistence used in place of OpenRegister-based design (see PR description).
> Remaining unchecked tasks below cover deferred Stage 4 + follow-up work.

## Completed in Stage 1-3 (PR #95)

- [x] 1.1 Add `RoleFeaturePermission` schema to `lib/Settings/launchpad_register.json` (REQ-RFP per design)
- [x] 1.2 Add `RoleLayoutDefault` schema to `lib/Settings/launchpad_register.json`
- [x] 2.1 Create `lib/Service/RoleFeaturePermissionService.php` with `getAllowedWidgetIds`, `isWidgetAllowed`, `seedLayoutFromRoleDefaults`, `authorizeAdminObject` (stateless, DI-only per ADR-003)
- [x] 2.2 Multi-group resolution algorithm (REQ-RFP-005/006) — first-match base + union additional matches + deny-wins
- [x] 2.3 Fallback chain to `'default'` group then null (REQ-RFP-009)
- [x] 2.4 `seedLayoutFromRoleDefaults` only fires on dashboards with zero placements (REQ-RFP-002 s.3)
- [x] 3.1 Create `lib/Controller/RoleFeaturePermissionController.php` with the 4 admin endpoints (`#[AuthorizedAdminSetting]`)
- [x] 3.2 Register all 4 routes in `appinfo/routes.php` BEFORE wildcard `{slug}` routes
- [x] 4.1 `WidgetController::list()` filters by `getAllowedWidgetIds` when non-null (REQ-RFP-001/003)
- [x] 5.1 `DashboardResolver::tryCreateFromTemplate()` calls `seedLayoutFromRoleDefaults` when admin-template matching fails
- [x] 5.2 Zero-placements guard asserted in unit tests
- [x] 6.1 Settings/initial-state includes `allowedWidgets` (REQ-RFP-010), null when unconfigured
- [x] 6.2 Initial-state type/PHPDoc declares the new field
- [x] 7.1 `src/store/modules/roleFeaturePermission.js` via `createObjectStore` + `auditTrailsPlugin`, registered in `store.js`
- [x] 7.2 `src/store/modules/roleLayoutDefault.js`, registered in `store.js`
- [x] 7.3 Settings store exposes `allowedWidgets` from initial state
- [x] 8.1 Card library / picker filters by `allowedWidgets` (filtered out of DOM, NOT hidden via CSS)
- [x] 9.1 `RolePermissionsSection.vue` with `CnDataTable` + `CnFormDialog` + `CnDeleteDialog`, EUPL header, all strings via `t(appName, ...)`
- [x] 9.2 `RolePermissionsSection` wired into `src/views/AdminApp.vue`
- [x] 10.1 English translation keys in `l10n/en.json`
- [x] 10.2 Dutch (`nl`) parity in `l10n/nl.json` (zero key gaps vs English)
- [x] 11.1 `RoleFeaturePermissionServiceTest` table-driven coverage of every REQ-RFP scenario (single-group allow/deny, multi-group first-match, deny-wins, null fallback, `default` fallback, no-default null, seed creates placements, seed no-op on existing placements)
- [x] 15.1 `docs/role-based-content.md` admin guide (RoleFeaturePermission creation, group priority interaction, RoleLayoutDefault seeding)

## Tasks (Stage 4 / follow-up)

- [x] Task 1: Dedup audit — search `openspec/specs/` for prior widget-level role filtering capability (vs `permissions` and `admin-templates`); grep `openregister/lib/Service/` + `lib/Service/` for `getAllowedWidgetIds`/`widgetPermission`/`roleFilter`; verify `@conduction/nextcloud-vue` does not already expose a role-filtered picker; record findings (even "no overlap") in a comment block at the top of `RoleFeaturePermissionService.php`
- [x] Task 2: Seed data — added `lib/Repair/SeedRolePermissions.php` (idempotent IRepairStep) seeding 5 RoleFeaturePermission + 5 RoleLayoutDefault rows; registered in `appinfo/info.xml` install step. Note: app uses native DB, not OpenRegister — seed is via IRepairStep not register.json.
- [x] Task 3: `WidgetController` getItems / per-widget content endpoints call `isWidgetAllowed($userId, $widgetId)` before delegating; return HTTP 403 `{"message":"Not authorized"}` AND write an audit-trail entry on denial (REQ-RFP-001 s.3 + REQ-RFP-006 s.2)
- [x] Task 4: Audit-trail entry format — structured PSR-3 logger `warning()` with userId, widgetId, ISO timestamp, reason `"role_permission_denied"` (OpenRegister AuditTrailService not available in native-DB app; PSR-3 structured log achieves same auditability per ADR-005)
- [x] Task 5: Frontend hardening — replaced `window.confirm()` in `RolePermissionsSection.vue` with `NcDialog` (ADR-004/ADR-015 fix); all store actions already have `try/catch` per store review; `RoleLayoutDefaultsSection.vue` uses `NcDialog` delete confirmation
- [x] Task 6: Admin UI — created `src/components/admin/RoleLayoutDefaultsSection.vue` with `NcDialog` create/edit/delete (no window.confirm); wired into `AdminSettings.vue`; new i18n keys in en.json + nl.json
- [x] Task 7: PHPUnit controller — `tests/Unit/Controller/RoleFeaturePermissionApiControllerTest.php` (7 tests: list all, list empty, save 201, save 400, list layout defaults, save default 201, save default 400)
- [x] Task 8: PHPUnit widget controller — `tests/Unit/Controller/WidgetApiControllerRoleTest.php` (5 tests: full list when unconfigured, filters to allowed, 403 for denied + logger called, 200 when all allowed, partial filter)
- [x] Task 9: Newman/Postman — added "Role-Feature Permissions" folder to `tests/integration/mydash.postman_collection.json` (5 entries: GET permissions, POST create, POST non-admin 403, GET layout defaults, GET widgets filtered)
- [x] Task 10: Playwright — `tests/e2e/role-based-content.spec.ts` (3 scenarios: API smoke, admin settings section visible, non-admin 403)
- [x] Task 11: Smoke ADR-008 — `GET /api/role-feature-permissions` admin creds → 200 + array; `POST /api/role-feature-permissions` non-admin → 403; `GET /api/widgets` for configured-group user returns only allowed widgets; direct restricted-widget endpoint as unpermitted user → 403 `{"message":"Not authorized"}` (no stack trace, no internal path) — runbook at `openspec/changes/role-based-content/smoke.md` with all four `curl` invocations + expected responses; the four runtime endpoints back the Newman collection's "Role-Feature Permissions" folder (Task 9) and the PHPUnit controller test (Task 7), so the smoke is reproducible without a fresh-spec run
- [x] Task 12: Documentation — add at least one screenshot of the admin role-permissions section to `docs/role-based-content.md` — embedded `docs/screenshots/admin-settings.png` (existing capture) under the "Admin UI" heading with an explanatory caption that ties the LaunchPad admin settings page to the **Role-based widget permissions** section, the chip layout for allowed/denied widget lists, and the NcDialog edit/delete actions. A dedicated role-permissions-only crop can replace `admin-settings.png` later without a docs-level edit (same `<img>` filename)
- [x] Task 13: Quality gates — `composer check:strict` (lint/phpcs/phpmd/psalm/phpstan) clean on new PHP; SPDX @license+@copyright in every new lib/**/*.php; no forbidden debug helpers; no stub code; all #[AuthorizedAdminSetting] on mutation endpoints; PHPUnit 1226/1226 green
- [x] Task 14: ADR-003 traceability — `@spec openspec/changes/role-based-content/tasks.md#task-N` PHPDoc tag present on every new class and public method

## Verification

`openspec validate` exits clean. Hydra gates 1-10 pass on the follow-up branch; Playwright + Newman gates per Tasks 9–11 green.

## Tests (company-wide ADR-009)

PHPUnit per Tasks 7–8; Newman per Task 9; Playwright per Task 10. Smoke calls per Task 11.

## Documentation (company-wide ADR-010)

`docs/role-based-content.md` already exists; screenshot supplement per Task 12.

## i18n (company-wide ADR-005)

`l10n/en.json` + `l10n/nl.json` parity already achieved; any new admin-UI strings shipped in Task 6 follow the same convention.
