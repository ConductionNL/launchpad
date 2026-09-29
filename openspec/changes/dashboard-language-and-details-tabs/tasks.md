# Tasks: dashboard-language-and-details-tabs

- [ ] 1. Confirm from `DashboardApiController` whether the dashboard payload states that variants exist; if not, add a `hasVariants` flag to it. Verify: PHPUnit against the real controller.
- [ ] 2. Add `LanguagesTab.vue`: list, add (blank or copy), edit, delete, make primary, using the existing endpoints. Verify: Vitest with the real envelope; the refusal to delete the only variant is shown.
- [ ] 3. Load the resolved variant on the workspace page and show the fallback note. Verify: Vitest for a match, a fallback and no variants.
- [ ] 4. Add the administration field editor and the `DetailsTab.vue` values form. Verify: Vitest for each of the five field types; PHPUnit that the real service accepts the exact payload.
- [ ] 5. Pass `metadata.<key>` through the dashboards list endpoint to `filterDashboards()` and add the switcher control. Verify: PHPUnit with two dashboards and a filter; the caller-of-the-method check `git grep filterDashboards lib/` finds the controller.
- [ ] 6. `nl` and `en` strings; Playwright: add a Dutch variant, switch language, see it; set a department and filter by it.
- [ ] 7. On archive fold the deltas into the two main specs and set `dash-languages` and `dash-metadata` to `built`.

