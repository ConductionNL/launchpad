# Built-in Dashboards

**Owned by**: Cross-app dashboard pattern (powered by MyDash, applicable to all Conduction apps)

## Purpose
Provide a comprehensive, cross-app dashboard pattern powered by the MyDash shared infrastructure and `CnDashboardPage` component from `@conduction/nextcloud-vue`. This spec defines the standard dashboard builder that all Conduction apps (OpenRegister, Pipelinq, Procest, LarpingApp, etc.) use for visual analytics from OpenRegister data without requiring external BI tools. Dashboards MUST support configurable widget types (KPI counters, bar/line/pie/area charts, data tables, activity timelines), per-user customizable layouts backed by GridStack, and auto-refresh intervals. The system integrates with the Nextcloud Dashboard Widget API (`OCP\Dashboard\IWidget`) to surface key metrics on the Nextcloud home screen. Each app implements its own domain-specific widgets while sharing the layout engine, chart library, and persistence patterns defined here. This complements the `rapportage-bi-export` spec by offering lightweight built-in visualization for quick data insights and the `production-observability` spec by providing a visual frontend for operational metrics.

**Source**: Gap identified in cross-platform analysis; four platforms (Baserow, NocoDB, Directus, Strapi) offer built-in dashboard builders.

## Requirements

### Requirement: Default Dashboard with System KPIs
The system SHALL provide a default dashboard that displays key performance indicators (KPIs) for the entire OpenRegister deployment. The dashboard MUST be available to every authenticated user on first visit, using the `DashboardService.getRegistersWithSchemas()` method for system-level statistics and `DashboardService.getStats()` for per-register/per-schema aggregation. KPIs MUST include register count, schema count, total object count, invalid/deleted/locked object counts, audit trail totals, and webhook log totals. The KPI row MUST use `CnKpiGrid` with `CnStatsBlock` components from `@conduction/nextcloud-vue`.

#### Scenario: First-time user sees default KPIs
- **GIVEN** a user navigates to the OpenRegister Dashboard view for the first time
- **WHEN** the `DashboardIndex.vue` component mounts and calls `dashboardStore.preload()`
- **THEN** the dashboard MUST render with the default layout (`DEFAULT_LAYOUT`) containing at minimum 4 KPI counter widgets across the top row (gridWidth=3 each, gridHeight=2)
- **AND** each KPI widget MUST display a numeric value, a descriptive label, and an icon using the `CnStatsBlock` pattern (icon circle + count + label)

#### Scenario: KPI widgets display live register statistics
- **GIVEN** the system contains 3 registers, 12 schemas, and 8,500 total objects
- **WHEN** `DashboardService.getRegistersWithSchemas()` returns the aggregated stats with `id: 'totals'`
- **THEN** the KPI widgets MUST show: `3` registers, `12` schemas, `8,500` objects, and the breakdown (invalid, deleted, locked counts) from `objectStats`

#### Scenario: KPI widgets display search trail statistics
- **GIVEN** the `searchTrailStore.fetchStatistics()` returns `total: 1250`, `successRate: 0.92`, `averageExecutionTime: 145`
- **WHEN** the dashboard renders the search KPI widgets
- **THEN** the widgets MUST show `1,250` total searches, `92.0%` success rate, `145ms` avg response time, and the unique search terms count

#### Scenario: Default KPIs adapt when no data exists
- **GIVEN** a fresh OpenRegister installation with zero registers, schemas, and objects
- **WHEN** the dashboard loads
- **THEN** all KPI widgets MUST display `0` with appropriate empty labels (not loading spinners or errors)
- **AND** the `emptyLabel` prop on `CnDashboardPage` MUST show the translated "No data available" message

### Requirement: Widget Types
The system SHALL support multiple widget types that users can place on dashboards. Each widget type MUST be registered as a widget definition with a unique `id`, `title`, and `type` in the `widgetDefs` array consumed by `CnDashboardPage`. The following widget types MUST be available: counter (single KPI number), chart (bar, line, pie, area, donut via `CnChartWidget` and ApexCharts), data table (tabular rows with sortable columns), and activity timeline (recent audit trail entries). All chart widgets MUST use the `CnChartWidget` component which wraps ApexCharts with Nextcloud-themed defaults (CSS variable colors, system font, transparent background).

#### Scenario: Counter widget displays a single KPI
- **GIVEN** a counter widget is configured with data source = schema `meldingen`, metric = count, filter = `status: open`
- **WHEN** the widget queries the `DashboardService.getStats()` endpoint with `registerId` and `schemaId` parameters
- **THEN** the widget MUST render a `CnStatsBlock` with the count value, the configured label, and the configured icon
- **AND** the count MUST be formatted with locale-aware number separators (`toLocaleString()`)

