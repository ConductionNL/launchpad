# Twenty CRM - Views & Pipelines

**Analyzed:** 2026-03-14

## View Types

### Table View
- Spreadsheet-like list format
- Configurable columns (show/hide, reorder)
- Grouping: collapsible groups by field value
- Sorting and filtering support

### Kanban Board View
- Cards organized across columns representing stages
- Drag-and-drop between stages
- Column aggregations: Count, Sum, Average, Min, Max
- Compact view (titles only) for high-level overview
- Best practice: 5-7 stages maximum

**Stage management:** Configured via Settings > Data Model by editing the Select field powering the board.

### Calendar View
- Records with date fields displayed on calendar
- Useful for tasks, deadlines, events
- Date-based visualization

## View Configuration

- **Fields & Columns:** Show/hide specific fields, reorder with drag
- **Filters:** Record filtering with conditions
- **Sorting:** Multiple sort criteria
- **View Settings:** Naming, icons, visibility
- **Access Control:** Restrict who can see custom views

## Sales Pipeline

Twenty provides dedicated pipeline support via Kanban views on Opportunities:
- Stage-based progression tracking
- Expected amount display (weighted deal values based on stage probability)
- Stage duration tracking (deal velocity monitoring)
- Visual deal flow management

## Dashboard & Reporting

**Status:** Beta (requires Early Access activation)

### Widget Types
| Type | Description |
|------|-------------|
| Bar Chart | Comparative data visualization |
| Pie Chart | Proportional data display |
| Line Chart | Trend visualization |
| Aggregate Chart | Summary metrics |
| iFrame | Embedded external content |
| Rich Text | Formatted text blocks |

**Not yet available:** Gauge charts, tables, dashboard export, external sharing.

### Dashboard Structure
- Dashboards contain tabs
- Tabs contain widgets
- Widgets configured with data source (object), chart type, and settings
- Visible to relevant team members
