# Tasks — admin-group-management

## Tasks

- [ ] Task 1: Scaffold `src/components/admin/tabs/GroupDashboardsTab.vue`
      under the existing Beheer tab strip (eighth tab); add
      `data-test="tab-group-dashboards"` and an `NcEmptyContent`
      placeholder while the rest of the slice fills in.
- [ ] Task 2: Add Pinia store slice `src/stores/groupDashboards.js`
      with `fetchGroups`, `fetchGroupDashboards(groupId)`, `create`,
      `update`, `delete`, `setDefault` — wraps the existing
      `/api/dashboards/group/...` endpoints from
      `multi-scope-dashboards`.
- [ ] Task 3: Render the group list in `GroupDashboardsTab.vue` —
      one row per NC group + the synthetic `default` sentinel, with
      a count badge of group-shared dashboards and a quick-action
      menu (View / Create / Manage).
- [ ] Task 4: Add `src/components/admin/group/CreateGroupDashboardModal.vue`
      — `NcDialog`-based form (name, icon, layout template selector,
      default flag) that POSTs to
      `/api/dashboards/group/{groupId}` via the store.
- [ ] Task 5: Add `src/components/admin/group/ManageGroupDashboardsModal.vue`
      — per-group list of dashboards with rename / delete /
      set-default actions; respects the last-in-group delete guard
      (HTTP 400 surfacing as a toast).
- [ ] Task 6: i18n — `en.json` + `nl.json` for all new strings
      (`Group dashboards`, `Create group dashboard`, `Manage`,
      `Default group`, `Last dashboard cannot be deleted`, etc.).
- [ ] Task 7: Vitest coverage — `GroupDashboardsTab` row rendering,
      `CreateGroupDashboardModal` happy path + validation,
      `ManageGroupDashboardsModal` delete-guard surface, store
      actions wired correctly to the endpoints.
- [ ] Task 8: Newman — extend the existing "Group-shared dashboards"
      folder in `tests/integration/launchpad.postman_collection.json`
      with the admin-UI-driven request flow if any new endpoints
      surface; otherwise note in the folder that all surfaced calls
      are already covered.
- [ ] Task 9: Playwright — under the gate-19 honest-coverage program
      add `tests/e2e/admin-group-management.spec.ts` covering create →
      member sees it on `/visible` → admin deletes (last-in-group
      guard) → member loses access.
- [ ] Task 10: Quality gates — ESLint clean, `composer check:strict`
      green (no backend changes; ensures no incidental regressions),
      SPDX in every new Vue + JS file's header comment, all
      hydra-gates green.
- [ ] Task 11: Documentation — add a "Group dashboards" tutorial
      under `docs/tutorials/admin/` walking through the
      create / manage / default workflow with screenshots; cross-link
      from `docs/architecture.md`.

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