#### Scenario: Bar chart widget groups objects by a field
- **GIVEN** a bar chart widget configured with data source = schema `meldingen`, group by = `status`, metric = count
- **WHEN** the widget queries `DashboardController.getObjectsBySchemaChart()` and transforms the `labels`/`series` response
- **THEN** a `CnChartWidget` with `type="bar"` MUST render showing one bar per status value
- **AND** the chart MUST use the Nextcloud-themed color palette defined in `CnChartWidget.defaultColors`

#### Scenario: Line chart widget shows time series
- **GIVEN** a line chart widget configured with data source = audit trail actions, date range = last 30 days
- **WHEN** the widget queries `DashboardController.getAuditTrailActionChart()` with `from` and `till` parameters
- **THEN** a `CnChartWidget` with `type="line"` MUST render with the x-axis showing dates and series showing create/update/delete counts per day

#### Scenario: Pie chart widget shows distribution
- **GIVEN** a pie chart widget configured to show objects by register
- **WHEN** the widget queries `DashboardController.getObjectsByRegisterChart()`
- **THEN** a `CnChartWidget` with `type="pie"` MUST render with `labels` from register names and `series` from object counts
- **AND** data labels MUST be enabled showing percentages (ApexCharts `dataLabels.enabled: true` for pie types)

#### Scenario: Data table widget shows top records
- **GIVEN** a data table widget configured to show most active objects with limit = 10
- **WHEN** the widget queries `DashboardController.getMostActiveObjects()` with `limit=10` and `hours=24`
- **THEN** a sortable HTML table MUST render with columns: Object ID, Name, Activity Count
- **AND** the table MUST follow the `stats-table` styling pattern from `DashboardIndex.vue`

### Requirement: Drag-and-Drop Layout with GridStack
Dashboard widgets MUST be placeable and resizable on a responsive 12-column grid using GridStack, implemented via `CnDashboardGrid` from `@conduction/nextcloud-vue`. The grid MUST support drag-and-drop repositioning, resize handles, minimum widget dimensions (2x2 grid units), float mode for free placement, and animated transitions. Layout changes MUST be captured via the `@layout-change` event from `CnDashboardPage` and persisted per user.

#### Scenario: User drags a widget to a new position
- **GIVEN** a dashboard with 4 widgets in the default layout
- **WHEN** the user enters edit mode (clicking the edit toggle button on `CnDashboardPage`) and drags the "Objects by Register" widget from position `gridX:6, gridY:2` to `gridX:0, gridY:2`
- **THEN** the GridStack `change` event MUST fire with the updated item positions
- **AND** `CnDashboardGrid.handleGridChange()` MUST emit `layout-change` with the updated layout array
- **AND** other widgets MUST reflow automatically (GridStack `float: true` allows free placement)

#### Scenario: User resizes a widget
- **GIVEN** a chart widget currently sized at `gridWidth:6, gridHeight:4`
- **WHEN** the user drags the resize handle to expand it to `gridWidth:12, gridHeight:6`
- **THEN** the widget MUST snap to the new grid dimensions
- **AND** the `CnChartWidget` inside MUST re-render to fill the new container size (ApexCharts `width: '100%'` responsive)
- **AND** the minimum size constraint (`gs-min-w="2"`, `gs-min-h="2"`) MUST be enforced

#### Scenario: Edit mode toggle controls drag/resize
- **GIVEN** the dashboard is in view mode (default, `isEditing: false`)
- **WHEN** the user clicks the edit button in `CnDashboardPage` header
- **THEN** `CnDashboardGrid` MUST call `grid.enable()` to allow drag and resize
- **AND** widget headers MUST show edit/remove action buttons (via `CnWidgetWrapper` edit mode)
- **AND** clicking "Done" MUST call `grid.disable()` and persist the final layout

#### Scenario: Widgets reflow on smaller viewports
- **GIVEN** a 12-column dashboard layout on a 1440px-wide screen
- **WHEN** the viewport is resized to 768px (tablet)
- **THEN** `CnKpiGrid` MUST collapse from 4 columns to 2 columns (per its `@media (max-width: 1200px)` rule)
- **AND** the GridStack grid MUST remain scrollable and all widgets MUST be accessible

### Requirement: Per-User Dashboard Layout Persistence
Each user's dashboard layout MUST be saved and restored across sessions. The layout definition (widget positions, sizes, visibility) MUST be stored using Nextcloud's user preferences API (`OCP\IConfig::setUserValue`) or as an OpenRegister object in a system register. The `useDashboardView` composable from `@conduction/nextcloud-vue` provides the `loadLayout` and `saveLayout` callback pattern for this persistence.

