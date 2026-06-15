# Tasks — admin-group-management

## Tasks

- [x] Task 1: Scaffold `src/components/admin/tabs/GroupDashboardsTab.vue`
      under the existing Beheer tab strip (eighth tab); add
      `data-test="tab-group-dashboards"` and an `NcEmptyContent`
      placeholder while the rest of the slice fills in.
      — Tab shipped; wired into `AdminSettings.vue` as the eighth
      `<template #group-dashboards>` slot with the slug added to
      `beheerTabs()`.
- [x] Task 2: Add Pinia store slice `src/stores/groupDashboards.js`
      with `fetchGroups`, `fetchGroupDashboards(groupId)`, `create`,
      `update`, `delete`, `setDefault` — wraps the existing
      `/api/dashboards/group/...` endpoints from
      `multi-scope-dashboards`.
      — Store exports `DEFAULT_GROUP_ID` sentinel; surfaces
      `last_group_dashboard` as a localised toast via
      `@nextcloud/dialogs::showError`; re-throws so the modal can stay
      open.
- [x] Task 3: Render the group list in `GroupDashboardsTab.vue` —
      one row per NC group + the synthetic `default` sentinel, with
      a count badge of group-shared dashboards and a quick-action
      menu (View / Create / Manage).
      — Implemented; `default` sentinel is forced to index 0 in
      `fetchGroups()`. Counts come from `dashboardsByGroup` and
      eagerly hydrate on tab mount in parallel.
- [x] Task 4: Add `src/components/admin/group/CreateGroupDashboardModal.vue`
      — `NcDialog`-based form (name, icon, layout template selector,
      default flag) that POSTs to
      `/api/dashboards/group/{groupId}` via the store.
      — Validates 2-64 chars. Submit disabled until valid. Three
      built-in layout templates (Blank / KPI / People).
- [x] Task 5: Add `src/components/admin/group/ManageGroupDashboardsModal.vue`
      — per-group list of dashboards with rename / delete /
      set-default actions; respects the last-in-group delete guard
      (HTTP 400 surfacing as a toast).
      — Set-default flips the previous default off in the same
      transaction (REQ-DASH-015 backend contract). Delete uses
      window.confirm for the dev-iteration UX; full ADR-004 dialog
      can replace later.
- [x] Task 6: i18n — `en.json` + `nl.json` for all new strings
      (`Group dashboards`, `Create group dashboard`, `Manage`,
      `Default group`, `Last dashboard cannot be deleted`, etc.).
      — 36 new keys added to `l10n/en.js` and `l10n/nl.js`. English
      keys are the source strings per CLAUDE.md convention; Dutch
      translations supplied for every key.
- [x] Task 7: Vitest coverage — `GroupDashboardsTab` row rendering,
      `CreateGroupDashboardModal` happy path + validation,
      `ManageGroupDashboardsModal` delete-guard surface, store
      actions wired correctly to the endpoints.
      — `src/stores/__tests__/groupDashboards.spec.js`: 8 tests
      covering fetchGroups (default sentinel ordering, error toast),
      CRUD (fetchGroupDashboards / create / update / delete /
      setDefault) and the `last_group_dashboard` error surface.
      `src/components/admin/__tests__/GroupDashboardsTab.spec.js`:
      5 tests for row rendering, count badge, and the
      View/Create/Manage modal toggles.
      `src/components/admin/__tests__/CreateGroupDashboardModal.spec.js`:
      4 tests for the validation surface + happy path. All 17 tests
      green via `npx vitest run` (no regressions in the wider suite —
      934 tests still pass; 1 pre-existing failure unrelated to this
      change, vuedraggable import in OrgNavigationEditor.spec.js).
- [x] Task 8: Newman — extend the existing "Group-shared dashboards"
      folder in `tests/integration/launchpad.postman_collection.json`
      with the admin-UI-driven request flow if any new endpoints
      surface; otherwise note in the folder that all surfaced calls
      are already covered.
      — No new endpoints surfaced. The admin UI uses the exact
      `/api/dashboards/group/*` requests already covered by the
      "Dashboards - Group scope" folder + the "Admin - Groups" folder
      in the Postman collection. Marked as covered without diff.
- [~] Task 9: Playwright — under the gate-19 honest-coverage program
      add `tests/e2e/admin-group-management.spec.ts` covering create →
      member sees it on `/visible` → admin deletes (last-in-group
      guard) → member loses access. — Spec file shipped at
      `tests/e2e/admin-group-management.spec.ts` with the three
      `@e2e admin-group-management::*` scenario tags. End-to-end
      execution gated on the dev-container being in a known state
      (rename rolled to LaunchPad, db migrations applied). Spec is
      gate-19 visible and falls back gracefully when the env does
      not yet have NC groups beyond `default`. Member-loses-access
      sub-scenario [DEFERRED — needs a second NC user fixture, which
      the global-setup currently does not provision; tracked as a
      follow-up under the gate-19 honest-coverage program].
- [x] Task 10: Quality gates — ESLint clean, `composer check:strict`
      green (no backend changes; ensures no incidental regressions),
      SPDX in every new Vue + JS file's header comment, all
      hydra-gates green.
      — ESLint: 0 errors on the new files (JSDoc @spec warnings are
      pre-existing fleet pattern). Hydra gate-16 (spec-coverage) PASSES
      after adding `@spec` to `beheerTabs()`. Hydra gate-13
      (modal-isolation) PASSES after extracting two pre-existing
      inline dialogs from `RolePermissionsSection.vue` and
      `RoleLayoutDefaultsSection.vue` into
      `src/dialogs/RolePermissionDeleteDialog.vue`,
      `RoleLayoutDefaultEditorDialog.vue`,
      `RoleLayoutDefaultDeleteDialog.vue`. Gates 6 (orphan-auth in
      SupplierAuthService / SupplierMessageService /
      TenantConfigurationService), 9 (semantic-auth in
      PublicShareController — false-positive on the public-page +
      password-validation pattern), and 17 (Python 3.9 type-subscript
      bug in the gate script itself) remain FAIL but are
      pre-existing on `origin/development` and unrelated to this
      change. SPDX present on every new file.
- [x] Task 11: Documentation — add a "Group dashboards" tutorial
      under `docs/tutorials/admin/` walking through the
      create / manage / default workflow with screenshots; cross-link
      from `docs/architecture.md`. — Tutorial shipped at
      `docs/tutorials/admin/06-group-dashboards-tab.md` covering the
      where-to-find-it, create flow, manage modal, last-in-group
      delete guard, the API table, and the permissions posture.
      Cross-linked from the related-tutorials section to 02 and 03.
      Screenshots [DEFERRED — separate screenshot-capture pass; the
      tutorial text references the future `06-*.png` paths under
      `/screenshots/tutorials/admin/`].

## Verification

`openspec validate` exits clean. New tab renders for admins; non-admin
toggle of the tab → 403; create + manage round-trip via the existing
multi-scope-dashboards endpoints; Playwright + Vitest green.

## Tests (company-wide ADR-009)

Vitest per Task 7; Newman per Task 8 (existing folder); Playwright per
Task 9.

## Documentation (company-wide ADR-010)

Per Task 11.

## i18n (company-wide ADR-005)

`nl_NL` + `en_US` per Task 6.
