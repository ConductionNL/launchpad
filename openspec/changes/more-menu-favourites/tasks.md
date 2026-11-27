# Tasks: more-menu-favourites

- [x] 1. Unmount the dashboard switcher sidebar and its backdrop from the dashboard view (REQ-SHELL-004)
- [x] 2. Dashboards column: favourites short list, "More" unfolds in place and keeps the menu open, "Fewer" folds, every opening starts folded; without favourites the open dashboard, a line about starring and More (Ruben, 27 Nov) (REQ-SHELL-016)
- [x] 3. Star per row of the unfolded list, stored per user through the preferences endpoint, rolled back on a failed write, reachable with the arrow keys (REQ-SHELL-017)
- [x] 4. Powered by + Documentation footer in the More menu; Download in This dashboard, not in the header (REQ-SHELL-018)
- [x] 5. Unit tests: moreMenuItems (folded, favourites, unfolded, stars, toggle/parse), LaunchpadMoreMenu (keep open, stars, keyboard, footer), DashboardMoreMenu (load, store, rollback, fold on open, Download), StartPageMenu, DashboardPageHeader, Views wiring
- [x] 6. Verified live on the sandbox (NC 34 tarball, MariaDB, Redis, APCu, OR main): header only More, no panel, More keeps the menu open with stars, a star stores (PUT 200) and survives a reload, Download in This dashboard
- [ ] 7. e2e: support/dashboardSwitcher.ts (done), more-menu, dashboard-download, dashboard-period-and-download (green live) and the new more-menu-favourites.spec.ts (4/4 live) are done; left: dashboard-switcher.spec.ts (tests the removed panel itself: rewrite or retire with task 8) and the other ~40 specs that click inside `.dashboard-switcher-sidebar`
- [ ] 8. Delete DashboardSwitcherSidebar and its tests once no spec drives it