#### Scenario: Layout persists across page navigations
- **GIVEN** user `admin` rearranges their dashboard by moving the KPI row to the bottom
- **WHEN** `CnDashboardPage` emits `@layout-change` with the updated layout array
- **THEN** the `saveLayout` callback MUST persist the layout JSON to Nextcloud user preferences via `PUT /api/dashboard/layout`
- **AND** when user `admin` navigates away and returns, `loadLayout` MUST restore the saved arrangement

#### Scenario: New user receives default layout
- **GIVEN** user `medewerker-1` has never visited the dashboard before
- **WHEN** the dashboard loads and `loadLayout` returns `null` or an empty array
- **THEN** the `useDashboardView` composable MUST fall back to `defaultLayout` (the `DEFAULT_LAYOUT` constant)
- **AND** the default layout MUST NOT be saved until the user explicitly modifies it

#### Scenario: Layout reset to defaults
- **GIVEN** user `admin` has a customized layout with 3 widgets removed
- **WHEN** the user clicks a "Reset to defaults" button in the dashboard header
- **THEN** the layout MUST revert to `DEFAULT_LAYOUT`
- **AND** the reset layout MUST be persisted immediately via `saveLayout`

### Requirement: Dashboard Data Auto-Refresh
Dashboard widgets MUST periodically refresh their data to show current information without requiring a full page reload. Each widget MAY have an individual refresh interval, and the dashboard MUST support a global manual refresh action. The refresh mechanism MUST use non-disruptive data fetching (background API calls) that updates widget content without resetting scroll position or edit state.

#### Scenario: Global auto-refresh at configurable interval
- **GIVEN** the dashboard auto-refresh is set to 60 seconds (configurable via dashboard settings)
- **WHEN** 60 seconds elapse since the last data fetch
- **THEN** all widgets MUST re-query their data sources (`dashboardStore.preload()`, `dashboardStore.fetchAllChartData()`, `searchTrailStore.fetchStatistics()`)
- **AND** the refresh MUST be non-disruptive: no full page reload, no layout reset, no scroll position change
- **AND** a subtle refresh indicator (spinning icon or timestamp) MUST show the last-refreshed time

#### Scenario: Manual refresh via button
- **GIVEN** a dashboard with stale data from 5 minutes ago
- **WHEN** the user clicks the refresh button in the `#header-actions` slot (the `NcButton` with `Refresh` icon in `DashboardIndex.vue`)
- **THEN** `refreshDashboard()` MUST call all data-fetching methods and set `refreshing: true` during the operation
- **AND** the refresh button MUST show `NcLoadingIcon` while refreshing and restore the `Refresh` icon when done

#### Scenario: Auto-refresh pauses when tab is not visible
- **GIVEN** auto-refresh is configured at 30-second intervals
- **WHEN** the user switches to another browser tab
- **THEN** the refresh timer MUST pause (using `document.visibilitychange` event)
- **AND** when the user returns, an immediate refresh MUST trigger followed by resumption of the regular interval

### Requirement: RBAC-Filtered Dashboard Data
Dashboard widgets MUST only display data that the viewing user is authorized to access. Data filtering MUST be enforced server-side by passing the authenticated user context through `DashboardService` to the underlying mappers (`MagicMapper`, `AuditTrailMapper`) which respect register/schema authorization rules. No client-side filtering of unauthorized data SHALL be relied upon.

#### Scenario: Non-admin user sees only permitted registers
- **GIVEN** a shared dashboard with a widget showing "Objects by Register" for all registers
- **AND** user `medewerker-1` has access to register `zaken` but not register `vertrouwelijk`
- **WHEN** `medewerker-1` views the dashboard
- **THEN** `DashboardController.getObjectsByRegisterChart()` MUST return only registers the user can access
- **AND** the chart widget MUST show data for `zaken` only, with no trace of `vertrouwelijk` data

#### Scenario: Admin user sees all data including orphaned items
- **GIVEN** the admin views the dashboard
- **WHEN** `DashboardService.getRegistersWithSchemas()` returns the full list including `id: 'orphaned'`
- **THEN** the admin dashboard MUST show the "Orphaned Items" statistics section
- **AND** non-admin users MUST NOT see orphaned item statistics

#### Scenario: RBAC applies consistently across all widget types
- **GIVEN** a dashboard with a KPI counter, a bar chart, and a data table all querying schema `meldingen`
- **AND** user `medewerker-1` has read access to `meldingen` but not `vertrouwelijk`
- **WHEN** all three widgets fetch data
- **THEN** all three widgets MUST show consistent counts (same total, same distribution) reflecting only `meldingen` data
- **AND** there SHALL be no discrepancy between the KPI total and the chart/table totals

