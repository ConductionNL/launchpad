# Tasks: clickable-kpi-widgets

## Phase 1: Shared Component (nextcloud-vue)

- [x] Add `route` prop to `CnStatsBlock` (`Object|null`, default `null`)
- [x] Update `componentTag` computed to return `'router-link'` when `route` is set
- [x] Update `componentAttrs` computed to pass `{ to: route }` for router-link
- [x] Update `isInteractive` / `rootClasses` to apply clickable styles when `route` is set
- [x] Ensure `@click` emit is NOT fired when navigating via `route` (router-link handles it)
- [ ] Add unit test: route prop renders `<router-link>` with correct `to`
- [ ] Add unit test: no route + clickable renders `<a>` with `@click` emit (backward compat)
- [ ] Add unit test: no route + no clickable renders `<div>` (static)
- [x] Update CnStatsBlock JSDoc with `route` prop example
- [ ] Publish updated `@conduction/nextcloud-vue`

## Phase 2: App Migrations

### Procest (`procest/src/views/Dashboard.vue`)
- [x] Replace inline `<div class="kpi-card">` in `#widget-count-open-cases` slot with `<CnStatsBlock>`
- [x] Replace inline `<div class="kpi-card">` in `#widget-count-overdue` slot with `<CnStatsBlock>`
- [x] Replace inline `<div class="kpi-card">` in `#widget-count-completed` slot with `<CnStatsBlock>`
- [x] Replace inline `<div class="kpi-card">` in `#widget-count-my-tasks` slot with `<CnStatsBlock>`
- [x] Configure route for "Open Cases" → `{ name: 'Cases', query: { status: 'open' } }`
- [x] Configure route for "Overdue" → `{ name: 'Cases', query: { overdue: 'true' } }`
- [x] Configure route for "Completed This Month" → `{ name: 'Cases', query: { status: 'completed' } }`
- [x] Configure route for "My Tasks" → `{ name: 'Tasks' }`
- [x] Delete duplicated `.kpi-card` / `.kpi-icon` / `.kpi-content` CSS from Dashboard.vue
- [ ] Delete or deprecate unused `src/views/dashboard/KpiCards.vue`

### Pipelinq (`pipelinq/src/views/Dashboard.vue`)
- [x] Replace inline `<div class="kpi-card">` in `#widget-count-open-leads` slot with `<CnStatsBlock>`
- [x] Replace inline `<div class="kpi-card">` in `#widget-count-open-requests` slot with `<CnStatsBlock>`
- [x] Replace inline `<div class="kpi-card">` in `#widget-count-pipeline-value` slot with `<CnStatsBlock>`
- [x] Replace inline `<div class="kpi-card">` in `#widget-count-overdue` slot with `<CnStatsBlock>`
- [x] Configure route for "Open Leads" → `{ name: 'Leads', query: { status: 'open' } }`
- [x] Configure route for "Open Requests" → `{ name: 'Requests', query: { status: 'open' } }`
- [x] Configure route for "Pipeline Value" → `{ name: 'Pipeline' }` (note: currency formatting needs countLabel workaround)
- [x] Configure route for "Overdue" → `{ name: 'Leads', query: { overdue: 'true' } }`
- [x] Delete duplicated `.kpi-card` / `.kpi-icon` / `.kpi-content` CSS from Dashboard.vue

### OpenCatalogi (`opencatalogi/src/views/dashboard/Dashboard.vue`)
- [x] Add `name` properties to routes in `opencatalogi/src/router/index.js` (already present)
- [x] Replace inline `<div class="kpi-card">` in `#widget-count-catalogs` slot with `<CnStatsBlock>`
- [x] Replace inline `<div class="kpi-card">` in `#widget-count-publications` slot with `<CnStatsBlock>`
- [x] Replace inline `<div class="kpi-card">` in `#widget-count-concept-publications` slot with `<CnStatsBlock>`
- [x] Replace inline `<div class="kpi-card">` in `#widget-count-concept-attachments` slot with `<CnStatsBlock>`
- [x] Configure route for "Catalogs" → `{ name: 'Catalogs' }`
- [x] Configure route for "Publications" → `{ name: 'Catalogs' }` (aggregate — no single catalog slug available)
- [x] Configure route for "Concept Publications" → `{ name: 'Catalogs' }` (no filtered view available yet)
- [x] Configure route for "Concept Attachments" → omitted (no attachments list view exists)
- [x] Delete duplicated `.kpi-card` / `.kpi-icon` / `.kpi-content` CSS from Dashboard.vue

### MyDash — Deferred
- [x] ~~Replace inline KPI in dashboard with CnKpiGrid + CnStatsBlock + routes~~ — MyDash has no KPI cards; it is a generic dashboard builder. No migration needed.

### LarpingApp — Deferred
- [x] ~~Replace inline KPI in Dashboard.vue with CnKpiGrid + CnStatsBlock + routes~~ — LarpingApp has a stub dashboard with no KPI data. Defer until dashboard is implemented.

## Phase 3: Verification

- [ ] Verify each app's KPI cards navigate to the correct list view with pre-applied filters
- [ ] Verify keyboard navigation (Tab → Enter) works on all KPI cards
- [ ] Verify KPI cards without routes remain static (no regression)
- [ ] Verify NL Design theming applies correctly to KPI cards
