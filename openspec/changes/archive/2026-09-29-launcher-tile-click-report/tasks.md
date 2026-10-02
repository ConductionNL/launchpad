# Tasks: launcher-tile-click-report

- [x] 1. Add `tileTitle` and `dashboardName` to the top-tiles and breakdown responses. Verify: PHPUnit against the real mapper rows, including a deleted tile.
- [x] 2. Add the "Tiles" section to `AdminAnalytics.vue` with the period selector, the table and the CSV button, and the per-dashboard view. Verify: Vitest with real response shapes.
- [x] 3. Show the tracking-off state from the config endpoint. Verify: Vitest for on and off.
- [x] 4. `nl` and `en` strings; axe on the table (caption, scoped headers).
- [ ] 5. Playwright: click a tile as a user, read the count as an administrator. Not written in this change: the spec carries `@e2e exclude` naming the Vitest files.
- [x] 6. On archive fold the delta into `openspec/specs/dashboard-view-analytics/spec.md` and set `tile-clicks` to `built`.

