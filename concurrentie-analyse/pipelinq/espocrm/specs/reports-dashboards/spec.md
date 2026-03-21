---
competitor: espocrm
analyzed_date: 2026-03-14
feature: reports-dashboards
---

# Reports & Dashboards

## Overview

EspoCRM provides a dashboard system with configurable dashlets and built-in CRM reports. The open-source edition includes basic reports for opportunities and a general-purpose dashlet framework. Advanced reporting with custom report builders is available only in the paid Advanced Pack.

## Dashboard System

### Architecture
- Users have personal dashboards with draggable dashlet grids
- Dashboard Templates (`DashboardTemplate` entity) can be pre-configured and assigned
- Multiple dashboard tabs supported

### Default Dashboard Layout
The default dashboard includes:
- **Activities** dashlet (upcoming meetings, calls, tasks)
- **Stream** dashlet (activity feed)

### Core Dashlets (`metadata/dashlets/`)

| Dashlet | Purpose |
|---------|---------|
| Stream | Activity stream feed |
| Emails | Email inbox widget |
| Records | Generic record list (configurable entity type) |
| Iframe | Embedded external content |
| Memo | Free-text notes |

### CRM Dashlets (`Modules/Crm/Resources/metadata/dashlets/`)

| Dashlet | Purpose |
|---------|---------|
| Activities | Upcoming activities timeline |
| Calendar | Calendar view widget |
| Meetings | Meeting list |
| Calls | Call list |
| Tasks | Task list |
| Cases | Support case list |
| Leads | Lead list |
| Opportunities | Opportunity list |
| OpportunitiesByStage | Chart: deals grouped by stage |
| OpportunitiesByLeadSource | Chart: deals grouped by lead source |
| SalesByMonth | Chart: monthly revenue trend |
| SalesPipeline | Chart: pipeline funnel |

## Built-in Opportunity Reports

### SalesPipeline (`Tools/Opportunity/Report/SalesPipeline.php`)
- Bar chart showing total deal value per pipeline stage
- Excludes lost stages
- Filterable by date range (close date) and team
- Supports fiscal year offset
- Uses `amountConverted` for multi-currency normalization
- Respects ACL (users only see permitted data)

### SalesByMonth (`Tools/Opportunity/Report/SalesByMonth.php`)
- Line/bar chart showing won revenue per month
- Historical trend analysis
- Date range and team filtering

### ByStage (`Tools/Opportunity/Report/ByStage.php`)
- Distribution chart showing deal counts or values per stage
- Date range filtering

### ByLeadSource (`Tools/Opportunity/Report/ByLeadSource.php`)
- Attribution chart showing revenue per lead source
- Helps identify most valuable acquisition channels

### Report Utility (`Tools/Opportunity/Report/Util.php`)
Shared utilities:
- Lost stage list detection
- Won stage list detection
- Date range parsing with fiscal year support
- Distinct query handling

## Dashboard API

```
GET  /DashboardTemplate          - List templates
POST /DashboardTemplate          - Create template
GET  /Preferences                - Get user preferences (includes dashboard layout)
PUT  /Preferences                - Update user preferences (dashboard layout)
```

Dashboard configuration is stored in user Preferences as JSON, including:
- Tab definitions
- Per-tab dashlet grid positions (x, y, width, height)
- Per-dashlet configuration options

## Stars System

Users can "star" (favorite) records of any entity type (`Tools/Stars/`), enabling quick access dashlets for starred items.

## Activity Stream

Every entity can have a stream showing:
- Field change history (for audited fields)
- Notes/comments
- Relationship changes
- Email activity
- Assignment changes

## Relevance to Pipelinq

### Strengths
- Solid dashlet framework with drag-and-drop grid
- Four built-in pipeline reports cover common CRM needs
- Dashboard templates for onboarding
- Activity stream provides full audit trail
- Stars/favorites for quick access

### Weaknesses
- **No custom report builder** in open-source (paid Advanced Pack feature)
- **No real-time updates** - dashlets require manual refresh
- **Limited chart types** - basic bar/line charts only
- **No data export** from reports (only from list views)

### Opportunities for Pipelinq
- **OpenRegister faceting**: Use faceted search for dynamic report generation
- **n8n analytics workflows**: Build custom reports as n8n workflows that aggregate data
- **Nextcloud Dashboard integration**: Embed Pipelinq widgets in the Nextcloud dashboard
- **Real-time KPIs**: Use WebSocket or polling for live pipeline metrics
- **Custom visualizations**: Vue-based charts with more flexibility than EspoCRM's fixed report types
