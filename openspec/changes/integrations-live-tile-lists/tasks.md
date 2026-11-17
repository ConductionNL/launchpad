# Tasks: integrations-live-tile-lists

- [x] 1. Validate `display`, `mapping` and `limit` in `LiveTileService::validateSourceConfig()`. Verify: PHPUnit for each rule and for an old config.
- [x] 2. Evaluate the mapping server-side for both source modes, drop unmapped fields, apply the limit, and cache the mapped result. Verify: PHPUnit with array and object payloads.
- [x] 3. Render list, table and key-value in `LiveTileWidget.vue`, with https-only links and accessible markup. Verify: Vitest and an axe check.
- [x] 4. Add the display choice, mapping rows and the "Latest documents" preset (SharePoint and Confluence variants) to `LiveTileWidgetForm.vue`, and rename its "OpenConnector" labels to "Integriq". Verify: Vitest.
- [ ] 5. Playwright: a URL-mode list tile against a fixture endpoint.
- [ ] 6. Add `nl` and `en` strings, fold the delta into `openspec/specs/live-data-tile-widget/spec.md` on archive, and set int-builder and int-intranet-content to `built` in the parity matrix with evidence lines.