### Requirement: Nextcloud Dashboard Widget API Integration
The system SHALL register one or more `OCP\Dashboard\IWidget` implementations that surface key OpenRegister metrics on the Nextcloud home dashboard. This provides a quick overview without navigating to the OpenRegister app. The widget MUST use the `IAPIWidget` interface (supporting API versions 1 and 2) so that the Nextcloud dashboard can fetch widget items via AJAX. The `CnWidgetRenderer` component from `@conduction/nextcloud-vue` automatically renders these NC API widgets when `itemApiVersions` is provided in the widget definition.

#### Scenario: OpenRegister widget appears on Nextcloud dashboard
- **GIVEN** an admin has enabled the OpenRegister app
- **WHEN** the admin visits the Nextcloud home dashboard
- **THEN** an "OpenRegister Overview" widget MUST be available in the widget picker
- **AND** when added, it MUST display: total objects count, total registers, total schemas, and a "View Dashboard" link to the full OpenRegister dashboard

#### Scenario: NC dashboard widget shows recent activity
- **GIVEN** the OpenRegister NC dashboard widget is enabled
- **WHEN** the Nextcloud dashboard calls the widget's API v2 endpoint
- **THEN** the response MUST include up to 7 recent audit trail entries (creates/updates/deletes) formatted as dashboard items with title, subtitle (action type + timestamp), and link to the object detail view

#### Scenario: NC dashboard widget respects reload interval
- **GIVEN** the widget is registered with `reloadInterval: 300` (5 minutes)
- **WHEN** 5 minutes elapse on the Nextcloud dashboard
- **THEN** the Nextcloud dashboard framework MUST re-fetch the widget items
- **AND** the displayed data MUST reflect any objects created or modified in the interim

### Requirement: Chart Library Integration (ApexCharts)
All chart visualizations MUST use ApexCharts via the `CnChartWidget` component from `@conduction/nextcloud-vue`. The supported chart types MUST include: `area`, `line`, `bar`, `pie`, `donut`, and `radialBar`. Charts MUST use Nextcloud-themed colors from CSS variables (`--color-primary-element`, `--color-success`, `--color-warning`, `--color-error`), the system font (`--default-font`), and transparent backgrounds. ApexCharts MUST be lazy-loaded via dynamic `import('vue-apexcharts')` to avoid increasing the initial bundle size.

#### Scenario: ApexCharts loads asynchronously on first chart render
- **GIVEN** the dashboard contains a pie chart widget
- **WHEN** `CnChartWidget.created()` hook runs and calls `import('vue-apexcharts')`
- **THEN** the chart component MUST load asynchronously without blocking the dashboard render
- **AND** while loading, the chart area MUST show a fallback state (not a blank space)

#### Scenario: Chart adapts to dark mode
- **GIVEN** the user has enabled Nextcloud dark mode
- **WHEN** a chart widget renders
- **THEN** chart text MUST use `var(--color-main-text)` for legibility
- **AND** grid lines MUST use `var(--color-border)` for consistent theming
- **AND** the chart background MUST remain transparent (inheriting from the widget container)

#### Scenario: Chart toolbar enables PNG/SVG download
- **GIVEN** a chart widget with `toolbar: true` enabled
- **WHEN** the user clicks the toolbar download button
- **THEN** ApexCharts MUST offer PNG and SVG download options for the chart visualization

#### Scenario: Fallback when ApexCharts is not installed
- **GIVEN** the consuming app has not installed `apexcharts` and `vue-apexcharts` dependencies
- **WHEN** `CnChartWidget.created()` catches the import error
- **THEN** the widget MUST display the `unavailableLabel` text ("Chart library not available") in the fallback slot
- **AND** the error MUST be logged to console as a warning (not an uncaught exception)

### Requirement: Per-Register Dashboards
Each register MUST have its own dedicated dashboard view showing statistics specific to that register and its schemas. The per-register dashboard MUST display: object counts per schema, size distribution, audit trail activity over time, and the most active objects. The data MUST be fetched via `DashboardService.getRegistersWithSchemas(registerId)` and the chart endpoints with `registerId` filter.

#### Scenario: Navigating to a register dashboard
- **GIVEN** the user is viewing register `zaken` with schemas `meldingen` and `vergunningen`
- **WHEN** the user clicks a "Dashboard" tab or button on the register detail page
- **THEN** a per-register dashboard MUST load showing KPIs scoped to register `zaken` only
- **AND** charts MUST show objects by schema (meldingen vs vergunningen), audit trail activity filtered to this register, and size distribution for this register's objects

#### Scenario: Per-register dashboard shows schema-level breakdown
- **GIVEN** register `zaken` has 500 meldingen and 200 vergunningen objects
- **WHEN** the per-register dashboard loads
- **THEN** the KPI row MUST show `700` total objects for this register
- **AND** a bar/pie chart MUST show the breakdown: `meldingen: 500`, `vergunningen: 200`

