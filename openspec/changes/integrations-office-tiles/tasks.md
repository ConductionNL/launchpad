# Tasks: integrations-office-tiles

- [ ] 1. Implement `OfficeTilesService` (pack per user from `ITemplateManager::listCreators()` and enabled apps; create from template with free-name selection). Verify: PHPUnit.
- [ ] 2. Seed `office-tiles.list` and `office-tiles.create`, and add `GET /api/office-tiles` and `POST /api/office-tiles/create`. Verify: PHPUnit and route-reachability.
- [ ] 3. Add the `create` link type to `TileUpdater` validation and to `src/components/TileWidget.vue` with the name and folder dialog. Verify: Vitest and PHPUnit.
- [ ] 4. Add "Office tiles" to `src/modals/WidgetPickerModal.vue`, placing one container. Verify: Vitest.
- [ ] 5. Playwright with Nextcloud Office: place the pack, create a document twice, see two files.
- [ ] 6. Add `nl` and `en` strings, copy the spec to `openspec/specs/office-tiles/spec.md` on archive, and set int-office-tiles to `built` in the parity matrix with evidence lines.
