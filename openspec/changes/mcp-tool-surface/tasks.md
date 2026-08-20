# Tasks — mcp-tool-surface

## Provider Skeleton

- [ ] Task 1: REQ-MCP-001 — Create `lib/Mcp/LaunchpadToolProvider.php` implementing `OCA\OpenRegister\Mcp\IMcpToolProvider`: dispatcher only, tool catalogue as a `TOOL_DESCRIPTORS` class constant (unit-testable fixture), routing to domain handler classes — mirror `decidesk/lib/Mcp/DecideskToolProvider.php`
- [ ] Task 2: REQ-MCP-001 — Register the string DI alias `'OCA\\OpenRegister\\Mcp\\IMcpToolProvider::launchpad'` in `lib/AppInfo/Application::register()` (string alias, no autoload at registration — decidesk's `DomainServiceRegistrar` pattern, ADR-083 boot safety)
- [ ] Task 3: REQ-MCP-001 — All tool ids namespaced `launchpad.{toolName}`; add a unit test asserting the namespace and id uniqueness across the catalogue

## Gate and Validator

- [ ] Task 4: REQ-MCP-004 — Create `lib/Mcp/McpDashboardGate.php`: `currentUserId()`, `authorise(toolId, args)` delegating to `PermissionService::canViewDashboard()/canEditDashboard()/canAddWidget()/canRemoveWidget()/canCreateDashboard()`, `ActionAuthService::requireAction()` for ADR-023-gated actions, and the admin check for `instance`-reach tools. No `catch (\Throwable) { return null; }` shapes (hydra-gate-unsafe-auth-resolver)
- [ ] Task 5: REQ-MCP-005 — Create `lib/Mcp/McpArgumentValidator.php`: UUID, id, string-length, enum, and grid-bound checks returning structured error arrays (decidesk's validator as the template)
- [ ] Task 6: REQ-MCP-004 — Every handler: validate → authorise → call the existing service (`DashboardService`, `WidgetService`/`WidgetPlacementService`, `TileService`, `DashboardShareService`, `PublicShareService`, `KioskService`, `TemplateService`/`AdminTemplateService`, `ReactionService`, `AcknowledgementService`, `MetadataService`, `DashboardVersionService`). Never controllers, never mappers/ObjectService directly for writes

## Read Tools (REQ-MCP-002)

- [ ] Task 7: Implement the 14 read tools in `lib/Mcp/McpDashboardTools.php` (dashboard reads) and sibling handler classes: listDashboards, getDashboard, getActiveDashboard, getDashboardTree, listGroupDashboards, listWidgetTypes, listTiles, listShares, listVersions, listTemplates, getDashboardMetadata, getReactions, listPendingAcknowledgements, listKioskPlaylists — all `readOnlyHint: true`, scope `read`, reach `self`
- [ ] Task 8: REQ-MCP-002 — Unit test: each read tool's result for a fixture caller equals the visibility the corresponding service call grants that caller (visible-set parity, not superset)

## Write Tools (REQ-MCP-003)

- [ ] Task 9: Implement the 18 `self`-reach write tools (dashboard CRUD, active/default, fork, widget add/update/remove, tile CRUD + addTileToDashboard, version create/restore, metadata set, reaction add/remove) with per-tool `inputSchema`
- [ ] Task 10: Implement the 4 `user`-reach tools (shareDashboard, replaceShares, unshareDashboard, setPublicationState)
- [ ] Task 11: Implement the 5 `external`-reach tools (createPublicShareLink, revokePublicShareLink, createKioskPlaylist, updateKioskPlaylist, deleteKioskPlaylist)
- [ ] Task 12: Implement the 8 `instance`-reach tools (group dashboard CRUD, setGroupDefault, template create/update/delete/resync)
- [ ] Task 13: REQ-MCP-003 — Unit test on the descriptor fixture: every write tool declares exactly one scope ∈ {create, update, delete} and one reach ∈ {self, user, instance, external}; no write carries `readOnlyHint: true`
- [ ] Task 14: REQ-MCP-006 — Annotate all `delete`-scope and all `external`-reach tools with the destructive/approval flag hermiq's approval gate consumes; every handler result includes tool id, target identifier, caller uid, and outcome for the audit trail

## Exclusions (REQ-MCP-007)

- [ ] Task 15: Unit test asserting catalogue closure: the descriptor set equals exactly the REQ-MCP-002 + REQ-MCP-003 ids, and contains no tool for viewEvent, tile-click recording, `acknowledgement#acknowledge`, resource/preview/files uploads, lock endpoints, wizard, Confluence import, cleanup, demo showcases, bulk ops, export/import, or settings surfaces
- [ ] Task 16: Document the exclusion rationale in the provider's class docblock so a future "completeness" sweep reads why before adding

## Schema Overlay (REQ-MCP-009)

- [ ] Task 17: Create `lib/Settings/register.d/dashboard-mcp.json` adding an `x-openregister-mcp` block to the `Dashboard` schema with read-only `search` and `get` tools (`readOnlyHint: true`, scope `read`, sensible `filters` such as title/slug/type/groupId — openconnector's endpoint block as the template). No derived writes
- [ ] Task 18: Verify the imported schema carries the block (check the imported schema via OpenRegister, not the file — the importer can reject silently)

## Authorization Parity Tests (REQ-MCP-004/005)

- [ ] Task 19: PHPUnit — for a non-owner caller: updateDashboard, deleteDashboard, addWidget, and shareDashboard tools all deny; assert no state change (subclass `ObjectEntity`-backed fixtures rather than mocking magic accessors)
- [ ] Task 20: PHPUnit — non-admin caller invoking each `instance`-reach tool is denied by the gate
- [ ] Task 21: PHPUnit — gate error path: a throwing PermissionService produces a denial result, never an allow
- [ ] Task 22: PHPUnit — validator rejections: malformed UUID, over-long title, invalid enum, negative grid values — each returns a structured error and performs no service call

## Integration / Chat Flows (REQ-MCP-008)

- [ ] Task 23: Integration test — "add the calendar widget": getActiveDashboard → listWidgetTypes → addWidget, assert the placement exists via `GET /api/widgets/items` equivalence
- [ ] Task 24: Integration test — "share with the sales group": listDashboards → shareDashboard(group: sales), assert parity with `POST /api/dashboard/{id}/shares` output
- [ ] Task 25: Integration test — "template to default": listTemplates → forkDashboard → setDefaultDashboard, assert `GET /api/dashboards/default` returns the fork

## Quality & Integration

- [ ] Task 26: `composer check:strict` clean (PHPCS, PHPMD, Psalm, PHPStan) including the new `lib/Mcp/` namespace; SPDX headers on every new PHP file (hydra-gate-spdx)
- [ ] Task 27: Run the hydra gates; specifically confirm no orphan-auth finding (every gate method must be called) and no redundant-controller finding (no wrapper controllers were added)
- [ ] Task 28: Boot test without OpenRegister installed: app registers and the launchpad UI loads (alias unresolved is acceptable; boot failure is not)

## Verification

`openspec validate` exits clean. Catalogue = exactly 14 read + 35 write tools, all annotated; alias resolves; parity tests green; exclusion-closure test green; the three chat flows execute end-to-end against a dev instance through hermiq or a direct provider harness.

## Tests (company-wide ADR-009)

PHPUnit unit tests per Tasks 3, 8, 13, 15, 19–22 (descriptor fixture + gate + validator). Integration tests per Tasks 23–25. No Playwright surface — this change ships no launchpad UI.

## Documentation (company-wide ADR-010)

Changelog entry: "LaunchPad actions are now available as MCP tools for AI agents (hermiq), with per-tool scope/reach grants." A docs page listing the tool catalogue with scope/reach per tool, generated from or checked against the descriptor constant so it cannot drift.

## i18n (company-wide ADR-005)

Tool names/descriptions are agent-facing contract strings and remain English (per the English-code rule); no user-facing UI strings are introduced.
