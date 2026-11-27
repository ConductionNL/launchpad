# Tasks: more-menu-favourites

- [x] 1. Unmount the dashboard switcher sidebar and its backdrop from the dashboard view (REQ-SHELL-004)
- [x] 2. Dashboards column: favourites short list, "More" unfolds in place and keeps the menu open, "Fewer" folds, every opening starts folded, first eight without favourites (REQ-SHELL-016)
- [x] 3. Star per row of the unfolded list, stored per user through the preferences endpoint, rolled back on a failed write, reachable with the arrow keys (REQ-SHELL-017)
- [x] 4. Powered by + Documentation footer in the More menu; Download in This dashboard, not in the header (REQ-SHELL-018)
- [x] 5. Unit tests: moreMenuItems (folded, favourites, unfolded, stars, toggle/parse), LaunchpadMoreMenu (keep open, stars, keyboard, footer), DashboardMoreMenu (load, store, rollback, fold on open, Download), StartPageMenu, DashboardPageHeader, Views wiring
- [x] 6. Verified live on the sandbox (NC 34 tarball, MariaDB, Redis, APCu, OR main): header only More, no panel, More keeps the menu open with stars, a star stores (PUT 200) and survives a reload, Download in This dashboard
- [ ] 7. e2e: support/dashboardSwitcher.ts and the specs that drive the old panel switch through the More menu
- [ ] 8. Delete DashboardSwitcherSidebar and its tests once no spec drives it
