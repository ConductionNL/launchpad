# MCP tool surface — every launchpad action commandable from chat

## Why

The product direction is that every Conduction app exposes its actions as MCP tools, so any action can in principle be automated by an AI agent — and so a user can simply chat while their apps execute their commands: "add the calendar widget to my dashboard", "share the sprint dashboard with the sales group". hermiq consumes these tools under its two-axis **scope × reach** grant model: rights are granted per agent, granularly, with writes denied by default, human approval gates on destructive/external actions, and a full audit trail.

LaunchPad currently exposes **nothing** to that surface. Verified by grep: no `OCA\Launchpad\Mcp` namespace, no `IMcpToolProvider` implementation, no `x-openregister-mcp` block anywhere in `lib/`, `appinfo/`, or `src/` (zero hits). Meanwhile the app has one of the richest user-action surfaces in the fleet — `appinfo/routes.php` registers ~120 routes over dashboards, widgets, tiles, sharing, publication, versioning, templates, locks, and admin operations.

The fleet mechanism is proven: decidesk ships the reference implementation (`decidesk/lib/Mcp/` — `DecideskToolProvider` implementing `OCA\OpenRegister\Mcp\IMcpToolProvider`, registered under the DI alias `OCA\OpenRegister\Mcp\IMcpToolProvider::decidesk`, tool ids `decidesk.{toolName}`, with `McpMeetingGate` for authorization, `McpArgumentValidator` for input validation, and `McpMeetingScopeResolver` for caller scoping), and openconnector demonstrates schema-level `x-openregister-mcp` blocks for auto-derived read tools. This change gives launchpad the same surface, sized to its real action inventory.

**Relation to `launchpad-ai-dashboard-assistant`**: that change points AI *at* the dashboard — AI-generated summary widgets rendered on the canvas. This change points AI at launchpad's *actions* — the dashboard becomes something an agent can build, rearrange, and share on the user's behalf. They are complementary and share no code; where the assistant change's widgets need data, they read it through widget endpoints, not through these tools.

## What Changes

- New `lib/Mcp/` namespace with `LaunchpadToolProvider` implementing `OCA\OpenRegister\Mcp\IMcpToolProvider`, registered in `AppInfo\Application` under the DI alias `OCA\OpenRegister\Mcp\IMcpToolProvider::launchpad`. Tool ids are `launchpad.{toolName}`.
- A full-coverage tool catalogue derived from the real controller/service surface (see spec REQ-MCP-002/003): 14 read tools and 35 write tools covering dashboard CRUD, active/default selection, forking, widget add/reposition/remove, tile CRUD, sharing, publication state, public links, versioning, metadata, group dashboards, and admin template management.
- Read tools carry `readOnlyHint: true`; every write tool is annotated with **scope** (`create` / `update` / `delete`) and **reach** (`self` / `user` / `instance` / `external`) so hermiq's grant matrix can authorize each tool individually, default-deny.
- Authorization parity: every tool routes through the exact same guards as the HTTP surface — `PermissionService::can*()`, `ActionAuthService::requireAction()` (ADR-023 matrix), and the admin checks — via a `McpDashboardGate` mirroring decidesk's `McpMeetingGate`. Argument validation via a `McpArgumentValidator` counterpart.
- A read-only `x-openregister-mcp` block (`search`/`get`, openconnector pattern) on the `Dashboard` schema for discovery parity; auto-derived CRUD **writes stay disabled** because dashboard mutations must pass through `DashboardService` invariants (slug generation, lock checks, permission levels), never raw object writes.

## Capabilities

### New Capabilities

- `mcp-tools` — launchpad's MCP tool surface: catalogue, scope/reach annotations, authorization parity, and chat-command flows.

### Modified Capabilities

(none)

## Impact

**Affected code:**

- `lib/Mcp/LaunchpadToolProvider.php` — dispatcher + tool catalogue (new)
- `lib/Mcp/McpDashboardTools.php`, `lib/Mcp/McpWidgetTools.php`, `lib/Mcp/McpSharingTools.php`, `lib/Mcp/McpAdminTools.php` — handlers grouped by domain (new)
- `lib/Mcp/McpDashboardGate.php`, `lib/Mcp/McpArgumentValidator.php` — authorization + validation (new)
- `lib/AppInfo/Application.php` — DI alias registration (string alias, no autoload at boot, per the ADR-083-safe pattern decidesk uses)
- `lib/Settings/register.d/dashboard-mcp.json` — read-only `x-openregister-mcp` overlay (new)

**Affected APIs:**

- No new HTTP routes. Tools call the existing service layer (`DashboardService`, `WidgetService`/`WidgetPlacementService`, `TileService`, `DashboardShareService`, `PublicShareService`, `TemplateService`) — never controllers, never raw ObjectService writes.

**Dependencies:**

- OpenRegister providing `OCA\OpenRegister\Mcp\IMcpToolProvider`; hermiq as the consuming agent host. Without OpenRegister the alias resolves lazily and fails only when asked for (decidesk pattern), so launchpad still boots.

**Migration:**

- None. Additive surface; no schema or data changes beyond the read-only MCP overlay.

## Notes

- Deliberately excluded from the tool surface (REQ-MCP-007): telemetry recorders (view events, tile clicks — agents must not fabricate analytics), binary upload endpoints (resources, template preview images — chat tools take JSON, not multipart), the setup wizard, Confluence import, orphaned-data cleanup, and demo showcases (one-off admin plumbing, high blast radius, no chat use case).
- Sizing note: unlike decidesk's 5-tool exemplar, launchpad's honest action inventory is large. The catalogue is still one-tool-per-user-action, not per-route: e.g. rename/describe/re-slug are all `launchpad.updateDashboard`; move/resize/style are all `launchpad.updateWidgetPlacement` — matching how `dashboardApi#update` and `widgetApi#updatePlacement` already aggregate them.
