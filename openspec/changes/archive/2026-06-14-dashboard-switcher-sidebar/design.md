---
status: pr-created
---

# Design — dashboard-switcher-sidebar

## Context

`multi-scope-dashboards` introduced three dashboard sources (`user`, `group`, `default`) plus a `source` discriminator on every visible-dashboard row. The existing topbar select could not scale to that many entries and had no room to host the `allow_user_dashboards`-gated create/delete affordances. A dedicated slide-in sidebar was designed to:

1. Group dashboards into three labelled sections in a fixed order (primary group → default group → personal).
2. Route switch clicks to the correct API endpoint via the `source` discriminator emitted alongside the dashboard id.
3. Isolate animation, section logic, and personal-only affordances from the runtime shell.

## Decisions

### D1: Sidebar in `Views.vue`, not `WorkspaceApp.vue`

**Decision**: `Views.vue` owns the sole `DashboardSwitcherSidebar` mount. `WorkspaceApp.vue` deliberately has no mount (PR #114 confirmed this).

**Rationale**: `Views.vue` has direct access to the live Pinia store (`userDashboards`, `groupSharedDashboards`, `defaultGroupDashboards`) needed to keep the sidebar list reactive after create/delete operations. Lifting the sidebar to `WorkspaceApp.vue` (which is initial-state-only) would require forwarding every store getter through inject/provide, adding complexity without user-visible benefit. The e2e gate (`tests/e2e/wave3-runtime-shell.spec.ts` PR #114 test) asserts only one `.dashboard-switcher-sidebar` node in the DOM.

### D2: wave3.3 — inline delete button replaced by per-row cog (`DashboardRowActions`)

**Decision**: The inline hover-revealed `__delete` X button specified in REQ-SWITCH-004 was replaced by `DashboardRowActions`, a per-row `NcActions` cog menu that surfaces Edit / Configure / Add custom widget / Delete in a dropdown.

**Rationale**: The inline button was user-tested and found to be easily triggered by accident on touch/pen devices. The cog menu groups all four per-dashboard destructive + edit actions into one consistent affordance regardless of device type. The `delete-dashboard` emit contract is preserved — the cog emits through `DashboardRowActions` → `DashboardSwitcherSidebar` → Views.vue with the same `(id, source)` payload.

### D3: `+ New Dashboard` as NcButton card, not inline list row

**Decision**: The create affordance is a full-width `NcButton type="outline"` card rendered below the personal list (`__add-dashboard-card`), not a `<li>` row inside the `<ul>`.

**Rationale**: An inline `<li>` row can be mistaken for a real dashboard (same visual weight). A distinct card makes the affordance visually distinct and prevents accidental hover-and-click scenarios on long personal lists.

### D4: `v-model` rebind (`model: { prop: 'isOpen', event: 'update:open' }`)

**Decision**: Vue 2's `model` option is used so the parent template can write `v-model="sidebarOpen"` while the component emits `update:open(boolean)` as mandated by the spec.

**Rationale**: `v-model:open` is Vue 3 syntax. Vue 2.7 requires `model` rebind + `update:<propname>` naming convention to approximate it without a Vue 3 migration. This is the idiomatic Vue 2.7 approach used throughout the codebase.

### D5: `SidebarFooter` as sticky sibling, not inside scroll container

**Decision**: `SidebarFooter` is a direct child of the `<aside>` root, styled `position: sticky; bottom: 0`. The scrollable `__body` sits above it as a sibling.

**Rationale**: If the footer were inside the `__body` scroll container it would scroll out of view on long dashboard lists. Sticky + sibling keeps it visible at all times with no JavaScript needed.

## Declarative-vs-imperative decision

No new PHP service classes were written (this is a pure frontend change). No `x-openregister-*` schema extension applies. ADR-031 check: not applicable.

## MCP coverage

No MCP surface — this change adds a frontend navigation component only. No server-side tool or action is introduced. ADR-035 check: no `IMcpToolProvider` change required.
