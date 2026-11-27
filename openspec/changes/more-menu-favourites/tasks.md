# Tasks: more-menu-favourites

- [x] Backend: `POST`/`DELETE /api/dashboards/{uuid}/favourite` (visible dashboards only, idempotent, answers the list); preference `favourite_dashboards`; dropped entries cleaned on read; route auth `#[NoAdminRequired]`
- [x] Initial state: `favouriteDashboards` next to the visible dashboards in `PageController::index` (no fetch on menu open)
- [x] `DashboardMoreMenu.vue` / `LaunchpadMoreMenu.vue`: Dashboards column shows favourites; empty state with the open dashboard, the explanation line and More; More expands to all dashboards in place (nine rows, scrolling), Less returns; New dashboard below the list (personal dashboards allowed, quota not reached) creates and opens "My workspace"
- [x] Star button per row (`aria-pressed`, accessible name per state, `#f0b400` filled); toggling updates the store optimistically and rolls back with an error toast on failure
- [x] Footer of the menu: Powered by sendent and Conduction, links with `target="_blank" rel="noopener noreferrer"`
- [x] This dashboard: Download between Duplicate as new dashboard and Set as default; remove the header Download button
- [x] This dashboard: no Read receipts item (moved to the admin page) (announcements-managed-in-settings #2287: `canViewReadReceipts` and the `read-receipts` item left DashboardMoreMenu; unit DashboardMoreMenu.spec.js and e2e dashboard-acknowledgements.spec.ts (#2296) check it is absent)
- [x] Settings group: Announcements entry for people who may manage announcements, after Admin settings
- [x] Remove `DashboardSwitcherSidebar.vue`, `SidebarFooter.vue`, `SidebarBackdrop.vue`, `DashboardRowActions.vue`, the shell's `sidebarOpen` state and their tests
- [x] Translations (en, nl and every shipped locale) for the new strings (en, nl, de, fr, es, it first; every other shipped locale, .js and .json, in help-launchpad-3: the 16 More menu strings incl. All dashboards, This dashboard, More, Default dashboard and the plural with each locale's plural count)
- [x] Unit tests: favourite controller and service (star, unstar, invisible dashboard 404, cleanup), menu component (empty state, favourites, expand, collapse, star toggle, footer, Download position)
- [x] Playwright `tests/e2e/more-menu-favourites.spec.ts`: empty state; star a dashboard in the expanded list and see it in the short list after a reload; unstar; Download in This dashboard; New dashboard in the expanded list creates and opens a dashboard; no sidebar and no header Download
- [x] Rewrite or remove the e2e specs that open the sidebar (except the three that follow REQ-SHELL-018/019 below)
- [ ] Live check on a sandbox against LpMeer and LpMeerAlle (pixel pass), and the page within the speed rule
- [x] Default pill on the effective-default row, folded and expanded (REQ-SHELL-018): "Default", tooltip "Opens automatically", accessible name "Default dashboard"; `effective-default-marker.spec.ts` follows
- [x] Full list: icons through the shared renderer, children indented under their parent with expand (REQ-TREEUI-001), "Filter by detail" above the list with the detail fields read on first expand (REQ-MDUI-003); `dashboards-tree-ui.spec.ts` and the MDUI-003 test follow
