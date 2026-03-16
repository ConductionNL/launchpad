# Design: clickable-kpi-widgets

## Architecture Overview

The shared component library (`@conduction/nextcloud-vue`) already has `CnStatsBlock` with a `clickable` prop and `@click` emit, and `CnKpiGrid` for layout. The gap is that click-to-navigate is handled by each app individually — the component doesn't know about routing.

The design adds a `route` prop to `CnStatsBlock` so the component handles navigation internally. When `route` is set, the component renders as a `<router-link>` (for SPA navigation) instead of an `<a>` tag, and `clickable` is implied. Apps only need to pass route config — no click handlers required.

```
┌─────────────────────────────────────────────────────────┐
│  CnKpiGrid                                              │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐     │
│  │ CnStatsBlock│  │ CnStatsBlock│  │ CnStatsBlock│     │
│  │ route={     │  │ route={     │  │ (no route)  │     │
│  │  name:'list'│  │  name:'list'│  │ → static    │     │
│  │  query:{..} │  │  query:{..} │  │             │     │
│  │ }           │  │ }           │  │             │     │
│  │ → <router-  │  │ → <router-  │  │ → <div>     │     │
│  │    link>    │  │    link>    │  │             │     │
│  └─────────────┘  └─────────────┘  └─────────────┘     │
└─────────────────────────────────────────────────────────┘
```

## Current Implementation Details

### CnStatsBlock Component (`nextcloud-vue/src/components/CnStatsBlock/CnStatsBlock.vue`)

**Current props** (all have defaults — backward compatible):
- `title` (String) — block title
- `count` (Number) — main count value, displayed via `formattedCount` (`.toLocaleString()`)
- `countLabel` (String, default `'objects'`) — label next to count
- `breakdown` (Object) — key-value pairs displayed below count
- `loading` (Boolean) — shows `NcLoadingIcon` when true
- `loadingLabel` / `emptyLabel` (String) — text for loading/empty states
- `icon` (Object|Function) — Vue component icon (MDI icons)
- `iconSize` (Number, default 24)
- `variant` (String) — `'default'|'primary'|'success'|'warning'|'error'` — controls icon background and count value color
- `horizontal` (Boolean) — switches from vertical to horizontal layout
- `clickable` (Boolean) — when true, renders root as `<a href="#" role="button" tabindex="0">` with cursor: pointer and hover/focus styles

**Current rendering logic (line 2-3 of template):**
```vue
<component
  :is="clickable ? 'a' : 'div'"
  v-bind="clickable ? { href: '#', role: 'button', tabindex: '0' } : {}"
  @click="onClick">
```

**Current click handler (line 186-190):**
```js
onClick(event) {
  if (this.clickable) {
    event.preventDefault()
    this.$emit('click', event)
  }
}
```

**Current rootClasses computed:**
```js
rootClasses() {
  return {
    'cn-stats-block--horizontal': this.horizontal,
    'cn-stats-block--clickable': this.clickable,
    [`cn-stats-block--${this.variant}`]: this.variant !== 'default',
  }
}
```

**Existing CSS for clickable state:**
- `.cn-stats-block--clickable` — `cursor: pointer`
- `.cn-stats-block--clickable:hover` — `border-color: var(--color-primary-element)` + box-shadow
- `.cn-stats-block--clickable:focus-visible` — `outline: 2px solid var(--color-primary-element)` with 2px offset

**Key observation:** The `<component :is="...">` dynamic element pattern is already in place, making the addition of `'router-link'` as a third option a minimal change. The `v-bind` pattern also supports dynamic attribute binding, so `{ to: route }` can be passed when `route` is set.

## Component Changes

### `CnStatsBlock` — New `route` prop

**New prop:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `route` | `Object\|null` | `null` | Vue Router route location object (e.g. `{ name: 'cases', query: { status: 'open' } }`). When set, the card renders as a `<router-link>` and `clickable` is implied. |

