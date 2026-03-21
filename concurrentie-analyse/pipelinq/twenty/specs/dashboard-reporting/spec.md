---
competitor: twenty
analyzed_date: 2026-03-14
feature: Dashboard & Reporting
category: analytics
maturity: beta
---

# Dashboard & Reporting

## Summary

Twenty offers customizable dashboards with multiple widget types for CRM data visualization. Currently in beta, requiring Early Access activation.

## Architecture

- **Dashboards** contain **Tabs**
- **Tabs** contain **Widgets**
- Each widget has a data source (object), chart type, and configuration

## Widget Types (6)

| Type | Description |
|------|-------------|
| Bar Chart | Comparative data visualization |
| Pie Chart | Proportional data display |
| Line Chart | Trend visualization |
| Aggregate Chart | Summary metrics |
| iFrame | Embedded external content |
| Rich Text | Formatted text blocks |

## Not Yet Available
- Gauge charts
- Table widgets
- Dashboard export
- External sharing (non-Twenty users)

## Configuration
- Select data source object
- Choose widget type
- Configure chart settings
- Arrange on dashboard
- Set visibility per team member

## Relevance to Pipelinq

**Twenty's reporting strengths:**
- Built-in dashboard builder
- Multiple chart types
- Direct CRM data access
- iFrame embedding for external tools

**Pipelinq differentiators:**
- Pipeline-specific views (stage distribution, velocity, bottleneck analysis)
- OpenRegister faceting provides real-time data aggregation
- Nextcloud ecosystem has Collectives for knowledge management
- Integration with external BI tools via API/OAS export
- Twenty's dashboards are still beta with significant gaps (no tables, no export)
