# Tasks: platform-dashboard-theme-and-css

## Backend

- [ ] 1. Add the four columns in a migration with getters and setters on `lib/Db/Dashboard.php`, exposed in the dashboard JSON. Verify: migration test and a serialisation test.
- [ ] 2. Validate the look in `DashboardService::updateDashboard()` (hex colours, resource belongs to the dashboard or is shared, accent at least 3:1 against the background, derived text colour). Verify: PHPUnit.
- [ ] 3. Add `sabberworm/php-css-parser` and `CustomCssSanitiser` as in D3. Verify: PHPUnit per dropped construct, scoping and the size cap; `composer audit` clean.
- [ ] 4. Allow `custom_css` only for administrators on dashboards and add the global `custom_css` admin setting. Verify: PHPUnit for 403 on a non-admin.

## Frontend

- [ ] 5. Add the "Look" tab to `src/modals/DashboardConfigModal.vue` with colour pickers, the resource picker and live contrast. Verify: Vitest and an axe check.
- [ ] 6. Apply the look and scoped CSS in `src/views/WorkspaceApp.vue` to the dashboard region only, inserting CSS as text. Verify: Vitest.
- [ ] 7. Add the admin CSS section showing what the sanitiser dropped. Verify: Vitest.

## Close

- [ ] 8. Playwright: one dashboard's look changes nothing else; `@import` is dropped.
- [ ] 9. Add `nl` and `en` strings, fold the delta into `openspec/specs/dashboards/spec.md` on archive, and set d-theme-per-dashboard and p-custom-css to `built` in the parity matrix with evidence lines.
