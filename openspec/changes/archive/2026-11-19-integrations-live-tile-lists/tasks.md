# Tasks: integrations-live-tile-lists

- [x] 1. Validate `display`, `mapping` and `limit` in `LiveTileService::validateSourceConfig()`. Verify: PHPUnit for each rule and for an old config.
- [x] 2. Evaluate the mapping server-side for both source modes, drop unmapped fields, apply the limit, and cache the mapped result. Verify: PHPUnit with array and object payloads.
- [x] 3. Render list, table and key-value in `LiveTileWidget.vue`, with https-only links and accessible markup. Verify: Vitest and an axe check.
- [x] 4. Add the display choice, mapping rows and the "Latest documents" preset (SharePoint and Confluence variants) to `LiveTileWidgetForm.vue`, and rename its "OpenConnector" labels to "Integriq". Verify: Vitest.
- [x] 5. Playwright: a URL-mode list tile against a fixture endpoint. Written 17 Nov: `tests/e2e/live-tile-lists.spec.ts` starts its own fixture server, allow-lists it, places a list tile and checks the server answer (two mapped rows, no unmapped field, a script link dropped) and the dashboard (an https link, plain text for the other). Not run yet: needs a live instance that can reach the fixture (`LIVE_TILE_FIXTURE_HOST`).
  - 19 Nov live pass (Nextcloud 34.0.6): `tests/e2e/live-tile-lists.spec.ts` passed (2 of 2) with `LIVE_TILE_FIXTURE_HOST=host.docker.internal` and `allow_local_remote_servers` on the rig.
- [x] 6. Add `nl` and `en` strings, fold the delta into `openspec/specs/live-data-tile-widget/spec.md` on archive, and set int-builder and int-intranet-content to `built` in the parity matrix with evidence lines. Strings (nl, en) and the parity rows done 17 Nov (`openspec/parity/capabilities.json`: both `built` with evidence); folding the delta waits for the archive.
  - The delta is folded into `openspec/specs/live-data-tile-widget/spec.md` on archive (19 Nov).
