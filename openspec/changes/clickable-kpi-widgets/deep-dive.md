# Deep-Dive Analysis: Clickable KPI Widgets

## Spec Readiness Assessment

**Overall Score: 7/10**

The spec is well-structured with clear requirements (Gherkin scenarios), a solid design document, and a phased task breakdown. The `CnStatsBlock` component change itself is small and well-defined. However, the spec has a significant gap: it assumes apps currently use `CnStatsBlock` for KPI cards, but in reality **none of the dashboard apps use CnStatsBlock**. All three active dashboards (procest, pipelinq, opencatalogi) use inline HTML within CnDashboardPage widget slots. This means the migration effort is larger than the spec suggests, and the interaction between `CnStatsBlock` (standalone component) and `CnDashboardPage` (grid layout with widget slots) needs to be clarified.

The component-level change is specific enough to implement without further clarification. The app-level migration is not.

## Current Implementation Inventory

### CnStatsBlock (`nextcloud-vue/src/components/CnStatsBlock/CnStatsBlock.vue`)

**Props:** `title`, `count`, `countLabel`, `breakdown`, `loading`, `loadingLabel`, `emptyLabel`, `icon`, `iconSize`, `variant` (default/primary/success/warning/error), `horizontal`, `clickable`.

**Rendering:** Uses `<component :is="clickable ? 'a' : 'div'">` pattern. When `clickable` is true, renders as `<a href="#" role="button" tabindex="0">` and emits `click` event on click (with `preventDefault`).

**Styling:** Has `--clickable` modifier with cursor pointer, hover border, and `:focus-visible` outline. Uses Nextcloud CSS variables throughout.

**Current limitation:** No `route` prop. No `router-link` rendering. Click handling is entirely emit-based -- the consuming component must handle navigation.

### CnKpiGrid (`nextcloud-vue/src/components/CnKpiGrid/CnKpiGrid.vue`)

Pure CSS grid layout wrapper. Supports 2/3/4 columns with responsive breakpoints. No routing or click logic. This component is **not used by any app** currently.

### Procest Dashboard (`procest/src/views/Dashboard.vue`)

- Uses `CnDashboardPage` with 6 custom widgets (4 KPI count tiles + status chart + my-work list).
- KPI cards are **inline HTML** inside `#widget-count-*` slots: `<div class="kpi-card">` with icon + value + label.
- **Does NOT use CnStatsBlock or CnKpiGrid.**
- Has a separate legacy `KpiCards.vue` component (at `procest/src/views/dashboard/KpiCards.vue`) that is **not imported** in the current Dashboard.vue. This component has a `@click-card` emit but no route integration.
- The Dashboard.vue has no click handlers on KPI cards. The `onWorkItemClick` method handles clicks on "My Work" list items, navigating to `CaseDetail` or `TaskDetail`.
- Router defines: `Cases` (/cases), `Tasks` (/tasks), `CaseDetail` (/cases/:id), `TaskDetail` (/tasks/:id), `MyWork` (/my-work).
- **No query-param-based filtering** is visible in the router config. The spec assumes routes like `{ name: 'zaken', query: { status: 'open' } }`, but the actual route name is `Cases`, not `zaken`. The list views would need to read and apply query params as filters.

### Pipelinq Dashboard (`pipelinq/src/views/Dashboard.vue`)

- Uses `CnDashboardPage` with 7 custom widgets (4 KPI count tiles + deals chart + my-work + client overview).
- KPI cards are **inline HTML** inside `#widget-count-*` slots, identical pattern to procest.
- **Does NOT use CnStatsBlock or CnKpiGrid.**
- No click handlers on KPI cards.
- Router defines: `Leads` (/leads), `Requests` (/requests), `Clients` (/clients), `Pipeline` (/pipeline), `MyWork` (/my-work).
- The spec assumes route name `leads` but the actual route name is `Leads` (capital L). Same for `requests` vs `Requests`.

### OpenCatalogi Dashboard (`opencatalogi/src/views/dashboard/Dashboard.vue`)

- Uses `CnDashboardPage` with 7 custom widgets (4 KPI count tiles + catalogi list + concept publications + concept attachments).
- KPI cards are **inline HTML** inside `#widget-count-*` slots.
- **Does NOT use CnStatsBlock or CnKpiGrid.**
- No click handlers on KPI cards.
- Uses a different store pattern (`objectStore` from `../../store/store.js`) and a `navigationStore` for modals.
- The catalog list items have click handlers that navigate via `$router.push`, but KPI count tiles do not.

