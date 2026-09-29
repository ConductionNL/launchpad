# Tasks: dashboard-tree-navigation

- [ ] 1. Load the tree in the workspace page and render nested groups in `DashboardSwitcher.vue` behind "any dashboard has a parent". Verify: Vitest with a flat set (unchanged) and a nested set.
- [ ] 2. Add the "Parent dashboard" select and slug field to the create and rename dialogs, surfacing the `validateParent` and `validateSlugUnique` messages. Verify: Vitest with the real error bodies.
- [ ] 3. Add the breadcrumb component fed by the server crumbs, hidden for a top-level dashboard. Verify: Vitest and axe (`nav` with an accessible name).
- [ ] 4. Resolve a path URL to a dashboard and redirect. Verify: Vitest with a hit and a miss.
- [ ] 5. Delete dialog counts descendants. Verify: Vitest.
- [ ] 6. `nl` and `en` strings; Playwright: create a child, see it nested and the breadcrumb; fold the delta into `openspec/specs/dashboards/spec.md`; set `dash-tree` to `built`.

