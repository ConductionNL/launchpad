# Proposal: clickable-kpi-widgets

## Summary

Make dashboard KPI cards clickable so they navigate to the relevant list view with appropriate filters pre-applied. Currently, KPI cards display counts (e.g. "1000 Open Cases", "973 Overdue") but clicking them does nothing — the user must manually navigate to the list and set filters. This is a missed UX opportunity: the number tells you *what*, but doesn't help you *act*.

![Dashboard KPI cards showing Open Cases (1000), Overdue (973), Completed This Month (0), My Tasks (0)](screenshots/dashboard-kpi-reference.png)

## Motivation

Users see KPI numbers on the dashboard and immediately want to drill into the underlying data. Today they have to:
1. Read "973 Overdue" on the dashboard
2. Navigate to the cases list manually
3. Find and activate the "status = overdue" facet

This should be a single click. Every modern dashboard tool (Metabase, Grafana, Power BI) makes KPI widgets clickable. Our apps should too.

## Affected Projects

- [ ] Project: `pipelinq` — Add route config to KPI cards (Open Leads → leads list, Open Requests → requests list, Overdue → filtered list)
- [ ] Project: `procest` — Wire existing `@click-card` emit to vue-router navigation with query params (component already supports clicks)
- [ ] Project: `mydash` — Add route config to dashboard KPI widgets
- [ ] Project: `larpingapp` — Add route config to dashboard KPI widgets
- [ ] Project: `opencatalogi` — Add route config to dashboard KPI widgets
- [ ] Project: `openregister` — Update shared `CnDashboardPage` component if KPI routing is added at component level

## Scope

### In Scope

- Adding a `route` property to KPI card configuration (route name + query params)
- Making KPI cards render as clickable elements with proper cursor, hover state, and accessibility (`role="link"`, keyboard navigation)
- Each app defines its own route mappings (the KPI card config is app-specific, not centralized)
- Procest already has click handling — wire it to vue-router

### Out of Scope

- Changing KPI calculation logic or data sources
- Adding new KPI metrics
- Dashboard layout changes beyond click behavior
- Deep-linking from external systems into KPI views

## Approach

Build click-to-navigate as a first-class feature of a shared `CnKpiCard` component in the `nextcloud-vue` shared component library. The component handles all the UX concerns (cursor, hover state, accessibility, `<router-link>` rendering, keyboard navigation) so individual apps only need to pass a `route` prop — they never reimplement click handling themselves.

Example usage in any app:
```vue
<CnKpiCard
  title="Open Cases"
  :count="1000"
  icon="icon-folder"
  color="primary"
  :route="{ name: 'cases', query: { status: 'open' } }"
/>
```

When `route` is provided, the component renders as a `<router-link>` with proper accessibility attributes. When omitted, it renders as a static card (backward compatible). Each app only configures *which* route maps to *which* KPI — the navigation behavior is handled entirely by the shared component.

## Cross-Project Dependencies

1. **`nextcloud-vue`** — Add `CnKpiCard` (and optionally `CnKpiGrid`) to the shared component library with built-in route support
2. **All dashboard apps** — Replace inline KPI HTML / app-specific KPI components with the shared `CnKpiCard`, passing app-specific route configs
3. Each app's vue-router must support the query params used for filtering (most already do via faceted search)

## Rollback Strategy

The `route` prop is optional — KPI cards without it remain static. Rollback is simply removing the `route` prop from card configs. No database changes, no API changes. Apps can also continue using their own KPI components if the shared one isn't adopted yet.

## Current State Analysis

### CnStatsBlock (shared component — `nextcloud-vue/src/components/CnStatsBlock/CnStatsBlock.vue`)
The shared `CnStatsBlock` already has a `clickable` prop (Boolean) and emits `@click`. When `clickable` is true, the root element renders as `<a href="#" role="button" tabindex="0">` instead of `<div>`. The component has no awareness of vue-router — click handling is delegated entirely to the consuming app. It is used today **only in OpenRegister** sidebars (`RegisterSideBar.vue`, `RegistersSideBar.vue`) where it displays register/schema statistics without click behavior.

### Per-App KPI Implementation

