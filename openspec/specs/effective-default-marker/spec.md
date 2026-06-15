---
capability: effective-default-marker
status: implemented
---

# Effective Default Marker Specification

## Purpose

The dashboard switcher sidebar marks the user's *effective default
dashboard* — the dashboard the resolver lands them on when they
visit `/apps/launchpad/` cold — with a small ★ icon and a tooltip.

Without the marker, the only feedback after clicking "Set as
default" in a row's cog menu was the StarCheck icon inside the menu
itself, which auto-closes after the click. Users couldn't tell at a
glance which dashboard was their default.

## Context

"Effective default" means the dashboard the seven-step resolver
chain (REQ-DASH-018) would pick at cold load. The first five steps
of the chain are sidebar-visible and therefore eligible for the
marker:

1. **User pin** — `defaultDashboardUuid` set via
   `setDefaultDashboardPreference()`.
2. **Primary-group default** — `groupDashboards` row with
   `isDefault=1` AND `groupId == primaryGroup`.
3. **Default-group default** — `defaultGroupDashboards` row with
   `isDefault=1`.
4. First primary-group dashboard (no flag).
5. First default-group dashboard (no flag).

Step 6 (first personal dashboard) is intentionally NOT marked when
no explicit pin or group default applies — silently starring an
arbitrary personal dashboard the user never marked is more
confusing than helpful.

## Requirements

### Requirement: REQ-EDM-001 Star icon renders on the effective-default row

WHEN `DashboardSwitcherSidebar.vue` renders any sidebar section (primary-group / default-group / personal) each row MUST consult `isDefaultDashboard(dashboard)` AND render a `<span class="dashboard-switcher-sidebar__default-marker">` containing a `Star` icon when the result is true. The marker appears between the dashboard's own icon and its label so the dashboard's icon stays anchored to the left edge.

#### Scenario: Star renders on the effective-default row
@e2e exclude marker-render assertion on a specific DOM node — covered by Vitest component test
- **GIVEN** the sidebar renders with one dashboard being the effective default
- **WHEN** the rows render
- **THEN** that row MUST show a `Star` icon inside a `dashboard-switcher-sidebar__default-marker` span
- **AND** non-default rows MUST NOT show the marker

### Requirement: REQ-EDM-002 Effective default uses resolver precedence

`isDefaultDashboard(d)` MUST compare `d.uuid` against
`effectiveDefaultUuid`, the computed property that mirrors the
resolver's first five steps:

```
1. defaultUuid (the user's pin) — when set
2. primaryGroupDashboards.find(d => d.isDefault === 1) — when present
3. defaultGroupDashboards.find(d => d.isDefault === 1) — when present
4. primaryGroupDashboards[0] — when at least one exists
5. defaultGroupDashboards[0] — when at least one exists
6. (no fallback to personal dashboards — intentional)
```

The pin always wins over group fallbacks.

#### Scenario: Pin wins over group fallback
@e2e exclude resolver-precedence logic — covered by Vitest component test
- **GIVEN** the user has pinned dashboard A and a group default B exists
- **WHEN** `isDefaultDashboard()` is evaluated for each row
- **THEN** only dashboard A MUST be marked as the effective default

### Requirement: REQ-EDM-003 Tooltip + accessibility

The marker span MUST carry a `title` attribute with the localised
copy *"Default dashboard — opens automatically when you visit
LaunchPad"* and an `aria-label` with the shorter *"Default dashboard"*
so screen readers announce the marker.

#### Scenario: Marker carries tooltip and aria-label
@e2e exclude title/aria-label attribute assertion — covered by Vitest component test
- **GIVEN** the effective-default marker is rendered
- **WHEN** the marker span is inspected
- **THEN** it MUST carry a `title` attribute with the localised default-dashboard copy
- **AND** an `aria-label` of "Default dashboard"

### Requirement: REQ-EDM-004 Reactive on pin change

The marker MUST update on every change to `defaultUuid` or the
`groupDashboards` / `userDashboards` props without a page reload.
Pinning a different dashboard via the cog menu MUST move the marker
in the same render tick.

#### Scenario: Marker moves when pin changes
@e2e exclude same-tick reactive re-render assertion — covered by Vitest component test
- **GIVEN** dashboard A is currently marked as default
- **WHEN** the user pins dashboard B via the cog menu
- **THEN** the marker MUST move to dashboard B in the same render tick without a page reload

### Requirement: REQ-EDM-005 Color uses theme variable

The marker's color MUST be `var(--color-warning, #e9a800)` so it
inherits Nextcloud theme overrides (light/dark variants).

#### Scenario: Marker color uses theme variable
@e2e exclude CSS custom-property color is a computed-style assertion — Vitest/visual-regression scope, not browser flow
- **GIVEN** the marker is rendered
- **WHEN** its color is computed
- **THEN** it MUST resolve to `var(--color-warning, #e9a800)` so it follows theme overrides

## Test coverage

- `src/components/Workspace/__tests__/DashboardSwitcherSidebar.spec.js`
  has 9 cases:
  - Marker renders only on the matching row
  - Marker carries `title` + `aria-label`
  - No marker when `defaultUuid` is empty AND no group fallback applies
  - Marker on a group-section row when the user pinned a group dashboard
  - Falls back to default-group `isDefault=1` when no pin set
  - Prefers primary-group `isDefault=1` over default-group `isDefault=1`
  - Falls back to first primary-group row when no `isDefault=1` flag
  - Does NOT star a personal dashboard via fallback
  - Pin still wins over any group fallback

## References

- Implementation: PR #130 (initial marker) + PR #130 follow-up
  (group-default fallback).
- Resolver chain: `REQ-DASH-018` in the dashboards capability.
- Frontend reference: [docs/features/effective-default-marker.md](../../../docs/features/effective-default-marker.md).
