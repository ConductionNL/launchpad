# Tasks: dashboard-period-and-download

- [ ] `DashboardPageHeader.vue`: the reader's rights ("alleen bekijken", "u mag widgets toevoegen") in the meta line; `@spec` tags to REQ-DPD-001
- [ ] Period control in the header, shown when a placement's source names `periodField`; This month, Quarter, Year; kept in `?period=`
- [ ] Register-query sources (`ChartHost` and the `stat`, `pie-chart`, `graph`, `table` sources): add the period range on `periodField` to the query; ignore it without a period field
- [ ] Widget forms with a register source: an optional "Date field for the period" select over the schema's date properties
- [ ] `GET /api/dashboards/{id}/download?period=&filter=` for anyone who may view the dashboard: renders a PDF through the scheduled-exports renderer for the caller, with their visibility rules; no run-row, no delivery; names widgets it cannot draw
- [ ] "Downloaden" in the header, absent in kiosk and public views
- [ ] Translations in every shipped locale
- [ ] Unit tests: meta line per kind and right; period range per choice and on the query; no period choice without a period field; the download endpoint's permission check (view-only allowed, stranger 404), no run-row, filters applied
- [ ] Playwright: choose Kwartaal and see the address and a figure change; download the dashboard as a view-only reader
