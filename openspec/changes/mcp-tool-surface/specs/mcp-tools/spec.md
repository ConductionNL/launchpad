---
capability: mcp-tools
delta: false
status: draft
---

# LaunchPad MCP Tool Surface

## Context

LaunchPad exposes its user actions as MCP tools so agents (hermiq) can execute them under per-agent, default-deny grants, and so users can command the app from chat. The provider mechanism follows the fleet reference implementation in `decidesk/lib/Mcp/` (`DecideskToolProvider`, `McpMeetingGate`, `McpArgumentValidator`, `McpMeetingScopeResolver`); the catalogue below is derived from launchpad's real route/service surface (`appinfo/routes.php`, `DashboardService`, `WidgetService`, `TileService`, `DashboardShareService`, `PublicShareService`, `TemplateService`, `KioskService`, `ReactionService`, `AcknowledgementService`). LaunchPad currently has no MCP surface at all (verified: zero `IMcpToolProvider` / `x-openregister-mcp` hits in the repo).

Write tools are annotated on hermiq's two grant axes:

- **scope** — what kind of mutation: `create` / `update` / `delete` (reads carry `read`).
- **reach** — whose world it touches: `self` (the caller's own dashboards/objects), `user` (grants or removes other users' access), `instance` (instance-wide: group dashboards, admin templates), `external` (crosses the authentication boundary: public links, kiosk tokens).

## ADDED Requirements

### Requirement: REQ-MCP-001 Provider class, DI alias, and tool id namespace

