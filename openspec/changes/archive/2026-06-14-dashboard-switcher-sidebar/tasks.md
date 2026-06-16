# Tasks — dashboard-switcher-sidebar

## Tasks

- [x] Task 1: Create `src/components/Workspace/DashboardSwitcherSidebar.vue` with props (`isOpen`, `groupName`, `groupDashboards`, `userDashboards`, `activeDashboardId`, `allowUserDashboards`) and emits (`switch`, `create-dashboard`, `delete-dashboard`, `update:open`) per REQ-SWITCH-001..007
- [x] Task 2: Internal computeds `matchedGroupDashboards = groupDashboards.filter(d => d.source !== 'default')` and `defaultGroupDashboards = groupDashboards.filter(d => d.source === 'default')` (REQ-SWITCH-001)
- [x] Task 3: Every icon rendered via `<IconRenderer>` (no inline `v-if="iconUrl"` branches in this template) — REQ-SWITCH-007
- [x] Task 4: CSS root: `position: fixed; top: 50px; width: 280px; z-index: 1500; transform: translateX(-100%); transition: transform .25s ease`; `&.open` selector toggles `transform: translateX(0)` when `isOpen === true` (REQ-SWITCH-006)
- [x] Task 5: Personal-row delete affordance — wave3.3 replaced the inline hover-revealed delete button with `DashboardRowActions` (per-row cog menu). Delete action is accessible via the cog on any personal row and emits `delete-dashboard(id)` without triggering a switch; CSS class `__delete` is retained as a stable selector hook for future use (REQ-SWITCH-004 satisfied via cog)
- [x] Task 6: Active-item highlight — row with `id === activeDashboardId` gets `.active` class with `--color-primary-element-light` background + `--color-primary` icon tint (REQ-SWITCH-003)
- [x] Task 7: Dashboard-row click emits `update:open(false)` THEN `switch(id, source)` where `source` is derived from the row's section (REQ-SWITCH-002)
- [x] Task 8: `+ New Dashboard` affordance only rendered when `allowUserDashboards === true`; implemented as a dedicated `NcButton` card below the personal list (`__add-dashboard-card`) instead of an inline row; click emits `update:open(false)` THEN `create-dashboard()` (REQ-SWITCH-005)
- [x] Task 9: Add companion `src/components/Workspace/SidebarBackdrop.vue` (click-to-close backdrop) for the runtime shell to wire alongside the sidebar
- [x] Task 10: Sidebar wired in `src/views/Views.vue` (the inner runtime-shell view) with `v-model="sidebarOpen"`, `@switch`, `@create-dashboard`, `@delete-dashboard`, `@toggle-edit`, `@open-config`, `@add-custom-widget`, `@set-default`; `SidebarBackdrop` rendered alongside it. `WorkspaceApp.vue` intentionally has no second mount (PR #114 confirmed Views.vue as the sole owner; e2e gate asserts only one `.dashboard-switcher-sidebar` in the DOM)
- [x] Task 11: Vitest — section visibility table (3 sections × empty/non-empty); emit order on switch (`update:open(false)` BEFORE `switch(id, source)`); `source` discriminator matches the section the row was rendered in; `delete-dashboard` does NOT also emit `switch` or `update:open`; Add-Dashboard card absent when `allowUserDashboards: false`; `.active` class is reactive to `activeDashboardId` changes — see `src/components/Workspace/__tests__/DashboardSwitcherSidebar.spec.js`
- [x] Task 12: Playwright — sidebar open/close via hamburger; per-row cog reveals Edit/Configure/Add-widget/Delete actions on personal rows; no inline `__delete` X button; clicking a sidebar row triggers `GET /api/dashboard/{id}`; footer logos and documentation link rendered — see `tests/e2e/wave3-runtime-shell.spec.ts` and `tests/e2e/spec-coverage/spec-coverage.spec.ts`
- [x] Task 13: Quality + a11y — ESLint clean (warnings only, pre-existing); translations present (`'Dashboards'`, `'Default'`, `'My Dashboards'`, `'+ New Dashboard'`, `'Delete dashboard'`, `'Close'`) in `l10n/en.json` + `l10n/nl.json`; Esc closes (`@keydown.esc`); close button has `aria-label="Close"`; cog menu has `aria-label="Dashboard menu"`; every row has `:aria-label="dashboard.name"`; `delete-dashboard` action in cog has `aria-label="Delete dashboard"`

## Verification

`openspec validate` exits clean. Sidebar opens/closes via keyboard + backdrop; all three sections render correctly and the active row stays highlighted across switches.

## Tests (company-wide ADR-009)

Vitest per Task 11; Playwright per Task 12. No backend surface.

## Documentation (company-wide ADR-010)

Changelog entry covering the new sidebar component and the parent wiring contract.

## i18n (company-wide ADR-005)

`nl_NL` + `en_US` per Task 13.
