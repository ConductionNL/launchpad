# Tasks: launcher-tile-sorting

- [ ] 1. Add `ContainerWidgetForm.vue` with the three communal fields plus "Sort tiles", and register it in `FORM_OVERRIDES` in `src/constants/widgetRegistry.js`. Verify: Vitest on the form.
- [ ] 2. Keep local `{count, lastUsedAt}` per placement in `src/composables/useTileClickTracking.js`, storage failures caught. Verify: Vitest with storage available and blocked.
- [ ] 3. Order and reflow children in view mode in `ContainerWidget.vue` for the five modes, with the tie rules and a once-per-load shuffle. Verify: Vitest per mode.
- [ ] 4. Add "Forget my usage" to the container menu and the "Sorted by" accessible description. Verify: Vitest and an axe check.
- [ ] 5. Playwright: most used moves a clicked tile to the front after reload; "By hand" restores the stored layout.
- [ ] 6. Add `nl` and `en` strings, fold the delta into `openspec/specs/container-widget/spec.md` on archive, and set d-tile-sort and tile-most-used to `built` in the parity matrix with evidence lines.