#### Scenario: Per-register dashboard shows orphaned objects for admin
- **GIVEN** an admin views the per-register dashboard for register `zaken`
- **WHEN** `DashboardService.getRegistersWithSchemas(registerId)` is called
- **THEN** orphaned object statistics (referencing non-existent schemas) MUST be shown if applicable
- **AND** the admin MUST see a "Recalculate Sizes" action that calls `DashboardService.recalculateAllSizes(registerId)`

### Requirement: Admin vs User Dashboards
The system SHALL differentiate between admin-level dashboards and regular user dashboards. Admin dashboards MUST include system health indicators, orphaned item counts, webhook log statistics, and size recalculation actions. Regular user dashboards MUST show only the data the user is authorized to see, without administrative actions. The distinction MUST be enforced by the `@NoAdminRequired` annotations on controller methods and server-side RBAC filtering.

#### Scenario: Admin dashboard shows system health metrics
- **GIVEN** an admin user views the dashboard
- **WHEN** `DashboardService.getRegistersWithSchemas()` returns the full data set
- **THEN** the admin dashboard MUST include: System Totals (id: 'totals'), all registers with schemas, and Orphaned Items (id: 'orphaned')
- **AND** the admin MUST see action buttons: "Recalculate Sizes" (calls `DashboardController.calculate()`), "Refresh All"

#### Scenario: Regular user dashboard hides admin-only widgets
- **GIVEN** a regular user `medewerker-1` views the dashboard
- **WHEN** the dashboard loads
- **THEN** the "Orphaned Items" section MUST NOT be visible
- **AND** "Recalculate Sizes" and other admin actions MUST NOT appear
- **AND** webhook log statistics MUST be hidden (only relevant for system administrators)

#### Scenario: Admin can view dashboard as another user
- **GIVEN** an admin wants to verify what `medewerker-1` sees on their dashboard
- **WHEN** the admin uses an "impersonate view" option (if implemented)
- **THEN** the dashboard MUST render with RBAC filters applied as if `medewerker-1` were viewing it
- **AND** this MUST be a read-only preview (no layout changes persisted to `medewerker-1`)

### Requirement: Responsive Mobile Layout
The dashboard MUST be fully usable on mobile devices and tablets. The grid layout MUST adapt responsively: collapsing from multi-column to single-column on narrow viewports. KPI cards MUST stack vertically on mobile. Charts MUST remain readable at smaller sizes. The dashboard MUST NOT require horizontal scrolling on any viewport width >= 320px.

#### Scenario: KPI grid collapses on mobile
- **GIVEN** the KPI row uses `CnKpiGrid` with `columns=4` (default)
- **WHEN** the viewport width is below 600px
- **THEN** the CSS media query `@media (max-width: 600px)` MUST apply `grid-template-columns: 1fr`
- **AND** all KPI cards MUST stack vertically in a single column

#### Scenario: Dashboard usable on 768px tablet
- **GIVEN** a dashboard with 8 widgets in a 12-column layout
- **WHEN** the viewport is 768px wide
- **THEN** `CnKpiGrid--cols-4` MUST collapse to 2 columns (per `@media (max-width: 1200px)`)
- **AND** chart widgets MUST remain readable with ApexCharts responsive sizing (`width: '100%'`)
- **AND** the user MUST be able to scroll vertically to see all widgets

#### Scenario: Drag-and-drop disabled on touch devices in view mode
- **GIVEN** the user views the dashboard on a touch device
- **WHEN** the dashboard is in view mode (not editing)
- **THEN** GridStack MUST have drag and resize disabled (`disableDrag: true, disableResize: true`)
- **AND** normal scroll/swipe gestures MUST work without accidentally moving widgets

### Requirement: NL Design System Theming
All dashboard components MUST be compatible with NL Design System theming via the `nldesign` app. Dashboards MUST use Nextcloud CSS variables exclusively (`--color-primary-element`, `--color-border`, `--color-main-background`, `--color-text-maxcontrast`, etc.) and MUST NOT hardcode colors. The `nldesign` app overrides these Nextcloud variables with municipality-specific values, so theming works automatically without any dashboard-specific code.

#### Scenario: Municipality theme applies to dashboard
- **GIVEN** the `nldesign` app is installed with the "Den Haag" theme that sets `--color-primary-element: #00811f`
- **WHEN** the dashboard renders with KPI cards and charts
- **THEN** `CnStatsBlock` icon circles MUST use `var(--color-primary-element-light)` background
- **AND** `CnChartWidget` MUST use `var(--color-primary-element)` as the first chart color
- **AND** no hardcoded hex colors SHALL appear in the rendered dashboard

