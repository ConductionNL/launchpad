---
status: draft
source: competitive-analysis
competitor: valtimo
analyzed_date: 2026-03-13
---
# Dashboard System -- Valtimo

## Purpose
Provides configurable dashboards with statistical widgets for monitoring case volumes, process performance, and KPIs. Dashboards give users an at-a-glance overview of their work and help management track organizational performance.

## Architecture Overview
- **Backend module**: `dashboard/` (Kotlin, Spring Boot)
- **Frontend modules**: `dashboard/` (display) and `dashboard-management/` (configuration) Angular libraries
- **Widget architecture**: Pluggable data sources + display types combined into widgets
- **Access control**: Dashboard visibility governed by PBAC permissions

## Data Model

### Dashboard
| Field | Type | Description |
|-------|------|-------------|
| id | UUID | Unique dashboard ID |
| key | String | Dashboard identifier |
| title | String | Display name |
| description | String | Dashboard description |
| order | Integer | Tab ordering |

### WidgetConfiguration
| Field | Type | Description |
|-------|------|-------------|
| id | UUID | Unique widget ID |
| dashboardId | UUID | Parent dashboard reference |
| title | String | Widget display name |
| key | String | Widget identifier |
| dataSourceKey | String | Registered data source type |
| dataSourceProperties | JSON | Configuration for the data source |
| displayTypeKey | String | Visualization type (bar, number, meter, gauge) |
| displayTypeProperties | JSON | Display configuration (colors, thresholds) |
| order | Integer | Widget ordering within dashboard |

## Business Logic

### Widget Data Resolution
1. Dashboard loaded -- widget configurations fetched
2. For each widget, `WidgetDataSourceResolver` finds the registered data source by key
3. Data source executes its query with configured properties (e.g., case counts, process durations)
4. Results passed to the display type renderer
5. Frontend renders the appropriate visualization

### Display Types
| Type | Description |
|------|-------------|
| Bar chart | Horizontal/vertical bar charts for comparisons |
| Number | Single large number (e.g., "42 open cases") |
| Meter | Progress meter with min/max |
| Gauge | Radial gauge with severity zones |
| Custom | Extensible via custom Angular components |

### Threshold/Severity Support
Widgets support threshold configuration for KPI monitoring:
- Define severity levels (e.g., green/yellow/red)
- Visual indicators change based on current value vs thresholds
- Useful for SLA monitoring, workload alerts

### Configuration Methods
1. **Admin UI**: Create dashboards, add widgets via form interfaces, edit via JSON editor
2. **Auto-deployment**: `.dashboard.json` files deployed at startup with changeset IDs to prevent duplicate execution

### Access Control
- `view` action controls access to specific dashboard data
- `view_list` action controls whether dashboard tabs appear
- Field-based conditions can restrict access to individual dashboards

## Comparison Notes -- Valtimo vs Procest

### Procest approach
- Uses **MyDash** Nextcloud app for dashboard functionality
- Widget-based dashboard with configurable data sources
- Integration with OpenRegister for case data
- Simpler widget model, fewer visualization types

### Valtimo advantages
- Rich visualization types (bar, meter, gauge, number)
- Threshold/severity-based KPI monitoring
- Auto-deployable dashboard configurations
- JSON editor for batch widget editing
- Integrated access control per dashboard

### Valtimo disadvantages
- Dashboards are Valtimo-specific -- not reusable outside the platform
- Limited to pre-built data sources (no arbitrary SQL or API queries from UI)
- No drag-and-drop dashboard layout editor
- No real-time updates (polling-based refresh)
