# Tasks: dashboards-personal-hide-ui

- [ ] 1. Add `getPersonalLayer`, `savePersonalLayer` and `resetPersonalLayer` to `src/services/api.js` and `src/stores/personalLayer.js`. Verify: Vitest with the real refusal shape.
- [ ] 2. Add "Hide for me" to the widget menu in view mode, hidden for compulsory placements. Verify: Vitest for view, edit and compulsory.
- [ ] 3. Add the "Hidden (n)" popover with "Show again" and the reset dialog in `src/dialogs/`. Verify: Vitest plus axe.
- [ ] 4. `nl` and `en` strings through the writing skill.
- [ ] 5. Playwright: Pieter hides a widget, Sanne still sees it; Pieter resets and it returns.
- [ ] 6. On archive fold the delta into `openspec/specs/dashboards/spec.md` and set `build-personal-hide` to `built`.