#### Scenario: Dark mode theming works with NL Design
- **GIVEN** the user has enabled Nextcloud dark mode and the `nldesign` app provides dark-mode overrides
- **WHEN** the dashboard renders
- **THEN** widget backgrounds MUST use `var(--color-main-background)` (which is dark in dark mode)
- **AND** text MUST use `var(--color-main-text)` for readability
- **AND** grid placeholder styling (`--color-primary-element-light` dashed border) MUST remain visible

#### Scenario: WCAG AA contrast compliance
- **GIVEN** any NL Design System theme is applied
- **WHEN** the dashboard renders with KPI values, chart labels, and table text
- **THEN** all text MUST meet WCAG 2.1 AA minimum contrast ratio (4.5:1 for normal text, 3:1 for large text)
- **AND** chart data labels and axis labels MUST be legible against the chart background

### Requirement: Performance and Lazy Loading
Dashboard widgets MUST load efficiently to avoid blocking the initial page render. Chart libraries (ApexCharts) MUST be lazy-loaded via dynamic `import()`. Widget data MUST be fetched in parallel (not sequentially). The dashboard page MUST render a loading skeleton or `NcLoadingIcon` while data is being fetched. The `CnDashboardPage` component's `loading` prop controls the loading state display.

#### Scenario: Dashboard initial load under 2 seconds
- **GIVEN** a dashboard with 8 widgets and moderate data volumes (< 10,000 objects)
- **WHEN** the user navigates to the dashboard
- **THEN** the page skeleton (header + empty grid placeholders) MUST render within 500ms
- **AND** all widget data MUST load and render within 2 seconds on a standard connection
- **AND** `CnDashboardPage` MUST show `NcLoadingIcon` while `loading: true`

#### Scenario: Chart library lazy-loaded on first chart widget
- **GIVEN** the user has a dashboard with only KPI counter widgets (no charts)
- **WHEN** the dashboard loads
- **THEN** the ApexCharts bundle MUST NOT be loaded (no unnecessary JavaScript)
- **AND** when the user adds a chart widget, `CnChartWidget.created()` MUST trigger `import('vue-apexcharts')` at that point

#### Scenario: Parallel data fetching for independent widgets
- **GIVEN** the dashboard has KPI, chart, and table widgets
- **WHEN** the dashboard mounts
- **THEN** `dashboardStore.preload()`, `dashboardStore.fetchAllChartData()`, and `searchTrailStore.fetchStatistics()` MUST execute in parallel (via `Promise.all` or concurrent calls)
- **AND** each widget MUST render independently as its data arrives (progressive rendering)

### Requirement: Dashboard-Level Filters
Users MUST be able to apply dashboard-level filters that affect all widgets simultaneously. Supported filters MUST include: date range (from/till), register selector, and schema selector. Filters MUST propagate to all widget data queries as parameters (`registerId`, `schemaId`, `from`, `till`) passed to `DashboardController` endpoints.

#### Scenario: Date range filter affects all widgets
- **GIVEN** a dashboard with 4 widgets showing audit trail and object data
- **WHEN** the user applies a date range filter: `from: 2026-03-01` to `till: 2026-03-31`
- **THEN** `DashboardController.getAuditTrailActionChart()` MUST receive `from=2026-03-01&till=2026-03-31`
- **AND** all chart and table widgets MUST update to show only data from March 2026
- **AND** KPI counters MUST reflect the filtered period

#### Scenario: Register filter scopes all widgets
- **GIVEN** a dashboard showing data from all registers
- **WHEN** the user selects register `zaken` from the register filter dropdown
- **THEN** all widget data queries MUST include `registerId={zakenId}`
- **AND** the KPI totals MUST show only `zaken` register counts
- **AND** the "Objects by Schema" chart MUST show only schemas belonging to `zaken`

#### Scenario: Filters persist in URL
- **GIVEN** the user applies register and date range filters
- **WHEN** the URL updates with query parameters (e.g., `?registerId=1&from=2026-03-01`)
- **THEN** sharing or bookmarking the URL MUST restore the same filter state
- **AND** navigating back MUST also restore the previous filter state

### Requirement: Dashboard Sharing
Users MUST be able to share dashboard configurations with other users or groups. Shared dashboards MUST be read-only for recipients by default, with the owner retaining full edit control. The sharing mechanism MUST integrate with Nextcloud's group system (`OCP\IGroupManager`).

#### Scenario: Share dashboard with a group
- **GIVEN** user `manager` creates a dashboard `KPI Overzicht` with a custom layout
- **WHEN** `manager` shares the dashboard with group `directie`
- **THEN** all users in `directie` MUST see `KPI Overzicht` in their dashboard list
- **AND** shared users MUST see the same widget layout as the owner
- **AND** shared users MUST NOT be able to modify the shared layout (read-only)