### MyDash (`mydash/src/`)

- MyDash is a **meta-dashboard** that renders Nextcloud Dashboard API widgets and custom tiles via GridStack.
- It does **not have its own KPI cards** -- it renders widgets from other apps.
- No `CnStatsBlock` usage found. The spec lists mydash as an affected project, but there may be nothing to migrate.

### LarpingApp (`openregister/custom_apps/larpingapp/src/views/dashboard/DashboardIndex.vue`)

- **Minimal placeholder** -- just shows a title and "LarpingApp" text. No KPI cards, no data, no widgets.
- The spec lists larpingapp as an affected project, but there is nothing to migrate currently.

## Standards & Patterns

### Vue / Nextcloud Patterns

- **Dynamic component tag:** CnStatsBlock already uses `:is="clickable ? 'a' : 'div'"` which maps cleanly to adding `'router-link'` as a third option. This is the established Vue 2 pattern.
- **`<router-link>` in component libraries:** `router-link` requires `vue-router` to be installed in the consuming app. The `nextcloud-vue` library does NOT currently depend on `vue-router`. Adding `router-link` rendering introduces an implicit peer dependency. If a consumer doesn't have `vue-router`, using the `route` prop would cause a runtime error. This should be documented.
- **CnDashboardPage widget slots vs CnStatsBlock:** Currently, KPI cards render as inline HTML inside `CnDashboardPage` widget slots (`#widget-count-*`). Replacing this inline HTML with `CnStatsBlock` components inside the widget slots is straightforward but changes the DOM structure (widget slot content goes from a `div.kpi-card` to a `CnStatsBlock` root element). The CSS impact needs testing since `CnDashboardPage`'s widget wrapper adds its own padding/borders.

### Accessibility (WCAG AA)

- **Keyboard navigation:** `<router-link>` renders as `<a>`, which is natively focusable and activatable via Enter. The existing `:focus-visible` styles on `.cn-stats-block--clickable` provide a visible focus indicator. This meets WCAG 2.4.7 (Focus Visible).
- **Screen reader:** `<a>` elements are announced as "link" by screen readers. The card content (title, count) inside the `<a>` would be read as the link text. However, the current CnStatsBlock structure has multiple child elements (icon, title, count, label) -- screen readers would read all of them as one link text, which could be verbose. Consider adding an `aria-label` that provides a concise description like "Open Cases: 1000 - View list".
- **Color contrast:** The component uses Nextcloud CSS variables which meet WCAG AA when using default or NL Design themes. No issues here.
- **Touch targets:** The entire card is the click target, which exceeds WCAG 2.5.8 minimum 24x24px requirement.

### NL Design System

- CnStatsBlock already uses Nextcloud CSS variables exclusively. NL Design overrides these variables via the nldesign app, so theming works automatically. No new design tokens are needed.
- The hover/focus states use `--color-primary-element` which adapts to NL Design themes.

## Open Questions

1. **How should CnStatsBlock interact with CnDashboardPage widget slots?**
   - Who: Architecture decision (team lead).
   - Suggested default: Use CnStatsBlock inside `#widget-count-*` slots. The widget wrapper provides the outer container; CnStatsBlock provides the inner card with route support. This may require removing CnStatsBlock's own background/border styling when used inside a widget wrapper to avoid double-boxing.

2. **Should the route names in the spec match the actual router definitions?**
   - Who: Spec author.
   - Suggested default: Yes. The spec currently uses lowercase route names (`zaken`, `leads`, `requests`) that don't match the actual router configs (`Cases`, `Leads`, `Requests`). The tasks should use the actual route names.

3. **Do the list views currently support query-param-based filtering?**
   - Who: Each app's developer.
   - Suggested default: Likely not. The router configs don't show query param handling. Each list view (CaseList, LeadList, RequestList) would need to read `this.$route.query` on mount and apply filters. This is additional work not accounted for in the task list.

4. **Should `CnStatsBlock` get an `aria-label` prop for screen reader optimization?**
   - Who: Accessibility review.
   - Suggested default: Yes. Add an optional `ariaLabel` prop. When set, apply it to the root element. When not set, let the browser compose from child content.

5. **What happens to the `@click` event when `route` is set?**
   - Who: Spec already addresses this (route takes precedence, no `@click` emit). Confirmed adequate.

6. **Should MyDash and LarpingApp remain in the affected projects list?**
   - Who: Product owner.
   - Suggested default: Remove them. MyDash is a meta-dashboard with no own KPI cards. LarpingApp has a placeholder dashboard with no data.