**Rendering logic:**

```
if route → <router-link :to="route">  (SPA navigation, no page reload)
else if clickable → <a href="#" @click.prevent>  (existing behavior, app handles click)
else → <div>  (static, no interaction)
```

**Implementation detail — the `<component :is="...">` pattern already used in CnStatsBlock makes this a minimal change:**

```js
// Current (line 3):
:is="clickable ? 'a' : 'div'"

// New:
:is="componentTag"

// In computed:
componentTag() {
  if (this.route) return 'router-link'
  if (this.clickable) return 'a'
  return 'div'
},
componentAttrs() {
  if (this.route) return { to: this.route, tabindex: '0' }
  if (this.clickable) return { href: '#', role: 'button', tabindex: '0' }
  return {}
},
isInteractive() {
  return !!this.route || this.clickable
},
```

**Accessibility:**
- `<router-link>` renders as `<a>` natively — proper semantics without extra attributes
- Focus styles already exist (`.cn-stats-block--clickable:focus-visible`)
- Keyboard: Enter/Space triggers navigation (native `<a>` behavior)

### No changes to `CnKpiGrid`

`CnKpiGrid` is a pure layout component (CSS grid wrapper) — it doesn't need to know about routing. The `route` prop lives on individual `CnStatsBlock` instances.

## App Migration Pattern

Each app replaces its custom KPI HTML with `CnStatsBlock` + route config. The pattern is identical across all apps — only the route names and query params differ.

**Before (pipelinq inline HTML):**
```vue
<div class="kpi-card" @click="$router.push({ name: 'leads' })">
  <span class="kpi-value">{{ openLeads }}</span>
  <span class="kpi-label">Open Leads</span>
</div>
```

**After:**
```vue
<CnKpiGrid>
  <CnStatsBlock
    :title="t('pipelinq', 'Open Leads')"
    :count="openLeads"
    :icon="AccountGroup"
    variant="primary"
    :route="{ name: 'leads', query: { status: 'open' } }"
  />
</CnKpiGrid>
```

**Before (procest custom component with emit):**
```vue
<KpiCards :openCases="1000" @click-card="handleCardClick" />
// handleCardClick(id) { if (id === 'open') this.$router.push(...) }
```

**After:**
```vue
<CnKpiGrid>
  <CnStatsBlock
    :title="t('procest', 'Open Cases')"
    :count="openCases"
    :icon="BriefcaseOutline"
    variant="primary"
    :route="{ name: 'zaken', query: { status: 'open' } }"
  />
</CnKpiGrid>
```

## File Structure

```
nextcloud-vue/
  src/components/CnStatsBlock/
    CnStatsBlock.vue          # Add route prop + router-link rendering

pipelinq/
  src/views/Dashboard.vue     # Replace inline KPI HTML with CnStatsBlock

procest/
  src/views/Dashboard.vue     # Replace KpiCards usage with CnStatsBlock
  src/views/dashboard/KpiCards.vue  # Deprecate (functionality moves to shared lib)

mydash/
  src/views/Dashboard.vue     # Replace inline KPI with CnStatsBlock

larpingapp/
  src/views/dashboard/Dashboard.vue  # Replace inline KPI with CnStatsBlock

opencatalogi/
  src/views/dashboard/Dashboard.vue  # Replace inline KPI with CnStatsBlock
```

## Per-App Migration Notes

### Procest (`procest/src/views/Dashboard.vue`)

**Current KPI structure:** Four inline `<div class="kpi-card">` blocks inside `CnDashboardPage` widget slots (`#widget-count-open-cases`, `#widget-count-overdue`, `#widget-count-completed`, `#widget-count-my-tasks`). Each contains a `.kpi-icon` div with an MDI icon and a `.kpi-content` div with `.kpi-value` and `.kpi-label` spans. No click handlers.

