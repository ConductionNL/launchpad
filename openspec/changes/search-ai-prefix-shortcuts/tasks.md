# Tasks: search-ai-prefix-shortcuts

- [ ] 1. Add `search_shortcuts` to `lib/Db/AdminSettingKey.php` and validate it in `AdminSettingsService` (prefix pattern, uniqueness, template check, limit 30). Verify: PHPUnit.
- [ ] 2. Provide the list through `lib/Service/InitialStateBuilder.php`. Verify: initial-state contract test.
- [ ] 3. Add `parsePrefix()` to `src/composables/useTileSearch.js` and route a match ahead of ranking. Verify: Vitest.
- [ ] 4. Render the "Search <name> for <rest>" result and the `?` list in `SearchWidget.vue` and `RuntimeShellSearch.vue`, opening through `useTileSearchHost.js`. Verify: Vitest and an axe check on the list.
- [ ] 5. Add the admin section to edit shortcuts. Verify: Vitest.
- [ ] 6. Playwright: `!t printer` opens the configured site.
- [ ] 7. Add `nl` and `en` strings, fold the delta into `openspec/specs/tile-quick-search/spec.md` on archive, and set d-search-prefix to `built` in the parity matrix with evidence lines.
