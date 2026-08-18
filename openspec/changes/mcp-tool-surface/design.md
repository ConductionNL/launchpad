# Design — LaunchPad MCP Tool Surface

## Context

Product intent: every app action should be automatable by AI under per-agent, default-deny, granularly granted rights (hermiq's scope × reach model), and a user should be able to command the app from chat. LaunchPad's action surface is large (~120 routes) and today entirely invisible to MCP. The design questions were: which mechanism (provider vs schema-derived tools), how to cut ~120 routes into an honest tool catalogue, how to guarantee authorization parity, and what deliberately stays out.

The fleet reference is `decidesk/lib/Mcp/` — `DecideskToolProvider` (dispatcher + `TOOL_DESCRIPTORS` constant), `McpMeetingGate` (centralised authorise(), explicitly no fail-open), `McpArgumentValidator` (typed argument checks returning structured errors), `McpMeetingScopeResolver` (caller scoping) — registered via the string DI alias `OCA\OpenRegister\Mcp\IMcpToolProvider::decidesk`.

## Goals / Non-Goals

**Goals:**

- Full coverage of launchpad's *user* actions: 14 read + 35 write tools, ids `launchpad.{toolName}`.
- Scope (`read`/`create`/`update`/`delete`) × reach (`self`/`user`/`instance`/`external`) annotations on every tool so hermiq can grant per tool, default-deny writes.
- Authorization parity: tools call the same `PermissionService` / `ActionAuthService` guards as the HTTP controllers — one authorization truth.
- Human-approval flags on destructive (`delete`) and boundary-crossing (`external`) tools; audit-ready structured results.

**Non-Goals:**

- Building a chat UI or an agent runtime — hermiq hosts the conversation and the grant/approval/audit machinery.
- Tooling telemetry, binary uploads, editing locks, acknowledgement writes, or one-off admin plumbing (see REQ-MCP-007).
- Replacing `launchpad-ai-dashboard-assistant` — that change renders AI summaries *on* the canvas; this one puts the canvas's actions *under* AI control. No shared code.

## Decisions

### D1: Imperative provider for all writes; schema-derived tools for read discovery only

**Decision**: All writes go through `LaunchpadToolProvider` handlers calling the existing service layer. The `Dashboard` schema additionally gets an `x-openregister-mcp` block (openconnector pattern) deriving only read-only `search`/`get`.

**Alternatives considered:**

- **Schema-derived CRUD for everything** (`x-openregister-mcp` with writes enabled): rejected. Dashboard mutations are not raw object writes — `DashboardService::createDashboard()` generates slugs, `updateDashboard()` respects locks and permission levels, widget placement runs collision handling (`PlacementService`), deletes cascade. Auto-derived writes would bypass every one of those invariants.
- **Provider-only, no schema block**: workable, but the two read paths cost one small overlay and give agents uniform object discovery across the fleet. Kept.

**Rationale**: decidesk proved the provider shape; openconnector proved read-only schema blocks. LaunchPad needs both halves for its split of "rich service invariants on writes" vs "plain object reads".

### D2: One tool per user action, not per route

**Decision**: The catalogue aggregates exactly where the HTTP surface aggregates: `updateDashboard` covers rename/description/slug (as `dashboardApi#update` does), `updateWidgetPlacement` covers move/resize/restyle/reconfigure (as `widgetApi#updatePlacement` does), `setPublicationState` covers publish/unpublish/schedule (three routes, one user intent).

**Alternatives considered:**

- **One tool per route (~120 tools)**: rejected — floods the model's tool context, and route-level granularity (e.g. separate publish/unpublish tools) adds grant-matrix rows without adding a meaningfully different permission decision.
- **A handful of coarse tools ("manageDashboard")**: rejected — coarse tools break the grant model; hermiq cannot grant "reposition widgets but never delete dashboards" if both hide inside one tool.

**Rationale**: the grant matrix is the sizing instrument: two actions belong in one tool iff a rights-granter would never want to split them. Rename-vs-describe: never split. Add-widget vs delete-dashboard: always split.

### D3: Reach classification — kiosk and public links are `external`, sharing is `user`, group/template management is `instance`

**Decision**: `createPublicShareLink`, `revokePublicShareLink`, and all three kiosk playlist writes carry reach `external`; `shareDashboard`/`replaceShares`/`unshareDashboard`/`setPublicationState` carry `user`; group-dashboard CRUD, `setGroupDefault`, and template CRUD/resync carry `instance`.

**Alternatives considered:**

- **Kiosk as `self`** ("it's the caller's playlist"): rejected — a kiosk playlist mints a `#[PublicPage]` token (`kiosk#render`, `/kiosk/{token}`) that serves dashboards to anonymous viewers. What a write *exposes* determines reach, not where the record lives. Same logic as public share links.

**Rationale**: reach must encode blast radius as the rights-granter experiences it. Anything that changes who-can-see across the auth boundary is `external`; across users inside the instance is `user`; instance topology is `instance`.

### D4: Authorization is a gate class delegating to the existing guards — never re-implemented

**Decision**: `McpDashboardGate` wraps `PermissionService::can*()`, `ActionAuthService::requireAction()` (ADR-023), and the admin check; every handler consults it first. Errors deny (no `catch (\Throwable) → null`, per the unsafe-auth-resolver gate). Handlers then call `DashboardService` / `WidgetService` / `TileService` / `DashboardShareService` / `PublicShareService` / `KioskService` / `TemplateService` — never controllers (no HTTP self-calls), never mappers or `ObjectService` directly for writes.

**Rationale**: The permission logic already exists, is audited, and drifts if duplicated. decidesk's `McpMeetingGate` documents the same invariants (guards return bool, no unconditional true, no Throwable-swallowing) — codified here as spec scenarios.

### D5: Exclusions are specified, not implied

**Decision**: REQ-MCP-007 names every real endpoint family that gets no tool and why — telemetry recorders (agents must not fabricate analytics), acknowledgement writes (compliance evidence of a *human* act), binary uploads (not chat-shaped), editing locks (interactive-session mechanics), one-off admin plumbing (wizard, imports, cleanup, bulk ops, settings surfaces).

**Rationale**: an unlisted omission looks like an oversight and invites a future "completeness" PR to tool the acknowledgement endpoint — the one addition that would be actively harmful. The exclusion list makes the boundary a tested assertion (catalogue-closure scenario) instead of folklore.

## Risks / Trade-offs

- **Catalogue size (49 tools)**: large for a single provider. Mitigated by domain-grouped handler classes and the descriptor constant being a plain fixture unit tests assert against (decidesk pattern). If hermiq context budgets demand it, read tools can later be collapsed behind the schema-derived `search`/`get` without touching write semantics.
- **Grant-model drift**: scope/reach vocabulary is hermiq's; if hermiq's axes change, annotations must follow. The descriptors keep annotations as data, so this is a constant edit, not a refactor.
- **Instance-reach tools in agent hands**: template and group-dashboard tools are powerful. Defence in depth: admin gate in `McpDashboardGate` + `instance` reach requiring an explicit hermiq grant + approval gate on deletes.

## Migration Plan

Additive only: new `lib/Mcp/` classes, one DI alias line, one register.d overlay. No routes, no schema data changes. Rollback = remove the alias registration (tools disappear from the catalogue) — no data cleanup needed.

## Open Questions

- Whether hermiq wants pagination cursors on `listDashboards`/`listTemplates` results or is satisfied with the endpoint's existing limits — decide against hermiq's consumer contract during implementation.
- Whether `launchpad.forkDashboard` should accept a template *gallery id* directly or only dashboard uuids (gallery entries are dashboards, so uuid-only is likely sufficient) — confirm against `template#gallery`'s payload shape.
