# Proposal: dashboard-period-and-download

## Why

Board LpManagement draws the header of a management dashboard: "Managementinformatie", "Downloaden" next to Meer, the
line "Groepsdashboard van Bedrijfsvoering · alleen bekijken · bijgewerkt 10:45", and a period choice "Deze maand,
Kwartaal, Jaar" that the figures below follow. DEMO-SEED names it the period selector.

- The line under the title is built (`DashboardPageHeader.vue`: kind and last change), but has no requirement of its
  own and does not say what the reader may do.
- No spec or code has a period choice for a whole dashboard. Widgets that read a register query each carry their own
  fixed date filter.
- No spec or code has a one-off download of a dashboard. Scheduled exports (REQ-SCH-009 "Run now") render a dashboard
  on a schedule or on demand for its owner, not for a reader.

## What changes

- `dashboards`: the header line names the kind of dashboard, what the reader may do and when it last changed
  (REQ-DPD-001); a period choice that widgets with a date field follow, kept in the address (REQ-DPD-002); a Download
  action that gives the reader the dashboard as a PDF now (REQ-DPD-003).
- The period is configuration: a widget's data source names its date field. No widget type is special-cased.

## Impact

- `DashboardPageHeader.vue`, a period control in the header, the register-query data sources (`ChartHost`, `stat`,
  `pie-chart`, `graph`, `table`), the export renderer of scheduled-exports reused for the download, translations.
