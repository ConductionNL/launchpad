# Design: mydash-compliance-audit-panel

## Context

Specter's intelligence pass for mydash surfaced two discrete compliance-governance capabilities with high demand: imminent deadline alerts (45 tender mentions) and portfolio-scoped filtering (42 tender mentions). The context brief aggregates these into one widget spec: `mydash-compliance-audit-panel`, following the existing mydash widget convention.

mydash's existing `openspec/specs/` contains 56 capability specs covering the dashboard data model, 17 built-in widget types, NC integration surfaces, and admin features. What's **missing** is a compliance-governance widget that surfaces organization-wide compliance posture (frameworks, audit events, retention status, deadlines) sourced from sibling apps via runtime GraphQL.

## Reuse Analysis

The compliance-audit widget composes existing capabilities — no new mydash infrastructure:

| Capability | Reuses (mydash) | Consumes (cross-app, runtime only) |
|---|---|---|
| `mydash-compliance-audit-panel` | `widgets`, `widget-add-edit-modal`, `dashboards`, `permissions`, `conditional-visibility` | OR audit-trail-immutable + archival-destruction-workflow (ADR-022), shillinq retention-rule GraphQL, docudesk compliance evidence via OR `object-interactions` |

The OpenRegister abstractions consumed (ADR-022 column "Abstraction"):

- **audit trail (immutable)** — surfaces event summary (types + counts + latest timestamp)
- **archival + destruction workflow** — surfaces retention disposition counts (pending-review, destroy-eligible, archived, destroyed)
- **integration registry (ADR-019)** — discovers compliance evidence files linked via OR `object-interactions`

The `@conduction/nextcloud-vue` shared library provides the runtime GraphQL client + every UI primitive the widget renders (`CnDetailCard`, `CnDetailGrid`, `CnStatsBlock` per `feedback_vue-logic-in-ncvue.md`). No mydash-local GraphQL plumbing, no mydash-local data tables.

## Declarative-vs-imperative decision

Per ADR-031, every requirement in this change lands declaratively:

- **No mydash service classes.** mydash already follows ADR-022 (no install-time OR dep), so it cannot have lifecycle / aggregation services over OR objects — every data surface is a runtime read.
- **Manifest entry** for the widget (`src/manifest.json` `widgets[]` per ADR-024) — declarative.
- **`widgetRegistry.js` entry** per spec (`{ component, label, defaults, requires }`) — declarative.
- **Per-widget content JSON shape** persisted on `oc_mydash_widget_placements.content` — declarative.
- **Runtime queries** are GraphQL operation strings inside the per-widget Vue components — declarative artefacts, no PHP service.

The only PHP surface is mydash's existing `WidgetService` (already shipped, no change), and the existing API routes that serve widget items. No new controllers, no new entities, no new mappers.

`kind: config` per ADR-032 — the entire change is JSON / spec text. Implementation chains for the widget will be `kind: code` when they land (Vue components + manifest JSON edits), but per ADR-032 the spec change ships first, then the implementation chain follows.

## Seed Data

No seed data — mydash holds no OR-backed schemas; this widget is a read-only consumer. The sibling apps (shillinq, docudesk, OpenRegister) already carry their own seed data per ADR-016. For local screenshot/QA purposes, the demo dashboard bundle (`demo-data-showcases` spec) will gain a "compliance audit demo" dashboard in the per-widget implementation chain, not here.

## Cross-app data contract

The widget MUST follow the same runtime-only contract documented in `feedback_mydash-no-or-dependency.md`:

1. The widget's manifest entry declares its data sources under `widgets[<id>].requires` as soft requirements — never hard dependencies in `manifest.dependencies`.
2. The renderer issues GraphQL queries to sibling apps' OR-mounted `/graphql` endpoints (or to Nextcloud native APIs).
3. If an endpoint returns 404 or an empty result, the widget MUST render its empty-state without throwing. The widget MUST NOT show "this app is required" — mydash is the always-available shell and the absence of a sibling app is a normal runtime state.
4. The widget MUST NOT cache cross-app data beyond the Vue component's own lifetime. Persistent caching is the sibling app's responsibility.
5. Authorization is enforced by the sibling app's RBAC, not mydash — mydash receives only what the viewer is allowed to see.

## Spec-sizing decision (ADR-032)

This change is `kind: config` end-to-end:

- Files touched: 1 × `specs/mydash-compliance-audit-panel/spec.md` + `proposal.md` + `design.md` + `tasks.md` — all markdown.
- No `.php`, `.vue`, `.ts`, `.js` files in scope.
- Per-widget implementation lands as a follow-up chain, where chain spec 1 is the manifest + registry entry (`kind: config`, small), chain spec 2 is the renderer + sub-form (`kind: code`, small-medium), and chain spec 3 (if needed) wires the runtime GraphQL client (`kind: code`, small).

## Mixed-spec rationale

N/A — this change is pure `kind: config`. No thin-glue code edits.

## Alternatives considered

1. **Split deadline alerts and portfolio filters into two separate widget types.**
   Rejected. Both operate over the same data domain (compliance frameworks, audit events, retention status) and share the same GraphQL queries. Splitting them would create two small widget types that duplicate query/empty-state/filter logic, violating DRY. Combining them as one widget with configurable card visibility (showAuditTrailCard, showRetentionCard, showEvidenceCard per REQ-CAP-002) keeps the queries unified while allowing orgs to customize their dashboard surface.

2. **Include framework definition + compliance metadata in mydash.**
   Rejected. mydash's role is to be a shell that composes cross-app data, not to own compliance domain knowledge. Framework definitions (BIO, ISO-27001, AVG, NEN-7510) and compliance evidence metadata live on the compliance sibling app; mydash discovers them at runtime and renders them declaratively. This keeps mydash clean and avoids the install-time dependency anti-pattern.

3. **Build a local audit table on mydash instead of consuming OR's audit-trail-immutable abstraction.**
   Rejected. Violates ADR-022 anti-pattern: "Home-grown audit trails". mydash is a shell; it reads the audit trail from OR via GraphQL, never writes to it locally. This keeps the audit trail authoritative and audit events synchronized across all apps that touch the same objects.

## Dependencies

The widget depends on:

- `widgets` — the generic widget shell and placement mechanism
- `widget-add-edit-modal` — the standard Add Widget modal (widget discovery + configuration)
- `dashboards` — the dashboard data model and storage
- `permissions` — widget-level visibility and data scope (who sees this widget on whose dashboard)
- `conditional-visibility` — role-based widget visibility on the dashboard

Cross-app runtime dependencies (soft):

- OpenRegister (audit-trail-immutable, archival-destruction-workflow, GraphQL) — for audit events and retention status
- shillinq (retention rules GraphQL) — for Archiefwet compliance data
- docudesk (compliance evidence) — for linked documents via OR object-interactions

## Migration

N/A — this is a new widget with no prior state to migrate.