#### Scenario: Owner can revoke sharing
- **GIVEN** dashboard `KPI Overzicht` is shared with group `directie`
- **WHEN** `manager` removes the sharing
- **THEN** users in `directie` MUST no longer see `KPI Overzicht` in their dashboard list
- **AND** the dashboard MUST remain accessible to `manager`

#### Scenario: Shared dashboard respects viewer RBAC
- **GIVEN** dashboard `KPI Overzicht` shows data from registers `zaken` and `vertrouwelijk`
- **AND** user `medewerker-1` in group `directie` does not have access to `vertrouwelijk`
- **WHEN** `medewerker-1` views the shared dashboard
- **THEN** widgets MUST show data from `zaken` only (server-side RBAC filtering)
- **AND** the layout and widget configuration MUST remain the same (only the data is filtered)

### Requirement: Saved Dashboard Templates
The system SHALL support saving and loading dashboard templates that define a standard set of widgets and layout. Templates allow administrators to create reusable dashboard configurations for different roles (e.g., "Manager Overview", "Data Quality Monitor", "Search Analytics"). Templates MUST be importable/exportable as JSON for cross-environment portability.

#### Scenario: Admin creates a dashboard template
- **GIVEN** an admin has configured a dashboard with 8 widgets and a customized layout
- **WHEN** the admin clicks "Save as Template" and names it `Manager Overview`
- **THEN** the widget definitions and layout array MUST be saved as a template
- **AND** the template MUST be available in a "Templates" gallery for other users to apply

#### Scenario: User applies a template
- **GIVEN** user `medewerker-1` wants to use the `Manager Overview` template
- **WHEN** the user selects the template from the gallery
- **THEN** the user's dashboard layout MUST be replaced with the template's layout
- **AND** the user MAY customize the applied layout (it becomes their personal layout)

#### Scenario: Export/import template as JSON
- **GIVEN** an admin wants to transfer a dashboard template between environments (dev to production)
- **WHEN** the admin exports the template
- **THEN** a JSON file MUST be generated containing: `{ widgets: [...], layout: [...], name: "...", version: "..." }`
- **AND** importing this JSON on another instance MUST create the same dashboard template

## Current Implementation Status
- **Implemented -- DashboardService backend**: `DashboardService` (`lib/Service/DashboardService.php`) provides `getRegistersWithSchemas()` for register/schema aggregation with statistics (object counts, sizes, invalid/deleted/locked counts), `getStats()` for per-register/per-schema metrics, `getOrphanedStats()` for orphaned items, `recalculateAllSizes()` for data maintenance, and chart data methods: `getAuditTrailActionChartData()`, `getObjectsByRegisterChartData()`, `getObjectsBySchemaChartData()`, `getObjectsBySizeChartData()`, `getAuditTrailStatistics()`, `getAuditTrailActionDistribution()`, `getMostActiveObjects()`.
- **Implemented -- DashboardController API**: `DashboardController` (`lib/Controller/DashboardController.php`) exposes: `page()` (template rendering), `index()` (registers with schemas JSON), `calculate()` (size recalculation), `getAuditTrailActionChart()`, `getObjectsByRegisterChart()`, `getObjectsBySchemaChart()`, `getObjectsBySizeChart()`, `getAuditTrailStatistics()`, `getAuditTrailActionDistribution()`, `getMostActiveObjects()`. All endpoints have `@NoAdminRequired` and `@NoCSRFRequired`.
- **Implemented -- Frontend dashboard view**: `DashboardIndex.vue` (`src/views/dashboard/DashboardIndex.vue`) uses `CnDashboardPage` from `@conduction/nextcloud-vue` with 8 custom widgets (4 KPI counters, popular search terms table, objects by register/schema tables, objects distribution chart placeholder). Uses `DEFAULT_LAYOUT` constant with 12-column GridStack grid. Supports manual refresh via the refresh button. Search trail data integration via `searchTrailStore`.
- **Implemented -- Shared dashboard components**: `@conduction/nextcloud-vue` provides: `CnDashboardPage` (top-level page with header, edit toggle, loading/empty states), `CnDashboardGrid` (GridStack-powered grid engine), `CnWidgetWrapper` (widget container shell with header/content/footer), `CnWidgetRenderer` (NC Dashboard API widget renderer), `CnTileWidget` (quick-access tiles), `CnKpiGrid` (responsive KPI card grid with 2/3/4 columns), `CnStatsBlock` (KPI card with icon, count, breakdown), `CnChartWidget` (ApexCharts wrapper with Nextcloud theming).
- **Implemented -- Dashboard composable**: `useDashboardView` composable from `@conduction/nextcloud-vue` provides: widget definition management (app + NC API widgets), layout loading/saving with callbacks, add/remove widget methods, NC Dashboard API widget loading via OCS endpoint, edit mode state.
- **Implemented -- MetricsService**: `MetricsService` (`lib/Service/MetricsService.php`) provides `getDashboardMetrics()` combining files processed, embedding stats, search latency, and storage growth data. Includes 90-day retention cleanup via `cleanOldMetrics()`.
- **Implemented -- Prometheus metrics endpoint**: `MetricsController` (`lib/Controller/MetricsController.php`) exposes `/api/metrics` with register/schema/object counts in Prometheus format.
- **Partially implemented -- Chart rendering**: `CnChartWidget` exists in `@conduction/nextcloud-vue` with full ApexCharts integration, but `DashboardIndex.vue` has the chart widget commented out with a TODO note ("CnChartWidget does not exist yet"). The component does exist but may not be published/exported yet.
- **NOT implemented -- per-user layout persistence**: `DashboardIndex.vue` uses a local `dashboardLayout` data property; changes are not persisted to Nextcloud user preferences or any backend storage. The `useDashboardView` composable supports `loadLayout`/`saveLayout` callbacks but they are not wired up.
- **NOT implemented -- custom dashboard creation**: Users cannot create multiple named dashboards. Only the single default dashboard exists.
- **NOT implemented -- dashboard sharing**: No sharing mechanism between users or groups.
- **NOT implemented -- dashboard-level filters**: No date range, register, or schema filter controls on the dashboard page.
- **NOT implemented -- dashboard templates**: No template save/load/export/import functionality.
- **NOT implemented -- Nextcloud IWidget registration**: No `OCP\Dashboard\IWidget` implementation exists. OpenRegister does not register widgets on the Nextcloud home dashboard.
- **NOT implemented -- auto-refresh timer**: Manual refresh exists but no automatic periodic refresh with configurable interval.
- **NOT implemented -- per-register dashboards**: No register-scoped dashboard view (the `registerId`/`schemaId` filter parameters exist on the API but are not exposed in the frontend).
- **NOT implemented -- admin vs user dashboard differentiation**: All widgets are shown to all users regardless of role.

