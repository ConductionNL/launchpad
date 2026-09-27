# Tasks: governance-rest-api-reference

- [ ] 1. Try `nextcloud/openapi-extractor` on `DashboardApiController`, `WidgetApiController` and `TileApiController`; record in design.md which generator is used. Verify: the generated fragment lists those routes.
- [ ] 2. Add response and request shape annotations to the public controllers (dashboards, placements, tiles, sharing, public links, export and import). Verify: psalm and phpstan stay green.
- [ ] 3. Generate `openapi.json` with `x-launchpad-stability` on every operation. Verify: the file validates as OpenAPI 3.1.
- [ ] 4. Add `composer lint:openapi` (regenerate and compare, every route documented) to `check:strict`. Verify: a fixture with an undocumented route fails.
- [ ] 5. Add the API page to the Docusaurus site with authentication instructions. Verify: the docs build.
- [ ] 6. Copy the spec to `openspec/specs/rest-api-reference/spec.md` on archive and set d-rest-api to `built` in the parity matrix with evidence lines.