**Available route names** (from `procest/src/router/index.js`):
- `'Cases'` (path: `/cases`) — case list view
- `'Tasks'` (path: `/tasks`) — task list view
- `'MyWork'` (path: `/my-work`) — my work view

**Migration:**
1. Replace each `#widget-count-*` slot content with a single `<CnStatsBlock>` using the `route` prop
2. Route mappings: Open Cases → `{ name: 'Cases', query: { status: 'open' } }`, Overdue → `{ name: 'Cases', query: { overdue: 'true' } }`, Completed → `{ name: 'Cases', query: { status: 'completed' } }`, My Tasks → `{ name: 'Tasks' }`
3. Delete approximately 100 lines of duplicated `.kpi-card` / `.kpi-icon` / `.kpi-content` CSS
4. The old `KpiCards.vue` component (`procest/src/views/dashboard/KpiCards.vue`) is already unused by Dashboard.vue — it can be deleted or deprecated

**Note:** Procest's `KpiCards.vue` has a richer design than the Dashboard.vue inline KPIs: it includes a subtitle line (e.g. "+3 today", "action needed") that `CnStatsBlock` does not currently support. This subtitle could be mapped to `countLabel` or may need a new prop.

### Pipelinq (`pipelinq/src/views/Dashboard.vue`)

**Current KPI structure:** Four inline `<div class="kpi-card">` blocks inside `CnDashboardPage` widget slots (`#widget-count-open-leads`, `#widget-count-open-requests`, `#widget-count-pipeline-value`, `#widget-count-overdue`). Structure identical to Procest — `.kpi-icon` + `.kpi-content` with `.kpi-value` and `.kpi-label`.

**Available route names** (from `pipelinq/src/router/index.js`):
- `'Leads'` (path: `/leads`) — lead list view
- `'Requests'` (path: `/requests`) — request list view
- `'Pipeline'` (path: `/pipeline`) — pipeline board view

**Migration:**
1. Replace each `#widget-count-*` slot with `<CnStatsBlock>` + `route` prop
2. Route mappings: Open Leads → `{ name: 'Leads', query: { status: 'open' } }`, Open Requests → `{ name: 'Requests', query: { status: 'open' } }`, Pipeline Value → `{ name: 'Pipeline' }`, Overdue → `{ name: 'Leads', query: { overdue: 'true' } }`
3. Delete duplicated `.kpi-card` CSS (approximately 40 lines identical to Procest)

**Note:** The Pipeline Value KPI displays currency (`formatCurrency()`) rather than a count. `CnStatsBlock.formattedCount` uses `.toLocaleString()` which works for integers but not for currency formatting. Either: (a) pass the pre-formatted string via a new prop, or (b) use the `countLabel` to add "EUR" and pass the raw number.

### OpenCatalogi (`opencatalogi/src/views/dashboard/Dashboard.vue`)

**Current KPI structure:** Four inline `<div class="kpi-card">` blocks inside `CnDashboardPage` widget slots (`#widget-count-catalogs`, `#widget-count-publications`, `#widget-count-concept-publications`, `#widget-count-concept-attachments`). Same CSS pattern as Procest/Pipelinq.

**Router limitation:** OpenCatalogi's routes have **no `name` properties** — they use path-only routing (e.g. `{ path: '/catalogi', components: { default: Catalogi } }`). The `route` prop on `CnStatsBlock` expects a Vue Router location object, which can use `{ path: '/catalogi' }` as an alternative to `{ name: 'Catalogi' }`. However, adding route names is recommended for maintainability.

**Migration:**
1. Add `name` properties to OpenCatalogi routes (e.g. `name: 'Catalogi'`, `name: 'Publications'`)
2. Replace each `#widget-count-*` slot with `<CnStatsBlock>` + `route` prop
3. Route mappings: Catalogs → `{ path: '/catalogi' }`, Publications → `{ path: '/publications' }` (but needs a catalog slug — may not be drillable without a default catalog), Concept Publications → `{ path: '/publications', query: { status: 'Concept' } }`, Concept Attachments → (no dedicated attachments list view exists — may need to omit route or link to a filtered publications view)

