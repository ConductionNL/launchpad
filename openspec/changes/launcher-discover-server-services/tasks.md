# Tasks: launcher-discover-server-services

- [ ] 1. Add `lib/Service/ServiceDiscoveryService.php` over `INavigationManager::getAll('link')`, excluding LaunchPad. Verify: PHPUnit with a stubbed manager.
- [ ] 2. Seed `tile.discover` in `lib/actions.seed.json` and add `GET /api/tiles/discover` with `#[NoAdminRequired]` and the action check. Verify: PHPUnit and route-reachability.
- [ ] 3. Add the "Discovered on this server" section to `src/modals/WidgetPickerModal.vue`, searchable with the existing box, with the "On this dashboard" mark. Verify: Vitest.
- [ ] 4. Place the tile through the existing tile call with the mapping in D2. Verify: Vitest on the payload.
- [ ] 5. Playwright: discover Deck and place it.
- [ ] 6. Add `nl` and `en` strings, fold the delta into `openspec/specs/tiles/spec.md` on archive, and set tile-discover-containers to `built` in the parity matrix with evidence lines.
