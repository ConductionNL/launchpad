# Tasks: launcher-discover-server-services

- [x] 1. Add `lib/Service/ServiceDiscoveryService.php` over `INavigationManager::getAll('link')`, excluding LaunchPad. Verify: PHPUnit with a stubbed manager.
- [x] 2. Seed `tile.discover` in `lib/actions.seed.json` and add `GET /api/tiles/discover` with `#[NoAdminRequired]` and the action check. Verify: PHPUnit and route-reachability.
- [x] 3. Add the "Discovered on this server" section to `src/modals/WidgetPickerModal.vue`, searchable with the existing box, with the "On this dashboard" mark. Verify: Vitest.
- [x] 4. Place the tile through the existing tile call with the mapping in D2. Verify: Vitest on the payload.
- [ ] 5. Playwright: discover Deck and place it.
  - Written: `tests/e2e/discover-server-services.spec.ts` (Deck enabled, listed under "Discovered on this server" with its icon and LaunchPad not listed, placed in one click as an `app` tile opening `/apps/deck/`, then marked "On this dashboard"; the add flow opens through "More" → "Add app" from launchpad#1300). Open: the run against a live instance with Deck.
- [ ] 6. Add `nl` and `en` strings, fold the delta into `openspec/specs/tiles/spec.md` on archive, and set tile-discover-containers to `built` in the parity matrix with evidence lines.
