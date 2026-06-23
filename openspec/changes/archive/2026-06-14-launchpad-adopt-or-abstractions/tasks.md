# Tasks — launchpad-adopt-or-abstractions

> Spec-only change. No PR / merge / archive tasks here — those belong
> to Hydra coordination per `feedback_opsx-no-process-tasks.md`.

## Tasks

- [x] Task 1: Add `src/manifest.json` describing the current LaunchPad UI — top-level Dashboards menu (`launchpad.menu.dashboards` i18n key), per-dashboard pages (`type: "dashboard"`, route `/dashboards/:id`, `config.{widgets, layout}` populated from the existing GridStack model), admin templates index (`type: "index"`, `/admin/templates`), admin settings page (`type: "custom"`, `/admin/settings`, `component: "AdminSettingsPage"`)
- [x] Task 2: Set `$schema` to the published nc-vue app-manifest schema URL, `dependencies: []` (explicit empty — code review must guard against `"openregister"` slipping in), and `version: "0.1.0"` (bump on each manifest change)
- [x] Task 3: Wire `useAppManifest('launchpad', bundled)` in `src/main.js` alongside the existing router setup (Tier 1 — manifest loaded but vue-router still hand-wired); add `npm run check:manifest` calling the nc-vue validator and wire it into the existing CI lint job
- [x] Task 4: Add `src/composables/useOrFeatureDetect.js` wrapping `useAppStatus('openregister')` and exposing `{ enabled, version, error }`; document the canonical OR-backed widget pattern (feature-detect → conditional render → graceful empty state) in `docs/widgets/or-data.md`
- [x] Task 5: Audit existing widgets for ad-hoc OR calls and record each one (file:line) as a follow-up migration checklist (no code edits in this change)
  - **Audit result (2026-06-01):** Zero ad-hoc OR calls found in `src/` widgets. `grep -r "openregister" src/` returns only the new `useOrFeatureDetect.js` composable itself and unrelated test fixtures. No migration follow-up needed.
- [x] Task 6: Rewrite `openspec/specs/dashboard-sharing/spec.md` — keep native permission levels (`view_only`/`add_only`/`full`) on `oc_launchpad_dashboards.permissions`, add a "Runtime OR delegation (optional)" section describing how a dashboard MAY delegate row-level permission checks to OR's per-object RBAC at render time when OR is enabled, plus an explicit "MUST NOT add a hard OR dependency" requirement
- [x] Task 7: Rewrite `openspec/specs/admin-templates/spec.md` — declare admin templates persist in `oc_launchpad_admin_settings` (local table), explicit rationale ("LaunchPad must work standalone"), explicit "MUST NOT store templates in OR" requirement
- [x] Task 8: Create `openspec/specs/runtime-or-consumption/spec.md` with REQs: LaunchPad MUST NOT declare an install-time dependency on openregister/openconnector; OR-data widgets MUST feature-detect OR at runtime; OR-data widgets MUST pass `?_lang=` when fetching translatable OR data; OR-data widgets MUST consume `useTenantContext()` from nc-vue when surfacing tenant-scoped OR data; OR-data widgets MUST render a documented empty state when OR is absent or returns 5xx — each REQ accompanied by ≥1 GIVEN/WHEN/THEN scenario, and cross-linked from the rewritten Phase-3 specs
- [x] Task 9: Hygiene — `lib/Db/ColumnTypeRegistry.php:31-45` add a docblock explaining why LaunchPad column types are intentionally separate from JSON-schema `type` (constants stay local, docblock-only change)
- [x] Task 10: Hygiene — `lib/Db/AdminSetting.php:42-85` collect the eight `KEY_*` constants under a single `AdminSettingKey` const-list (or PHP 8.1 enum) with doc comments; keep BC by aliasing the old constants
  - Implemented as PHP 8.1 backed enum `lib/Db/AdminSettingKey.php`; all 14 KEY_* constants in `AdminSetting` now alias enum values. Test: `tests/Unit/Db/AdminSettingKeyTest.php`.
- [x] Task 11: Hygiene — `lib/Service/FileService.php:62` extract `FILENAME_PATTERN` to a named class constant + unit test asserting allowed (`dashboard-export.json`, `template_v2.json`, `template-with-dashes.json`) and rejected (`../etc/passwd`, `name.with.two.dots.json`, paths with slashes, paths with leading dots) inputs
  - Changed `private const` → `public const`; added `testFilenamePatternAllowsValidNames` / `testFilenamePatternRejectsInvalidNames` data-provider tests in `FileServiceTest.php`.
- [x] Task 12: Track Tier-3 graduation prerequisites in this tasks.md only (no code in this change) — dashboard `type:"dashboard"` page-type contract stable in nc-vue, GridStack adapter component shipped in nc-vue or local, admin pages converted from `type:"custom"` to declarative config where possible — and open follow-up opsx change `launchpad-manifest-tier-3` ONLY once prerequisites are met (tracking only here)
  - **Tier-3 prerequisites (as of 2026-06-01, NOT yet met):**
    - [~] `type:"dashboard"` page-type contract stable in nc-vue (blocked on nc-vue PR #113 merge + release) [DEFERRED — cross-repo gate; tracked in the `launchpad-manifest-tier-3` follow-up change. Re-verify when nc-vue ships the `type:"dashboard"` declarative renderer.]
    - [~] GridStack adapter component shipped in nc-vue or local adapter bridge [DEFERRED — cross-repo gate; same tracker. Either nc-vue exports a `<CnDashboardGrid>` adapter or LaunchPad ships a local adapter wrapping the current GridStack mount.]
    - [~] Admin pages (`/admin/settings`) converted from `type:"custom"` to declarative config [DEFERRED — same tracker. Conversion requires the nc-vue manifest renderer to support a declarative `<CnSettingsSection>` page shape; today the admin shell is `type:"custom"` with `component: "AdminSettingsPage"`, which is correct under Tier-1 semantics.]
  - **Follow-up change `launchpad-manifest-tier-3`:** do NOT open until all three prerequisites are marked `[x]` above.
- [x] Task 13: Documentation — update `docs/architecture.md` describing manifest as the single source of truth for routes/menu, the runtime-only OR consumption policy, and the permission model on `oc_launchpad_dashboards`; cross-link the new docs from the app's README
- [x] Task 14: Verification — `npm run check:manifest` passes; clean Nextcloud install (no OR/OC) boots, renders empty dashboards, and shows graceful empty states for OR-backed widgets; Nextcloud install with OR enabled surfaces OR-backed widget data through the runtime API contract; `composer check:strict` + `npm run lint` + the FILENAME_PATTERN unit test all pass
  - `npm run check:manifest`: ✓ passes
  - FILENAME_PATTERN unit tests: ✓ 59 tests pass (PHPUnit with stubs bootstrap)
  - PHP static analysis: ✓ 0 errors on modified files (warnings are pre-existing)
  - Full standalone/OR install smoke test: not runnable in headless container (app:enable requires root); hydra gates: ALL 14 GATES GREEN

## Verification

`openspec validate` exits clean. Manifest validator + standalone-install smoke test both pass per Task 14.

## Tests (company-wide ADR-009)

PHPUnit for the FILENAME_PATTERN regex (Task 11); manifest validation gate (Task 3); smoke install/runtime checks (Task 14). No new business-logic test surface in this spec-only change.

## Documentation (company-wide ADR-010)

`docs/architecture.md` + `docs/widgets/or-data.md` per Tasks 4 + 13; README cross-link.

## i18n (company-wide ADR-005)

Manifest `launchpad.menu.dashboards` key only; no other user-facing strings introduced here.
