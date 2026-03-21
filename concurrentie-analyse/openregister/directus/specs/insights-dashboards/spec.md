---
status: draft
source: competitive-analysis
competitor: directus
analyzed_date: 2026-03-14
---

# Insights & Dashboards

## Overview

Directus Insights is a built-in analytics dashboard system that enables users to create custom dashboards with multiple panel types. Dashboards are stored in `directus_dashboards` and panels in `directus_panels`.

## Dashboard Structure

A dashboard consists of:
- **Name**: Dashboard title
- **Icon/Color**: Visual identification
- **Note**: Description
- **Panels**: Collection of data visualization panels arranged in a grid
- **Auto-refresh**: Configurable refresh interval

## Panel Types (12 Built-in)

### Data Visualization
| Panel | Description |
|-------|------------|
| **Bar Chart** | Vertical/horizontal bar chart with grouping |
| **Line Chart** | Time series or categorical line chart |
| **Pie Chart** | Pie/donut chart for proportional data |
| **Time Series** | Specialized time-based chart with date axis |

### Metrics
| Panel | Description |
|-------|------------|
| **Metric** | Single large number with optional prefix/suffix |
| **Meter** | Gauge/progress indicator with min/max range |
| **Metric List** | Multiple metrics in a compact list |

### Content
| Panel | Description |
|-------|------------|
| **Label** | Static text/markdown content |
| **List** | Dynamic list of items from a collection |
| **Relational Variable** | Display value from a related collection |
| **Variable** | Reusable dynamic value / global filter control |

## Panel Configuration

Each panel has:
- **Type**: Which panel component to render
- **Position**: Grid x/y coordinates
- **Size**: Width/height in grid units
- **Collection**: Data source collection
- **Options**: Panel-type-specific configuration (fields, filters, aggregations, colors)

## Data Queries

Dashboard panels use the same query system as the REST API:
- **Filter**: Restrict which items are included
- **Aggregation**: `count`, `sum`, `avg`, `min`, `max`
- **Group By**: Category fields for grouping data
- **Sort/Limit**: Control result ordering and size

## Access Control

- Dashboards respect the user's permissions
- Panels only show data the authenticated user has access to
- Dashboard CRUD operations require appropriate permissions on `directus_dashboards` and `directus_panels`

## Extensibility

Panels are a dedicated extension type. Custom panels can:
- Include any Vue.js component
- Access Directus services and data via composables
- Define custom configuration options
- Use chart libraries (Chart.js, D3, etc.)

## Implementation

The dashboard services (`DashboardsService`, `PanelsService`) are thin wrappers around `ItemsService` - they store dashboard/panel configurations as system collection records. The actual data visualization happens entirely in the frontend Vue app.

## Relevance to OpenRegister

OpenRegister does not currently have a built-in dashboard/analytics feature. Options:
- **MyDash app**: A separate Nextcloud app could provide dashboard functionality
- **Nextcloud Dashboard**: Integrate with Nextcloud's existing dashboard widget system
- **n8n integration**: Use n8n to aggregate data and push to dashboard tools
- **External tools**: Integrate with Grafana, Metabase, or similar

Directus's approach of storing dashboard configs as data and rendering in the frontend is lightweight and could be adopted. The key advantage is that it uses the same permission system and query engine as the rest of the platform.