7. **Should the old procest `KpiCards.vue` be deleted or just deprecated?**
   - Who: Procest maintainer.
   - Suggested default: Delete. It is not imported by the current Dashboard.vue and has been superseded by inline HTML in widget slots. There is no value in deprecating a component that is already unused.

## Risks & Dependencies

### Risks

1. **Query param filtering not implemented in list views.** The spec assumes clicking "Open Cases" navigates to `/cases?status=open` and the CaseList view shows filtered results. But there is no evidence that CaseList reads `$route.query.status`. If list views don't support query params, the KPI cards will navigate to the correct page but won't filter -- defeating the purpose. **This is the single biggest risk.**

2. **CnStatsBlock styling conflict inside CnDashboardPage widgets.** CnStatsBlock has its own `background`, `border-radius`, and `padding`. CnDashboardPage's widget wrapper (`CnWidgetWrapper`) also adds padding and borders. Using CnStatsBlock inside a widget slot could result in double padding or nested rounded rectangles. The current inline `.kpi-card` CSS uses negative margins to compensate for widget padding (e.g., `.kpi-card--warning { margin: -16px; padding: 16px; }`). CnStatsBlock doesn't have this compensation.

3. **`vue-router` peer dependency.** CnStatsBlock using `router-link` assumes `vue-router` is available. If the component is ever used outside a vue-router context (e.g., in a simple page without routing), it will crash.

4. **Duplicated KPI CSS across apps.** Procest, pipelinq, and opencatalogi all have nearly identical `.kpi-card` CSS (same icon size, colors, layout). Migrating to CnStatsBlock eliminates this duplication, but the migration must ensure visual parity. The current inline CSS and CnStatsBlock's CSS are not identical (different font sizes, layout direction, spacing).

### Dependencies

- **Phase 1 (CnStatsBlock change) must complete before Phase 2 (app migrations).** Apps cannot use the `route` prop until it exists in the published `@conduction/nextcloud-vue` package.
- **List view query param support must be implemented in parallel with or before Phase 2.** Without this, the route navigation is functional but useless.
- **`@conduction/nextcloud-vue` must be published and version-bumped** before any app can use the new prop.

## Gap Analysis

### What the spec covers well

- The component-level API design (route prop, rendering logic, backward compatibility) is thorough and well-reasoned.
- The Gherkin scenarios cover the main use cases including precedence rules (route > clickable > static).
- The security considerations are appropriate (no injection risk, SPA-only navigation).
- The rollback strategy is sound (route prop is optional, removing it restores static behavior).
- Trade-off analysis (route prop vs external wrapper) is well-documented.

### What is missing or underspecified

1. **No task for list view query param support.** The tasks assume list views already filter based on `$route.query`, but there is no evidence of this. A new task is needed per app: "Add query param filter initialization to [CaseList|LeadList|RequestList|etc.]."

2. **Incorrect route names.** The design and task documents use route names that don't match the actual router configs. `zaken` should be `Cases`, `leads` should be `Leads`, `requests` should be `Requests`, `tasks` should be `Tasks`.

3. **No mention of CnDashboardPage integration.** All three active dashboards use CnDashboardPage with widget slots for KPI cards. The design shows CnKpiGrid + CnStatsBlock as the target layout, but these are standalone components, not CnDashboardPage widgets. The design needs to clarify: are KPI cards staying as CnDashboardPage widgets (with CnStatsBlock inside the slots) or being pulled out of the widget grid into a separate CnKpiGrid above it?

4. **No CSS reconciliation plan.** The current inline `.kpi-card` CSS and CnStatsBlock's CSS differ in layout (inline is horizontal with icon left; CnStatsBlock defaults to vertical). The `horizontal` prop on CnStatsBlock would match the current layout, but this is not mentioned in the tasks.

5. **MyDash and LarpingApp listed as affected but have nothing to migrate.** These should be removed from the affected projects list or explicitly marked as "no action needed."

6. **No mention of `aria-label` for screen reader optimization.** When a KPI card is a link, screen readers read all child content as the link text. This could be verbose ("icon Open Cases 1,000 open cases"). An `aria-label` prop would allow apps to provide concise link descriptions.

7. **The deprecated KpiCards.vue (procest) is listed for deprecation but is already unused.** The current Dashboard.vue imports `CnDashboardPage` and uses inline HTML; it does not import `KpiCards.vue`. The task should say "delete" not "deprecate."
