---
status: draft
source: competitive-analysis
competitor: nocobase
analyzed_date: 2026-03-14
---

# Data Visualization

## Purpose

NocoBase provides built-in data visualization through the `plugin-data-visualization` and `plugin-data-visualization-echarts` plugins. Users can create chart blocks on pages that query collection data and render various chart types.

## Architecture Overview

```
Chart Block (UI)
    |
    v
Query Action (/api/charts:query)
    |
    v
Query Parser (transforms config to SQL)
    |
    v
Database (aggregate queries)
    |
    v
Chart Renderer (ECharts / Ant Design Charts)
```

## Data Model

Chart configurations are stored as part of UI schemas (JSON). Each chart block contains:
- Collection and data source reference
- Measures (aggregation fields + functions)
- Dimensions (grouping fields)
- Filters (data subset)
- Chart type and display options

## Business Logic

### Query System
The visualization plugin defines a `charts:query` action that:
1. Accepts chart configuration (measures, dimensions, filters)
2. Parses configuration into SQL aggregate queries
3. Executes against the database
4. Returns formatted data for chart rendering
5. Results cached in memory (30 second TTL, max 1000 entries)

### Chart Types
Via ECharts integration:
- Bar charts (horizontal/vertical)
- Line charts
- Pie/Donut charts
- Area charts
- Scatter plots
- Custom ECharts configurations

Via Ant Design Charts (built-in):
- Basic statistical charts
- Table-based visualizations

### Formatter System
Data formatters transform raw query results for display:
- Date formatting (by day, month, year)
- Number formatting (decimal, percentage)
- Custom value mappings

### AI Integration (experimental)
The plugin has commented-out code for AI-powered chart building:
```typescript
// ai.aiManager.toolManager.registerTools([{
//   groupName: 'frontend',
//   tool: buildChartBlock,
// }]);
```

## Requirements

### Functional
- Create chart blocks on any page
- Query any collection with aggregations
- Multiple chart types (bar, line, pie, etc.)
- Dimension and measure configuration
- Filter data subsets
- Real-time data refresh

### Non-functional
- Query result caching (30s TTL)
- Efficient aggregate queries
- Responsive chart rendering

## Comparison Notes

### vs OpenRegister
- NocoBase has built-in chart blocks; OpenRegister has no native visualization
- NocoBase uses ECharts; OpenRegister would need external charting libraries
- NocoBase charts query collections directly; OpenRegister would need API-based queries
- Both lack advanced BI features (calculated fields, drill-down, dashboards)
