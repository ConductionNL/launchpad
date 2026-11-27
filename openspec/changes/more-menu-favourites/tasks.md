# Tasks: more-menu-favourites

- [ ] Backend: `POST`/`DELETE /api/dashboards/{uuid}/favourite` (visible dashboards only, idempotent, answers the list); preference `favourite_dashboards`; dropped entries cleaned on read; route auth `#[NoAdminRequired]`
- [ ] Initial state: `favouriteDashboards` next to the visible dashboards in `PageController::index` (no fetch on menu open)
- [ ] `DashboardMoreMenu.vue` / `LaunchpadMoreMenu.vue`: Dashboards column shows favourites; empty state with the open dashboard, the explanation line and More; More expands to all dashboards in place (nine rows, scrolling), Less returns; New dashboard below the list (personal dashboards allowed, quota not reached) creates and opens "My workspace"
- [ ] Star button per row (`aria-pressed`, accessible name per state, `#f0b400` filled); toggling updates the store optimistically and rolls back with an error toast on failure
- [ ] Footer of the menu: Powered by sendent and Conduction, links with `target="_blank" rel="noopener noreferrer"`
- [ ] This dashboard: Download between Duplicate as new dashboard and Set as default; remove the header Download button
- [ ] This dashboard: no Read receipts item (moved to the admin page)
- [ ] Settings group: Announcements entry for people who may manage announcements, after Admin settings
- [ ] Remove `DashboardSwitcherSidebar.vue`, `SidebarFooter.vue`, `SidebarBackdrop.vue`, `DashboardRowActions.vue`, the shell's `sidebarOpen` state and their tests
- [ ] Translations (en, nl and every shipped locale) for the new strings
- [ ] Unit tests: favourite controller and service (star, unstar, invisible dashboard 404, cleanup), menu component (empty state, favourites, expand, collapse, star toggle, footer, Download position)
- [ ] Playwright `tests/e2e/more-menu-favourites.spec.ts`: empty state; star a dashboard in the expanded list and see it in the short list after a reload; unstar; Download in This dashboard; New dashboard in the expanded list creates and opens a dashboard; no sidebar and no header Download
- [ ] Rewrite or remove the e2e specs that open the sidebar
- [ ] Live check on a sandbox against LpMeer and LpMeerAlle (pixel pass), and the page within the speed rule