## Standards & References
- **Nextcloud Dashboard API** -- `OCP\Dashboard\IWidget`, `OCP\Dashboard\IAPIWidget`, `OCP\Dashboard\IAPIWidgetV2` for home dashboard widget registration
- **Nextcloud User Preferences** -- `OCP\IConfig::setUserValue`/`getUserValue` for per-user layout storage
- **GridStack** -- `gridstack` npm package for drag-and-drop grid layout (used by `CnDashboardGrid`)
- **ApexCharts** -- `apexcharts` + `vue-apexcharts` for chart rendering (used by `CnChartWidget`); peer dependency pattern (apps install it, shared lib wraps it)
- **WCAG 2.1 AA** -- Accessibility requirements for data visualizations (contrast ratios, keyboard navigation, screen reader compatibility)
- **NL Design System** -- Dutch government theming via CSS variable overrides (no direct `--nldesign-*` references; theming works through Nextcloud's own CSS variables)
- **W3C WAI-ARIA** -- Accessibility patterns for interactive dashboard widgets (roles, states, properties)
- **Cross-reference: `production-observability`** -- Prometheus metrics, health endpoints, and `MetricsService` data feed dashboard KPIs
- **Cross-reference: `no-code-app-builder`** -- Dashboard components (charts, tables) reused as no-code app page components; `DashboardService` feeds chart component data
- **Cross-reference: `rapportage-bi-export`** -- Built-in dashboards provide lightweight visualization; BI export covers enterprise-grade reporting

## Specificity Assessment
- The spec is well-grounded in existing infrastructure: `DashboardService` and `DashboardController` provide 10+ chart/statistics API endpoints, `CnDashboardPage` and related components handle rendering, and the `useDashboardView` composable manages state.
- The primary gaps are in persistence (layout saving), user management (multi-dashboard, sharing, templates), and frontend filter controls. The backend already supports `registerId`/`schemaId`/`from`/`till` filtering; only the UI needs to be built.
- The `CnChartWidget` integration needs unblocking: the component exists in `@conduction/nextcloud-vue` but the import in `DashboardIndex.vue` is commented out due to a TODO flag.
- Open questions resolved: (1) ApexCharts is the chart library (confirmed by `CnChartWidget`); (2) GridStack is the grid engine (confirmed by `CnDashboardGrid`); (3) dashboards are an in-app feature with optional NC home widget via `IWidget`; (4) layout persistence should use `useDashboardView` callbacks with Nextcloud user preferences.