**Blocker:** OpenCatalogi's publications route requires a `catalogSlug` parameter (`/publications/:catalogSlug`). KPI cards show aggregate counts across all catalogs, so there is no single catalog to link to. Options: (a) link to the `/catalogi` overview, (b) add a new route for all-publications view, or (c) omit route for publication KPIs.

### MyDash

**No migration needed.** MyDash is a generic dashboard builder — it hosts Nextcloud Dashboard API widgets and custom tiles. It has no domain-specific KPI cards. If KPI support is desired, it would be through the existing `TileWidget` system, not `CnStatsBlock`.

### LarpingApp (`openregister/custom_apps/larpingapp/src/views/dashboard/DashboardIndex.vue`)

**No migration possible.** The dashboard is a stub containing only `<h2>Dashboard</h2>` and `<p>LarpingApp</p>`. There are no KPI cards, no data fetching, no store connections. This app should be deferred until a real dashboard is implemented.

### OpenRegister (sidebar usage)

**No dashboard migration needed.** OpenRegister uses `CnStatsBlock` in register sidebars for statistics display, not on a dashboard. These sidebar stats are informational and not expected to be clickable drill-down targets. The `route` prop addition does not affect these existing usages since the prop defaults to `null`.

## Accessibility Considerations

### WCAG AA Requirements for Router-Link Rendering
- **Semantic correctness**: `<router-link>` renders as `<a href="/path">`, which is the correct semantic element for navigation. Screen readers announce it as "link" automatically — no `role` attribute needed.
- **Accessible name**: The `<a>` wraps the entire card content. The accessible name is derived from the text content inside (title + count + label). This may be verbose for screen readers (e.g. "Open Cases 1,000 open cases, link"). Consider adding an `aria-label` prop for a concise description.
- **Focus management**: After navigation, the target page should manage focus (move to main content or heading). This is the responsibility of the target view, not the KPI card.
- **Touch target size**: WCAG 2.2 SC 2.5.8 requires interactive targets to be at least 24x24 CSS pixels. KPI cards are much larger than this minimum.

### Keyboard Navigation
- **Tab order**: KPI cards with `route` are focusable via Tab (native `<a>` behavior)
- **Activation**: Enter triggers navigation (native `<a>` behavior). Space also works with `<a>` elements in most browsers.
- **No trap**: Focus can move past KPI cards freely — no keyboard trap risk.

### Focus Indicator Styles
Existing `.cn-stats-block--clickable:focus-visible` provides a 2px solid primary-color outline with 2px offset, meeting WCAG 2.4.7. This style must also apply when `route` is set (the `rootClasses` computed needs to include `cn-stats-block--clickable` when `route` is truthy).

## Security Considerations

- Route objects are passed as props, not user input — no injection risk
- `<router-link>` only navigates within the SPA — no external URL support (by design)
- No API changes, no backend changes, no database changes

## NL Design System

- Uses existing Nextcloud CSS variables (`--color-primary-element`, `--color-border`, etc.)
- NL Design overrides these variables automatically via the nldesign app
- No new design tokens needed — the component already uses the correct variables

## Trade-offs

### Decision: `route` prop on CnStatsBlock vs. wrapping in `<router-link>` externally

**Chosen: `route` prop on CnStatsBlock**
- Keeps the API simple (one prop vs. wrapper element)
- Component controls accessibility attributes internally
- Consistent hover/focus styling without CSS leaking

**Alternative considered: external `<router-link>` wrapper**
- Would work but forces every app to handle `<router-link>` styling, cursor states, and accessibility
- Breaks the component's border/hover styles (wrapper adds an extra DOM layer)

### Decision: No external URL support

KPI cards should navigate within the app, not to external dashboards. If needed later, a separate `href` prop could be added. Keeping it vue-router only for now avoids mixed navigation patterns.
