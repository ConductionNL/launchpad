---
kind: config
depends_on: []
chain: []
---

# Compliance and audit dashboard panel

## Why

Specter's compliance survey identified two critical governance dashboard capabilities missing from mydash: (1) alerting officers when compliance deadlines are imminent (45 tender mentions), and (2) filtering governance data by portfolio to surface relevant items (42 tender mentions). Both map to the existing mydash widget convention — manifest-declared, GridStack-rendered, `widgetRegistry` entry, sub-form + renderer, runtime GraphQL for cross-app data. The widget consumes OR's `audit-trail-immutable` and `archival-destruction-workflow` abstractions (ADR-022) plus shillinq's Archiefwet retention rules and docudesk's compliance evidence, surfacing a unified compliance posture view on the dashboard.

mydash MUST NOT add an install-time dependency on OpenRegister or any sibling app — per `feedback_mydash-no-or-dependency.md`. Every data flow is a runtime read; every absent sibling renders an empty-state.

## What Changes

One net-new `mydash_compliance_audit` widget capability, `kind: config` (declarative manifest + capability contract). No new mydash services, no new schemas. Implementation lands as a follow-up chain (manifest entries + Vue components) once this spec is archived.

### New Capabilities

- **mydash-compliance-audit-panel** — compliance posture widget showing BIO/ISO-27001/AVG/NEN-7510 framework status, audit-trail summary, retention disposition counts, and deadline alerts with cross-session persistence. Consumes OR's audit-trail-immutable + archival-destruction-workflow (ADR-022), shillinq's Archiefwet retention rules, and docudesk's compliance evidence via runtime GraphQL. Portfolio filter scopes all queries server-side.

### Modified Capabilities

(none — the existing `widgets` spec stays untouched as the generic widget shell; this spec is a peer, describing one new manifest-declared widget type.)

## Impact

**Affected docs:**

- `openspec/specs/mydash-compliance-audit-panel/spec.md` — new capability spec.
- `openspec/changes/mydash-compliance-audit-panel/` — this proposal + design + tasks.

**Affected code:**

(deferred to per-widget implementation chain) `src/manifest.json` widget registry entry, `src/constants/widgetRegistry.js`, widget sub-form + renderer components, runtime GraphQL clients in `src/services/graphql/`.

**Affected APIs:**

(deferred to per-widget implementation chain) The widget is a **read-only consumer** of existing APIs on sibling apps. No new endpoints on mydash. Runtime GraphQL endpoints consumed: `/graphql` on openregister (audit-trail, archival-destruction) and shillinq (retention rules), docudesk compliance evidence via OR `object-interactions`.

**Dependencies:**

- **No new install-time dependencies.** Per `feedback_mydash-no-or-dependency.md` + ADR-024 §10, mydash's `manifest.dependencies` list MUST NOT include `openregister`, `shillinq`, or `docudesk`. The widget declares a **soft `requires`** in its manifest entry — registers itself, gracefully renders an empty-state when the endpoint is absent at runtime, and never blocks app install.
- npm: no new packages. Apollo Client / urql are already present in `@conduction/nextcloud-vue`.

**Trade-offs:**

- This spec is `kind: config` per ADR-032 and ships as a declarative manifest + capability contract. The actual widget implementation (Vue components, forms, GraphQL clients) is a follow-up chain, keeping each Hydra cycle scoped.
- Per-widget implementation chains land after this change is archived — one chain per widget capability.

## Out of scope

- Implementation code (Vue components, sub-forms, renderers, GraphQL clients) — deferred to implementation chain.
- Schema additions on sibling apps (OR registers, docudesk evidence GraphQL fields, shillinq retention GraphQL fields) — tracked separately per sibling app's openspec.
- New mydash routes or pages — the capability lands as a widget on the existing dashboard surface.
- PWA service-worker or offline-storage implementation.
