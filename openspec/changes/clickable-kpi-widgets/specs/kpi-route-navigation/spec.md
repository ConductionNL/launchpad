# Spec: KPI Route Navigation

## Overview

CnStatsBlock MUST support declarative route-based navigation via a `route` prop, eliminating the need for apps to implement click handlers for KPI drill-down.

## Requirements

### REQ-1: Route prop on CnStatsBlock

CnStatsBlock MUST accept an optional `route` prop of type `Object` (Vue Router location object) that defaults to `null`.

#### Preconditions
- `CnStatsBlock` component exists at `nextcloud-vue/src/components/CnStatsBlock/CnStatsBlock.vue`
- The component already uses `<component :is="clickable ? 'a' : 'div'">` dynamic element pattern
- Vue Router is available in the component's context (all consuming apps register vue-router)

#### Current Behavior
KPI cards currently render as `<div>` elements (when `clickable` is false) or `<a href="#">` elements (when `clickable` is true). Neither supports route-based navigation — the `<a>` variant requires the consuming app to handle the `@click` event and call `$router.push()` manually. No app currently does this for dashboard KPI cards.

#### Scenario: KPI card with route navigates on click

```gherkin
Given a CnStatsBlock with route `{ name: 'cases', query: { status: 'open' } }`
When the user clicks the card
Then the app navigates to the 'cases' route with query parameter status=open
And no page reload occurs (SPA navigation)
```

#### Scenario: KPI card without route remains static

```gherkin
Given a CnStatsBlock without a route prop
And without a clickable prop
When the user views the card
Then the card renders as a non-interactive element
And no cursor change occurs on hover
```

#### Edge Cases

##### Empty route object
```gherkin
Given a CnStatsBlock with route `{}`
When the component renders
Then the component SHOULD treat it as if no route was provided (render as static)
And a console warning SHOULD be logged in development mode
```

##### Invalid route name
```gherkin
Given a CnStatsBlock with route `{ name: 'nonexistent-route' }`
When the user clicks the card
Then vue-router emits a NavigationFailure
And the app MUST NOT crash
And the card remains interactive (no visual breakage)
```

##### Route with only a path string
```gherkin
Given a CnStatsBlock with route `{ path: '/catalogi' }`
When the user clicks the card
Then the app navigates to the '/catalogi' path
And this works for apps that have no route names (e.g. OpenCatalogi)
```

### REQ-2: Router-link rendering

When the `route` prop is set, CnStatsBlock MUST render as a `<router-link>` element instead of an `<a>` or `<div>`.

#### Preconditions
- Vue Router must be installed in the app's Vue instance (all Conduction apps satisfy this)
- The `<component :is="...">` pattern in CnStatsBlock must support `'router-link'` as a valid component name

#### Current Behavior
The component currently uses a ternary: `clickable ? 'a' : 'div'`. There is no `'router-link'` option. Clicking an `<a>` element with `href="#"` scrolls to top and emits a Vue event — it does not navigate to a route.

#### Scenario: Route prop implies clickable behavior

```gherkin
Given a CnStatsBlock with a route prop set
And the clickable prop is NOT explicitly set
When the component renders
Then the card MUST display clickable hover and focus styles
And the card MUST render as a <router-link> element
```

#### Edge Cases

##### Router-link without vue-router
```gherkin
Given a CnStatsBlock with a route prop in an app without vue-router
When the component renders
Then Vue SHOULD throw a warning about unknown component 'router-link'
And this is acceptable because all target apps use vue-router
```

### REQ-3: Backward compatibility

The existing `clickable` prop and `@click` event MUST continue to work unchanged. The `route` prop is additive.

#### Preconditions
- OpenRegister's `RegisterSideBar.vue` and `RegistersSideBar.vue` use `CnStatsBlock` without `clickable` or `route` — these must continue rendering as static `<div>` elements
- No existing consumer passes a `route` prop (it does not exist yet), so adding it cannot break existing code

#### Current Behavior
- `clickable=false` (default) → renders `<div>`, no interaction
- `clickable=true` → renders `<a href="#" role="button" tabindex="0">`, emits `@click` on activation
- No `route` prop exists — all KPI navigation is handled manually by apps

#### Scenario: Existing clickable prop still works

