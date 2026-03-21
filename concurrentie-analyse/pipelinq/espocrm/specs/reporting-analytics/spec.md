---
competitor: espocrm
analyzed_date: 2026-03-14
feature: Reporting & Analytics
relevance: medium
pipelinq_equivalent: Pipeline dashboards, OpenRegister analytics
---

# Reporting & Analytics

## Overview

EspoCRM provides two levels of reporting:

1. **Built-in pipeline reports** (free, open-source) - Pre-built sales pipeline visualizations
2. **Advanced Pack Reports** ($395/year) - Flexible report builder for any entity type

## Built-in Pipeline Reports (Free)

### Sales Pipeline Chart
- Bar chart of deal values grouped by stage
- Excludes lost stages
- Filterable by date range and team
- Supports fiscal year shifts
- Access-control enforced (users see only permitted data)

### Sales by Month
- Monthly revenue trend line
- Shows won deals over time

### By Stage
- Distribution of opportunities across pipeline stages

### By Lead Source
- Attribution analysis: which lead sources generate the most revenue

### Weighted Pipeline
- Uses computed field: `amount * probability * exchange_rate / 100`
- Enables weighted pipeline totals without stored data

## Advanced Pack Reports (Paid)

### Report Types
- **List Reports** - Tabular data with filtering and grouping
- **Grid Reports** - Pivot-table style aggregations
- **Joint Reports** - Combine multiple reports

### Capabilities
- Any entity type (not just Opportunities)
- Complex filters with AND/OR groups
- Grouping by fields, date periods
- Aggregate functions (SUM, AVG, COUNT, MIN, MAX)
- Custom columns via formula
- Chart visualizations (bar, line, pie)
- Export to CSV/Excel
- Email scheduling (automatic report delivery)
- Dashboard widgets from reports
- Report panels on record detail views

### Report-Driven Automation
- Scheduled workflows can use reports as their record set
- Example: "Send notification to customers whose license expires in 7 days"

## Dashboards

### Built-in Dashlets
- Activities (upcoming meetings, calls, tasks)
- Stream (activity feed)
- Pipeline chart
- Sales by month
- Opportunities by lead source
- Opportunities by stage

### Custom Dashlets
- Reports (Advanced Pack) can be added as dashboard widgets
- Multiple dashboard tabs supported
- Per-user dashboard customization

## Strengths

- Built-in pipeline reports are free
- Advanced Pack offers flexible report builder
- Report-driven workflow automation
- Dashboard customization
- Multi-tab dashboards
- CSV/Excel export
- Email-scheduled reports

## Weaknesses

- Full reporting requires paid extension ($395/year)
- No real-time/streaming analytics
- No cohort analysis
- No funnel visualization (only bar/line/pie charts)
- No pipeline velocity or conversion rate tracking built-in
- No AI-powered insights
- Limited chart types

## Comparison with Pipelinq

| Aspect | EspoCRM | Pipelinq |
|--------|---------|----------|
| Built-in reports | Basic pipeline charts (free) | Pipeline dashboards |
| Report builder | Advanced Pack ($395/year) | OpenRegister queries |
| Chart types | Bar, line, pie | TBD |
| Automation from reports | Scheduled workflows | n8n analytics workflows |
| Real-time | No | Potential via Nextcloud |
| Cost | Free basic / $395 full | Included |
| Funnel analysis | No | Potential via pipeline views |
