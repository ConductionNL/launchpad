# Tasks: sharing-dashboard-schedule-screen

- [ ] 1. Migration and entity: nullable `unpublish_at` on dashboards; `schedule` accepts `unpublishAt` and refuses one before `publishAt`. Verify: PHPUnit red first on the real service.
- [ ] 2. Read filter: a published dashboard past `unpublishAt` is treated as unpublished. Verify: PHPUnit.
- [ ] 3. `ScheduleDashboardDialog.vue` in `src/dialogs/` and the three menu items, calling the existing store actions. Verify: Vitest with the real error shape.
- [ ] 4. Header line for scheduled and take-down dates. Verify: Vitest.
- [ ] 5. Strings in every shipped locale through the writing skill.
- [ ] 6. On archive fold the delta into `openspec/specs/dashboards/spec.md` and set `s-schedule` to `built`.