```gherkin
Given a CnStatsBlock with clickable=true and no route prop
When the user clicks the card
Then the component emits a 'click' event
And the component renders as an <a> element (existing behavior)
```

#### Scenario: Route takes precedence over clickable

```gherkin
Given a CnStatsBlock with both route and clickable props set
When the component renders
Then the component renders as a <router-link> (route takes precedence)
And clicking navigates via the router (does not emit @click)
```

### REQ-4: Accessibility

#### Preconditions
- CnStatsBlock already has `.cn-stats-block--clickable:focus-visible` CSS (2px solid outline, 2px offset)
- The `rootClasses` computed must include `cn-stats-block--clickable` when `route` is truthy (currently only checks `this.clickable`)

#### Current Behavior
- Static cards (`<div>`) are not focusable and not announced as interactive
- Clickable cards (`<a>`) are focusable and have focus styles, but are announced as "button" (due to `role="button"`) rather than "link"
- No `route`-based cards exist yet

#### Scenario: Keyboard navigation

```gherkin
Given a CnStatsBlock with a route prop
When the user focuses the card via Tab key
Then the card MUST show a visible focus indicator
And pressing Enter MUST trigger navigation
```

#### Scenario: Screen reader announcement

```gherkin
Given a CnStatsBlock with title "Open Cases" and route set
When a screen reader encounters the card
Then it MUST announce the card as a link
And it MUST read the title "Open Cases"
```

#### Edge Cases

##### Screen reader verbosity
```gherkin
Given a CnStatsBlock with title "Open Cases", count 1000, and countLabel "cases"
When a screen reader reads the card content
Then it reads all text content: "Open Cases 1,000 cases"
And this may be overly verbose
Then an aria-label prop SHOULD be considered for concise announcement
```

### REQ-5: App migration

All Conduction apps with KPI dashboards SHOULD replace custom KPI implementations with CnStatsBlock + CnKpiGrid from the shared library, passing app-specific route configurations.

#### Preconditions
- `CnStatsBlock` route prop must be implemented and published (Phase 1)
- Each app's vue-router must have named routes matching the KPI drill-down targets
- OpenCatalogi routes need `name` properties added (currently path-only)
- Apps must support query parameter-based filtering in their list views (Procest and Pipelinq already do via faceted search)

#### Current Behavior
- Procest, Pipelinq, and OpenCatalogi all have inline `<div class="kpi-card">` HTML in their `CnDashboardPage` widget slots
- All three apps have nearly identical duplicated CSS for `.kpi-card`, `.kpi-icon`, `.kpi-content`, `.kpi-value`, `.kpi-label`
- None of the KPI cards are clickable — they display counts but do not navigate anywhere
- MyDash has no KPI cards (it is a generic dashboard builder)
- LarpingApp has a stub dashboard with no KPI data

#### Scenario: Pipelinq KPI drill-down

```gherkin
Given the pipelinq dashboard shows "Open Leads: 42"
When the user clicks the "Open Leads" KPI card
Then the app navigates to the leads list view (route name: 'Leads')
And the status filter is pre-set to "open"
```

#### Scenario: Procest KPI drill-down

```gherkin
Given the procest dashboard shows "Overdue: 973"
When the user clicks the "Overdue" KPI card
Then the app navigates to the cases list view (route name: 'Cases')
And the overdue filter is activated
```

#### Scenario: OpenCatalogi KPI drill-down

```gherkin
Given the opencatalogi dashboard shows "Catalogs: 5"
When the user clicks the "Catalogs" KPI card
Then the app navigates to the catalogi list view (path: '/catalogi')
```

#### Edge Cases

##### Pipelinq Pipeline Value (currency display)
```gherkin
Given a CnStatsBlock displaying "Pipeline Value: EUR 125,000"
When CnStatsBlock receives count=125000
Then CnStatsBlock displays "125,000" via toLocaleString()
And the "EUR" prefix MUST be handled via countLabel or a custom slot
```

##### OpenCatalogi publications without catalog context
```gherkin
Given the opencatalogi publications route requires a catalogSlug parameter
And the KPI card shows aggregate publication count across all catalogs
When the user clicks "Publications: 42"
Then the app SHOULD navigate to the catalogi overview (not a specific catalog)
Because there is no single catalog to link to
```
