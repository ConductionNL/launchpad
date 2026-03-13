# Valtimo Dashboard

Source: https://docs.valtimo.nl/features/dashboard

## Overview

Dashboards provide statistical insights into cases and processes, enabling users to prioritize and focus on specific tasks.

## Structure

- A dashboard is a container for widgets
- Multiple dashboards shown as tabs
- Configured by administrators

## Widgets

Each widget combines:
- **Data-source**: Supplies underlying data
- **Display-type**: Visualization format
- **Widget**: Combines both for presentation

### Display Types
- Bar charts
- Numbers
- Meters
- Gauges
- Custom formats

### Properties
- Threshold support for KPI indicators
- Severity-based visualization adjustments

## Configuration

### UI-Based
1. Create dashboards with name and description
2. Add widgets through form interfaces
3. Edit via JSON editor for batch modifications

### Auto-Deployment (IDE)
- `.dashboard.json` files deployed at startup
- Changesets with unique IDs prevent duplicate execution
- Validates changeset integrity

## Access Control

- `view` — Access specific dashboard data
- `view_list` — Display dashboard tabs
- Can restrict access to individual dashboards using field-based conditions