LaunchPad MUST implement `OCA\OpenRegister\Mcp\IMcpToolProvider` in a new class `OCA\LaunchPad\Mcp\LaunchpadToolProvider` (dispatcher + catalogue constant, handlers in domain-grouped classes, per the decidesk layout). It MUST be registered in `lib/AppInfo/Application.php` under the string DI alias `OCA\OpenRegister\Mcp\IMcpToolProvider::launchpad` (string alias so registration triggers no autoload and the app boots without OpenRegister, matching decidesk's `IMcpToolProvider::decidesk` registration). Every tool id MUST use the `launchpad.{toolName}` namespace.

#### Scenario: Provider resolves through the alias

- **GIVEN** an instance with OpenRegister and launchpad enabled
- **WHEN** the container resolves `OCA\OpenRegister\Mcp\IMcpToolProvider::launchpad`
- **THEN** it MUST return a `LaunchpadToolProvider` instance
- **AND** every id in its catalogue MUST start with `launchpad.`

#### Scenario: Boot without OpenRegister

- **GIVEN** an instance where OpenRegister is not installed
- **WHEN** launchpad boots
- **THEN** app registration MUST complete without error — the alias fails only if something asks for it

### Requirement: REQ-MCP-002 Read tool catalogue (full read coverage)

The provider MUST expose the following read tools, each with `readOnlyHint: true` and scope `read`, reach `self` (each returns only what the caller may already see through the corresponding endpoint's guards):

| Tool id | Backs |
|---|---|
| `launchpad.listDashboards` | `dashboardApi#visible` / `DashboardService::getVisibleToUser()` |
| `launchpad.getDashboard` | `dashboardApi#show` / `#resolved` |
| `launchpad.getActiveDashboard` | `dashboardApi#getActive` / `DashboardService::resolveActiveDashboard()` |
| `launchpad.getDashboardTree` | `dashboardApi#tree` |
| `launchpad.listGroupDashboards` | `dashboardApi#listGroup` |
| `launchpad.listWidgetTypes` | `widgetApi#listAvailable` |
| `launchpad.listTiles` | `tileApi#index` |
| `launchpad.listShares` | `dashboardShareApi#index` |
| `launchpad.listVersions` | `dashboardVersionApi#listVersions` |
| `launchpad.listTemplates` | `template#gallery` |
| `launchpad.getDashboardMetadata` | `dashboardMetadata#getMetadata` |
| `launchpad.getReactions` | `dashboardReactionApi#getReactions` |
| `launchpad.listPendingAcknowledgements` | `acknowledgement#pending` |
| `launchpad.listKioskPlaylists` | `kiosk#index` |

#### Scenario: Read tools are marked read-only

- **GIVEN** the provider's catalogue
- **WHEN** hermiq inspects the 14 read tools
- **THEN** each MUST carry `readOnlyHint: true` and scope `read`
- **AND** none of them MUST mutate any state when invoked

#### Scenario: Reads return only the caller's visible set

- **GIVEN** a caller who can see dashboards A and B but not C
- **WHEN** `launchpad.listDashboards` is invoked on their behalf
- **THEN** the result MUST contain A and B and MUST NOT contain C — identical to `GET /api/dashboards/visible`

### Requirement: REQ-MCP-003 Write tool catalogue with scope × reach annotations (full action coverage)

The provider MUST expose the following write tools — one tool per user action, aggregated exactly as the existing endpoints aggregate them (e.g. rename/describe/re-slug are all `updateDashboard`, matching `dashboardApi#update`; move/resize/style are all `updateWidgetPlacement`, matching `widgetApi#updatePlacement`). Each write tool MUST declare the scope and reach listed:

**Reach `self` — the caller's own dashboards:**

| Tool id | Backs | Scope |
|---|---|---|
| `launchpad.createDashboard` | `dashboardApi#create` / `DashboardService::createDashboard()` | create |
| `launchpad.updateDashboard` | `dashboardApi#update` / `DashboardService::updateDashboard()` | update |
| `launchpad.deleteDashboard` | `dashboardApi#delete` / `DashboardService::deleteDashboard()` | delete |
| `launchpad.switchActiveDashboard` | `dashboardApi#setActiveDashboard` / `#activate` | update |
| `launchpad.setDefaultDashboard` | `dashboardApi#setDefaultDashboard` / `DashboardService::setDefaultPreference()` | update |
| `launchpad.forkDashboard` | `dashboardApi#fork` / `DashboardService::forkAsPersonal()` — also the "use a gallery template" path | create |
| `launchpad.addWidget` | `widgetApi#addWidget` | create |
| `launchpad.updateWidgetPlacement` | `widgetApi#updatePlacement` | update |
| `launchpad.removeWidget` | `widgetApi#removePlacement` | delete |
| `launchpad.addTileToDashboard` | `widgetApi#addTile` | create |
| `launchpad.createTile` | `tileApi#create` | create |
| `launchpad.updateTile` | `tileApi#update` | update |
| `launchpad.deleteTile` | `tileApi#destroy` | delete |
| `launchpad.createDashboardVersion` | `dashboardVersionApi#createVersion` | create |
| `launchpad.restoreDashboardVersion` | `dashboardVersionApi#restoreVersion` | update |
| `launchpad.setDashboardMetadata` | `dashboardMetadata#setMetadata` | update |
| `launchpad.addReaction` | `dashboardReactionApi#addReaction` | create |
| `launchpad.removeReaction` | `dashboardReactionApi#removeReaction` | delete |

**Reach `user` — changes other users' access:**

| Tool id | Backs | Scope |
|---|---|---|
| `launchpad.shareDashboard` | `dashboardShareApi#create` | create |
| `launchpad.replaceShares` | `dashboardShareApi#replace` | update |
| `launchpad.unshareDashboard` | `dashboardShareApi#destroy` | delete |
| `launchpad.setPublicationState` | `dashboardApi#publish` / `#unpublish` / `#schedule` | update |

**Reach `external` — mints or revokes anonymous access:**

| Tool id | Backs | Scope |
|---|---|---|
| `launchpad.createPublicShareLink` | `publicShare#create` | create |
| `launchpad.revokePublicShareLink` | `publicShare#destroy` | delete |
| `launchpad.createKioskPlaylist` | `kiosk#create` | create |
| `launchpad.updateKioskPlaylist` | `kiosk#update` | update |
| `launchpad.deleteKioskPlaylist` | `kiosk#destroy` | delete |

**Reach `instance` — instance-wide (group dashboards, admin templates):**

| Tool id | Backs | Scope |
|---|---|---|
| `launchpad.createGroupDashboard` | `dashboardApi#createGroup` / `DashboardService::createGroupShared()` | create |
| `launchpad.updateGroupDashboard` | `dashboardApi#updateGroup` | update |
| `launchpad.deleteGroupDashboard` | `dashboardApi#deleteGroup` | delete |
| `launchpad.setGroupDefault` | `dashboardApi#setGroupDefault` | update |
| `launchpad.createTemplate` | `admin#createTemplate` / `AdminTemplateService` | create |
| `launchpad.updateTemplate` | `admin#updateTemplate` | update |
| `launchpad.deleteTemplate` | `admin#deleteTemplate` | delete |
| `launchpad.resyncTemplate` | `admin#resyncTemplate` / `TemplateResyncService` | update |

#### Scenario: Every write tool carries both axes

- **GIVEN** the provider's catalogue
- **WHEN** hermiq inspects any non-read tool
- **THEN** it MUST find a scope of `create`, `update`, or `delete` AND a reach of `self`, `user`, `instance`, or `external`
- **AND** no write tool MUST carry `readOnlyHint: true`

#### Scenario: Writes are default-deny in hermiq

- **GIVEN** an agent with no explicit grants for launchpad
- **WHEN** it attempts `launchpad.deleteDashboard`
- **THEN** hermiq MUST refuse before the tool executes — the tool's annotations exist so this decision is per-tool, not app-wide

#### Scenario: External-reach tools are the narrowest grant

- **GIVEN** an agent granted `create/self` and `update/self` for launchpad
- **WHEN** it attempts `launchpad.createPublicShareLink`
- **THEN** the call MUST be refused — `external` reach requires its own explicit grant

### Requirement: REQ-MCP-004 Authorization parity with the HTTP surface

Every tool handler MUST enforce the exact same authorization as the corresponding controller path, by calling the same service guards: `PermissionService::canViewDashboard()` / `canEditDashboard()` / `canAddWidget()` / `canRemoveWidget()` / `canCreateDashboard()`, `ActionAuthService::requireAction()` for ADR-023-gated actions, and the admin requirement for `instance`-reach tools. This MUST be centralised in a `McpDashboardGate` class (mirroring decidesk's `McpMeetingGate`: authorise() returning an allow/deny result, no `catch (\Throwable) { return null; }` fail-open shapes). A tool MUST NOT reach `ObjectService` or mappers directly for writes — all mutations go through the existing service layer so slug generation, lock checks, placement collision handling, and cascade behaviour hold.

#### Scenario: Tool denial equals endpoint denial

- **GIVEN** a caller for whom `PUT /api/dashboard/{id}` would return a permission error
- **WHEN** the same caller's agent invokes `launchpad.updateDashboard` for that dashboard
- **THEN** the tool MUST deny with a structured error
- **AND** no partial mutation MUST occur

#### Scenario: Admin tools require admin

- **GIVEN** a non-admin caller
- **WHEN** their agent invokes `launchpad.createTemplate`
- **THEN** the gate MUST deny exactly as `admin#createTemplate` does for that caller

#### Scenario: No fail-open on gate errors

- **GIVEN** the authorization service throws during a tool call
- **WHEN** the gate evaluates the request
- **THEN** the result MUST be a denial, never a silent allow

### Requirement: REQ-MCP-005 Argument validation before any service call

Every tool MUST validate its arguments against its declared `inputSchema` before touching a service, via a `McpArgumentValidator` (decidesk pattern: typed UUID/date/string-length checks returning a structured error array on failure). Invalid arguments MUST produce a validation error result, never an exception escaping the provider and never a service call with coerced values.

#### Scenario: Malformed UUID is rejected

- **GIVEN** an agent invokes `launchpad.getDashboard` with `dashboardUuid: "../../etc"`
- **WHEN** the validator runs
- **THEN** the tool MUST return a validation error without any service or database access

#### Scenario: Out-of-range grid values are rejected

- **GIVEN** `launchpad.updateWidgetPlacement` is invoked with a negative `gridWidth`
- **WHEN** the validator runs
- **THEN** the tool MUST return a validation error naming the offending field

### Requirement: REQ-MCP-006 Destructive and external tools are flagged for human approval and audited

Tools with scope `delete`, and all tools with reach `external`, MUST carry a destructive/approval annotation so hermiq inserts its human approval gate before execution. Every write tool invocation (allowed or denied) MUST be auditable: the provider returns enough structured context (tool id, target uuid/id, caller uid, outcome) for hermiq's audit trail to record the action.

#### Scenario: Delete requires approval

- **GIVEN** a user tells the chat "delete my old sales dashboard"
- **WHEN** the agent resolves this to `launchpad.deleteDashboard`
- **THEN** hermiq MUST present a human approval step before the tool runs
- **AND** the approval and execution MUST both land in the audit trail

#### Scenario: Public link requires approval

- **GIVEN** an agent invokes `launchpad.createPublicShareLink`
- **WHEN** hermiq evaluates the call
- **THEN** the external-reach annotation MUST trigger the approval gate even if the agent holds a `create/external` grant

### Requirement: REQ-MCP-007 Deliberate exclusions from the tool surface

The following real endpoints MUST NOT get tools, and the catalogue MUST stay closed against them without an explicit spec change:

- **Telemetry recorders** — `dashboardApi#viewEvent`, `tileAnalytics#recordClick`: an agent invoking these fabricates analytics.
- **Acknowledgements (write)** — `acknowledgement#acknowledge`: a mandatory-read acknowledgement is compliance evidence of a *human* act; an agent must never acknowledge on a user's behalf. (The read side, `listPendingAcknowledgements`, is exposed.)
- **Binary upload endpoints** — `resource#upload`/`#uploadMultipart`, `admin#uploadTemplatePreviewImage`, `filesWidget#upload`: multipart/base64 payloads are not chat-tool shaped; revisit if a file-reference contract lands.
- **Editing locks** — `dashboardLockApi#*`: locks serialize *interactive* editing sessions; tool calls are single atomic service operations and must not hold or break user locks.
- **One-off admin plumbing** — setup wizard, Confluence import, orphaned-data cleanup, demo showcases, bulk operations, export/import, role/metadata-field/org-navigation/footer/settings administration: high blast radius, no chat use case; deliberately out of v1.

#### Scenario: Catalogue contains no excluded tool

- **GIVEN** the provider's catalogue
- **WHEN** its ids are compared against the exclusion list
- **THEN** no tool MUST exist for any excluded endpoint
- **AND** the catalogue MUST contain exactly the tools of REQ-MCP-002 and REQ-MCP-003

### Requirement: REQ-MCP-008 Chat command flows resolve to tool sequences

The catalogue MUST be sufficient for hermiq to execute common chat commands as short tool sequences using only declared tools, with all resolution (name → uuid, widget label → widgetKey) done via read tools first.

#### Scenario: "Add the calendar widget to my dashboard"

- **GIVEN** a user with an active personal dashboard and an agent granted `read/self` and `create/self`
- **WHEN** the user sends "add the calendar widget to my dashboard"
- **THEN** the agent MUST be able to resolve it as `launchpad.getActiveDashboard` → `launchpad.listWidgetTypes` (find the calendar widgetKey) → `launchpad.addWidget`
- **AND** the widget MUST appear on the dashboard exactly as if added through the UI

#### Scenario: "Share my team dashboard with the sales group"

- **GIVEN** an agent granted `read/self` and `create/user`
- **WHEN** the user sends "share my team dashboard with the sales group"
- **THEN** the agent MUST resolve the dashboard via `launchpad.listDashboards`, then invoke `launchpad.shareDashboard` with a group share for `sales`
- **AND** the share MUST be identical to one created via `POST /api/dashboard/{id}/shares`

#### Scenario: "Start me a dashboard from the sprint template and make it my default"

- **GIVEN** an agent granted `read/self`, `create/self`, and `update/self`
- **WHEN** the user sends the command
- **THEN** the agent MUST resolve it as `launchpad.listTemplates` → `launchpad.forkDashboard` → `launchpad.setDefaultDashboard`
- **AND** the resulting dashboard MUST be the caller's default landing dashboard

### Requirement: REQ-MCP-009 Read-only x-openregister-mcp block on the Dashboard schema

A register.d overlay MUST declare `x-openregister-mcp` on the `Dashboard` schema with auto-derived **read** tools only (`search`, `get` with `readOnlyHint: true`, openconnector pattern). Auto-derived CRUD writes MUST NOT be enabled: dashboard mutations bypass `DashboardService` invariants (slug generation, permission levels, lock checks) if written as raw objects, so all writes stay with the provider tools of REQ-MCP-003.

#### Scenario: Schema-derived tools are read-only

- **GIVEN** the imported Dashboard schema
- **WHEN** OpenRegister derives MCP tools from its `x-openregister-mcp` block
- **THEN** only `search` and `get` MUST be derived, both read-only
- **AND** no derived create/update/delete tool MUST exist for the Dashboard schema