| App | Uses CnStatsBlock? | KPI Location | Current Click Behavior |
|-----|-------------------|--------------|----------------------|
| **Procest** | No | `procest/src/views/Dashboard.vue` — inline `<div class="kpi-card">` inside `CnDashboardPage` widget slots (`#widget-count-open-cases`, `#widget-count-overdue`, `#widget-count-completed`, `#widget-count-my-tasks`) | **None** — KPI cards are static `<div>` elements with no click handlers. An older `KpiCards.vue` component at `procest/src/views/dashboard/KpiCards.vue` exists with `@click-card` emit and `cursor: pointer` styling, but the Dashboard.vue no longer uses it. |
| **Pipelinq** | No | `pipelinq/src/views/Dashboard.vue` — inline `<div class="kpi-card">` inside `CnDashboardPage` widget slots (`#widget-count-open-leads`, `#widget-count-open-requests`, `#widget-count-pipeline-value`, `#widget-count-overdue`) | **None** — KPI cards are static `<div>` elements. No click handlers on KPI cards. |
| **OpenCatalogi** | No | `opencatalogi/src/views/dashboard/Dashboard.vue` — inline `<div class="kpi-card">` inside `CnDashboardPage` widget slots (`#widget-count-catalogs`, `#widget-count-publications`, `#widget-count-concept-publications`, `#widget-count-concept-attachments`) | **None** — KPI cards are static. |
| **MyDash** | No | MyDash (`mydash/src/views/Views.vue`) is a general-purpose dashboard builder that renders Nextcloud Dashboard API widgets and custom tiles via `DashboardGrid`. It has **no KPI cards at all** — it is a widget host, not a domain-specific app. | N/A — MyDash does not display KPI metrics itself. |
| **LarpingApp** | No | `openregister/custom_apps/larpingapp/src/views/dashboard/DashboardIndex.vue` — this is a **stub dashboard** with only a title and no KPI cards, charts, or data. | N/A — no KPI implementation exists. |
| **OpenRegister** | Yes | `openregister/src/sidebars/register/RegisterSideBar.vue` and `RegistersSideBar.vue` — uses `CnStatsBlock` with `CnKpiGrid` for register statistics (objects, logs, files, schemas). These are sidebar stats, not dashboard KPI cards. | **None** — CnStatsBlock instances have no `clickable` prop set. |

### Key Observation: Duplicated Inline CSS
Procest, Pipelinq, and OpenCatalogi all have near-identical inline KPI card CSS (`.kpi-card`, `.kpi-icon`, `.kpi-content`, `.kpi-value`, `.kpi-label`) copied across Dashboard.vue files. This is exactly the duplication that a shared component eliminates.

## Standards & Compliance

### WCAG AA Requirements for Clickable KPI Cards
- **WCAG 2.1 SC 4.1.2 (Name, Role, Value)**: Clickable cards must expose their role as a link. `<router-link>` renders as native `<a>`, which satisfies this automatically.
- **WCAG 2.1 SC 2.4.7 (Focus Visible)**: Focus indicator must be visible. CnStatsBlock already has `.cn-stats-block--clickable:focus-visible` styles with a 2px solid outline.
- **WCAG 2.1 SC 1.4.3 (Contrast)**: Hover/focus border color (`--color-primary-element`) must meet 3:1 contrast ratio against adjacent colors. Nextcloud's default theme satisfies this.
- **WCAG 2.1 SC 2.1.1 (Keyboard)**: All functionality must be operable via keyboard. Native `<a>` elements support Enter activation by default. `<router-link>` inherits this.

### WAI-ARIA Link Pattern
When `route` is set, `<router-link>` renders a standard `<a href="...">` element. No additional ARIA attributes are needed — the native semantics are correct. The `href` attribute is generated from the route object, so even without JavaScript, the link target is discoverable.

### Vue Router Navigation Patterns
- `<router-link :to="route">` performs client-side SPA navigation without page reload
- Route objects support `{ name, params, query, hash }` — query params are the natural mechanism for filter pre-application
- `<router-link>` supports `target="_blank"` if needed, but same-page navigation is the expected behavior for KPI drill-down

## Open Questions

- Should the KPI click open in the same page or in a new tab? (Same page is the expected UX.)
- Should we add analytics tracking for KPI clicks to understand which metrics users drill into most?
- Should `CnKpiCard` support external URLs (e.g. linking to a Grafana dashboard) in addition to vue-router routes?
- **MyDash scope**: MyDash is a generic dashboard builder with no domain-specific KPIs. Should it be removed from the affected projects list, or should its `TileWidget` gain route support for custom KPI tiles?
- **LarpingApp scope**: LarpingApp has a stub dashboard with no KPI data. Should it be deferred until the dashboard is actually implemented?
- **OpenCatalogi routing**: OpenCatalogi routes have no `name` properties (only `path` strings). Routes will need names added before `{ name: '...' }` style route objects can be used, or the `route` prop must accept path strings.
- **Procest KpiCards.vue**: The old `KpiCards.vue` component exists but is no longer used by Dashboard.vue. Should it be deleted as part of this change, or marked deprecated?
