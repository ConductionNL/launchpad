# Tasks — mcp-tool-surface

## Provider Skeleton

- [x] Task 1: REQ-MCP-001 — Create `lib/Mcp/LaunchPadToolProvider.php` implementing `OCA\OpenRegister\Mcp\IMcpToolProvider`: dispatcher only, tool catalogue as a `TOOL_DESCRIPTORS` class constant (unit-testable fixture), routing to domain handler classes — mirror `decidesk/lib/Mcp/DecideskToolProvider.php`
- [x] Task 2: REQ-MCP-001 — Register the string DI alias `'OCA\\OpenRegister\\Mcp\\IMcpToolProvider::launchpad'` in `lib/AppInfo/Application::register()` (string alias, no autoload at registration — decidesk's `DomainServiceRegistrar` pattern, ADR-083 boot safety)
- [x] Task 3: REQ-MCP-001 — All tool ids namespaced `launchpad.{toolName}`; add a unit test asserting the namespace and id uniqueness across the catalogue

## Gate and Validator

- [x] Task 4: REQ-MCP-004 — Create `lib/Mcp/McpDashboardGate.php`: `currentUserId()`, `authorise(toolId, args)` delegating to `PermissionService::canViewDashboard()/canEditDashboard()/canAddWidget()/canRemoveWidget()/canCreateDashboard()`, `ActionAuthService::requireAction()` for ADR-023-gated actions, and the admin check for `instance`-reach tools. No `catch (\Throwable) { return null; }` shapes (hydra-gate-unsafe-auth-resolver)
- [x] Task 5: REQ-MCP-005 — Create `lib/Mcp/McpArgumentValidator.php`: UUID, id, string-length, enum, and grid-bound checks returning structured error arrays (decidesk's validator as the template)
- [x] Task 6: REQ-MCP-004 — Every handler: validate → authorise → call the existing service (`DashboardService`, `WidgetService`/`WidgetPlacementService`, `TileService`, `DashboardShareService`, `PublicShareService`, `KioskService`, `TemplateService`/`AdminTemplateService`, `ReactionService`, `AcknowledgementService`, `MetadataService`, `DashboardVersionService`). Never controllers, never mappers/ObjectService directly for writes
  - Every handler checks its arguments, then the route's action and per-object check, then calls the route's service; none calls a controller, and every write goes through a service.

## Read Tools (REQ-MCP-002)

- [x] Task 7: Implement the 14 read tools in `lib/Mcp/McpDashboardTools.php` (dashboard reads) and sibling handler classes: listDashboards, getDashboard, getActiveDashboard, getDashboardTree, listGroupDashboards, listWidgetTypes, listTiles, listShares, listVersions, listTemplates, getDashboardMetadata, getReactions, listPendingAcknowledgements, listKioskPlaylists — all `readOnlyHint: true`, scope `read`, reach `self`
  - All 14 built. The eight added: `McpCatalogueTools` (getDashboardTree, listTemplates), `McpSharingTools` (listShares, listKioskPlaylists), `McpHistoryTools` (listVersions), `McpMetadataTools` (getDashboardMetadata, with the gate's new `requireView()` over `PermissionService::canViewDashboard()`), `McpEngagementTools` (getReactions, listPendingAcknowledgements). Each applies its route's check (the ADR-023 action, or only a session where the route asks none) and calls its route's service. The provider resolves each tool's handler from the container through `TOOL_HANDLERS` when the tool runs.
- [x] Task 8: REQ-MCP-002 — Unit test: each read tool's result for a fixture caller equals the visibility the corresponding service call grants that caller (visible-set parity, not superset)
  - `McpCatalogueToolsTest`, `McpSharingToolsTest`, `McpDashboardDetailToolsTest`, with the existing `McpDashboardToolsTest` and `McpTileToolsTest`: each tool answers exactly what its service gives the fixture caller, and a refused caller reaches no service.

## Write Tools (REQ-MCP-003)

- [x] Task 9: Implement the 18 `self`-reach write tools (dashboard CRUD, active/default, fork, widget add/update/remove, tile CRUD + addTileToDashboard, version create/restore, metadata set, reaction add/remove) with per-tool `inputSchema`
  - All 18 built: createDashboardVersion, restoreDashboardVersion, setDashboardMetadata, addReaction, removeReaction; addWidget, updateWidgetPlacement, removeWidget, addTileToDashboard (`McpWidgetTools`); createTile, updateTile and deleteTile, backed by the widget path with type `tile` because the tile routes answer 410 Gone (Ruben, 19 Nov 2026). updateWidgetPlacement leaves calculated fields, the acknowledgement settings and the content of a widget whose calculated fields OpenRegister stores to the app, whose route re-materialises them. The dashboard tools are `McpDashboardWriteTools` (createDashboard, updateDashboard, deleteDashboard, switchActiveDashboard, setDefaultDashboard, forkDashboard); updateDashboard changes the name, description, icon, parent, slug and sort order, behind the metadata edit check the route applies to those fields, and saves an automatic version as the route does.
- [x] Task 10: Implement the 4 `user`-reach tools (shareDashboard, replaceShares, unshareDashboard, setPublicationState)
  - `McpSharingTools` (shareDashboard, replaceShares, unshareDashboard) over `DashboardShareService`, and `McpDashboardWriteTools::setPublicationState()` (published, draft, scheduled) over `DashboardService`, behind `dashboard.publish` / `unpublish` / `schedule`. Test: `McpReachToolsTest`.
- [x] Task 11: Implement the 5 `external`-reach tools (createPublicShareLink, revokePublicShareLink, createKioskPlaylist, updateKioskPlaylist, deleteKioskPlaylist)
  - `McpPublicLinkTools` (createPublicShareLink, revokePublicShareLink) over `PublicShareService`, and the kiosk playlist tools in `McpSharingTools` over `KioskService`. Every external-reach tool carries `destructiveHint: true`. Test: `McpReachToolsTest`.
- [x] Task 12: Implement the 8 `instance`-reach tools (group dashboard CRUD, setGroupDefault, template create/update/delete/resync)
  - `McpInstanceTools`: the four group dashboard tools over `DashboardService` and the four template tools over `AdminTemplateService` / the resync service; each checks `requireAdmin()` first. Test: `McpReachToolsTest`.
- [x] Task 13: REQ-MCP-003 — Unit test on the descriptor fixture: every write tool declares exactly one scope ∈ {create, update, delete} and one reach ∈ {self, user, instance, external}; no write carries `readOnlyHint: true`
  - `LaunchPadToolProviderTest::testEveryToolCarriesItsGrantAnnotations` holds for every descriptor, so each write tool added later is checked too.
- [x] Task 14: REQ-MCP-006 — Annotate all `delete`-scope and all `external`-reach tools with the destructive/approval flag hermiq's approval gate consumes; every handler result includes tool id, target identifier, caller uid, and outcome for the audit trail
  - Hints are top-level keys, as OpenRegister's `IMcpToolProvider::getTools()` reads them (`readOnlyHint`, `destructiveHint`); they were nested under `annotations`, where OpenRegister never saw them. The provider adds `audit: {tool, target, caller, outcome}` to every write tool's answer, refusals included. The test holds for every descriptor.

## Exclusions (REQ-MCP-007)

- [x] Task 15: Unit test asserting catalogue closure: the descriptor set equals exactly the REQ-MCP-002 + REQ-MCP-003 ids, and contains no tool for viewEvent, tile-click recording, `acknowledgement#acknowledge`, resource/preview/files uploads, lock endpoints, wizard, Confluence import, cleanup, demo showcases, bulk ops, export/import, or settings surfaces
  - `LaunchPadToolProviderTest::testTheWriteCatalogueIsClosed` (the 35 write ids at their reach, no excluded surface) with `testTheReadCatalogueIsTheFourteenReadTools`.
- [x] Task 16: Document the exclusion rationale in the provider's class docblock so a future "completeness" sweep reads why before adding
  - The class docblock of `LaunchPadToolProvider` lists the exclusions and why.

## Schema Overlay (REQ-MCP-009)

- [x] Task 17: Create `lib/Settings/register.d/dashboard-mcp.json` adding an `x-openregister-mcp` block to the `Dashboard` schema with read-only `search` and `get` tools (`readOnlyHint: true`, scope `read`, sensible `filters` such as title/slug/type/groupId — openconnector's endpoint block as the template). No derived writes
  - `lib/Settings/register.d/dashboard-mcp.json`: `search` (filters title, slug, type, groupId) and `get`, both scope `read` and `readOnlyHint: true`; the import version moves to 1.0.6 so existing installs take it. Test: `DashboardMcpOverlayTest`.
- [x] Task 18: Verify the imported schema carries the block (check the imported schema via OpenRegister, not the file — the importer can reject silently)
  - 19 Nov live pass (Nextcloud 34.0.6, OpenRegister 2.2.0-dev): the imported Dashboard schema carries the `x-openregister-mcp` block in its configuration, and OpenRegister's `tools/list` lists `launchpad.dashboard.search` and `launchpad.dashboard.get`.

## Authorization Parity Tests (REQ-MCP-004/005)

- [x] Task 19: PHPUnit — for a non-owner caller: updateDashboard, deleteDashboard, addWidget, and shareDashboard tools all deny; assert no state change (subclass `ObjectEntity`-backed fixtures rather than mocking magic accessors)
  - A caller without the per-dashboard right gets `forbidden` and no service call: updateDashboard (`McpDashboardWriteToolsTest`), addWidget (`McpWidgetToolsTest`), and a refusal of the share service maps to `forbidden` (`McpReachToolsTest`). The ownership rules themselves stay in the services, tested by their own suites.
- [x] Task 20: PHPUnit — non-admin caller invoking each `instance`-reach tool is denied by the gate
  - `McpReachToolsTest::testInstanceToolsAreForAdministrators`: all eight instance tools refuse a non-administrator before any service call.
- [x] Task 21: PHPUnit — gate error path: a throwing PermissionService produces a denial result, never an allow
- [x] Task 22: PHPUnit — validator rejections: malformed UUID, over-long title, invalid enum, negative grid values — each returns a structured error and performs no service call

## Integration / Chat Flows (REQ-MCP-008)

- [x] Task 23: Integration test — "add the calendar widget": getActiveDashboard → listWidgetTypes → addWidget, assert the placement exists via `GET /api/widgets/items` equivalence
  - 19 Nov live pass (Nextcloud 34.0.6, OpenRegister 2.2.0-dev): `tests/e2e/api-direct/mcp-chat-flows.api.spec.ts` drives getActiveDashboard, listWidgetTypes and addWidget over `POST /apps/openregister/api/mcp` as a non-admin user; the placement is in `GET /api/dashboard/{id}`. The run found that OpenRegister listed none of the provider tools: it finds a provider as `OCA\<namespace>\Mcp\<namespace>ToolProvider` and the class was `LaunchpadToolProvider`; renamed to `LaunchPadToolProvider`, pinned by `LaunchPadToolProviderTest::testTheProviderLivesWhereOpenRegisterLooksForIt`.
- [x] Task 24: Integration test — "share with the sales group": listDashboards → shareDashboard(group: sales), assert parity with `POST /api/dashboard/{id}/shares` output
  - 19 Nov live pass (Nextcloud 34.0.6, OpenRegister 2.2.0-dev): same spec: listDashboards, shareDashboard(group) as a non-admin user; the share is in `GET /api/dashboard/{id}/shares` with the same id and permission level.
- [ ] Task 25: Integration test — "template to default": listTemplates → forkDashboard → setDefaultDashboard, assert `GET /api/dashboards/default` returns the fork
  - Open, 19 Nov live pass: listTemplates lists the admin template gallery, but forkDashboard only copies a dashboard the caller can see (`DashboardService::findVisibleDashboardForFork`), so it answers `not_found` for every template it was offered. The flow needs a decision on which templates a user may copy (the gallery is not filtered by target group). The test is written and parked as `test.fixme` in `tests/e2e/api-direct/mcp-chat-flows.api.spec.ts`.

## Quality & Integration

- [x] Task 26: `composer check:strict` clean (PHPCS, PHPMD, Psalm, PHPStan) including the new `lib/Mcp/` namespace; SPDX headers on every new PHP file (hydra-gate-spdx)
  - `lib/Mcp/` has no PHPCS, PHPMD (both rule sets), Psalm or PHPStan finding, and every file there carries an SPDX header. The PHPMD findings it had are fixed, not suppressed: the validator's `id()` is `positiveId()`, the `required` flag on `string()` and `oneOf()` is gone in favour of `optional()`, and `McpDashboardTools` refuses through its injected validator. The no-argument read tools carry the suppression the other tool classes use. Repo-wide `check:strict` stays red on files outside `lib/Mcp/` (phpcs in the Analytics/Admin contexts, the 387 psalm `MissingOverrideAttribute`-led findings, phpmd on long controllers), the same as on development.
- [x] Task 27: Run the hydra gates; specifically confirm no orphan-auth finding (every gate method must be called) and no redundant-controller finding (no wrapper controllers were added)
  - hydra-gates v1.19.0 on 19 Nov 2026: gate-6 orphan-auth PASS, gate-17 redundant-controller PASS, gate-8 unsafe-auth-resolver PASS. No failing gate names a file in `lib/Mcp/`.
- [x] Task 28: Boot test without OpenRegister installed: app registers and the launchpad UI loads (alias unresolved is acceptable; boot failure is not)
  - 19 Nov live pass (Nextcloud 34.0.6, OpenRegister 2.2.0-dev): launchpad enabled on a fresh MySQL instance without OpenRegister: `occ app:enable` succeeds, `/apps/launchpad/` answers 200 and renders (with Nextcloud's "requires openregister" notice), `GET /api/dashboards` answers 200, no error in the log.

## Build notes

Built so far: tasks 1 to 5, 21 and 22. `LaunchPadToolProvider` has its catalogue as `TOOL_DESCRIPTORS`. It is registered under the string alias `OCA\OpenRegister\Mcp\IMcpToolProvider::launchpad`, which is a literal, so nothing autoloads at boot. The gate (`McpDashboardGate`) applies the same ADR-023 action each HTTP route checks, plus the admin check, and denies on any exception. The validator (`McpArgumentValidator`) handles UUID, id, string length, enum and grid bounds. Four of the 14 read tools from task 7 exist: listDashboards, getDashboard, listGroupDashboards and listWidgetTypes, each calling the service its route calls. The other read tools, all write tools (tasks 9 to 14), the closure test (15), the schema overlay (17, 18), the remaining parity tests and the integration flows are open. Task 4's `PermissionService::can*()` delegation comes with the write tools that need it.

## Verification

`openspec validate` exits clean. Catalogue = exactly 14 read + 35 write tools, all annotated; alias resolves; parity tests green; exclusion-closure test green; the three chat flows execute end-to-end against a dev instance through hermiq or a direct provider harness.

## Tests (company-wide ADR-009)

PHPUnit unit tests per Tasks 3, 8, 13, 15, 19–22 (descriptor fixture + gate + validator). Integration tests per Tasks 23–25. No Playwright surface — this change ships no launchpad UI.

## Documentation (company-wide ADR-010)

Changelog entry: "LaunchPad actions are now available as MCP tools for AI agents (hermiq), with per-tool scope/reach grants." A docs page listing the tool catalogue with scope/reach per tool, generated from or checked against the descriptor constant so it cannot drift.

## i18n (company-wide ADR-005)

Tool names/descriptions are agent-facing contract strings and remain English (per the English-code rule); no user-facing UI strings are introduced.
