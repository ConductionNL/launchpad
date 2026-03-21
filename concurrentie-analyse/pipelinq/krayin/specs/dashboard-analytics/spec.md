---
competitor: krayin
analyzed_date: 2026-03-14
feature: dashboard-analytics
priority: medium
---

# Dashboard & Analytics

## Overview

Krayin provides a dashboard with 8 fixed statistical views covering lead performance, revenue, and contact metrics. Stats are date-range filtered and loaded asynchronously.

## Dashboard Stats

| Stat Type | Method | Description |
|-----------|--------|-------------|
| over-all | `getOverAllStats` | Summary metrics (total leads, revenue, etc.) |
| revenue-stats | `getRevenueStats` | Revenue over time |
| total-leads | `getTotalLeadsStats` | Lead count over time |
| revenue-by-sources | `getLeadsStatsBySources` | Revenue grouped by lead source |
| revenue-by-types | `getLeadsStatsByTypes` | Revenue grouped by lead type |
| top-selling-products | `getTopSellingProducts` | Products by revenue |
| top-persons | `getTopPersons` | Top contacts by deal value |
| open-leads-by-states | `getOpenLeadsByStates` | Open leads per pipeline stage |

## Routes

```
GET /dashboard        -- Dashboard page
GET /dashboard/stats  -- Stats API (?type=over-all&start_date=...&end_date=...)
```

## Pipelinq Comparison Notes

- 8 fixed stats is a minimal analytics offering
- No custom report builder or saved reports
- No export capability (PDF/Excel)
- Date range filtering is the only dimension
- No forecasting or trend analysis
- Good baseline to understand what metrics CRM users expect
- "Open leads by states" (pipeline stage distribution) is the most relevant for Pipelinq
