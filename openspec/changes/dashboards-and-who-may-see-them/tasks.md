# Tasks: dashboards-and-who-may-see-them

## 1. The personal layer

- [ ] 1.1 Add a personal layer record keyed by user and dashboard, holding order, size and the hidden set.
- [ ] 1.2 Apply the layer over a shared dashboard at render time, and only for its owner.
- [ ] 1.3 Refuse hiding a placement marked compulsory by `admin-templates`.
- [ ] 1.4 Add the reset action, deleting the whole layer behind a confirmation.
- [ ] 1.5 Drop orphaned layer entries when a placement disappears after an admin template re-sync.
- [ ] 1.6 PHPUnit on apply, on the compulsory refusal and on reset.

## 2. The report library

- [ ] 2.1 Define the report definition shape: id, title, description, aggregation, parameters, rendering, required registers and schemas.
- [ ] 2.2 Ship a first set of definitions with the app and list them in a gallery.
- [ ] 2.3 List a report whose registers or schemas are missing as unavailable, naming them.
- [ ] 2.4 Save a result as an ordinary dashboard owned by the saver.
- [ ] 2.5 PHPUnit on the availability check and on save-as-dashboard.

## 3. The status report

- [ ] 3.1 Add the status report object: date, health rating, summary, figures, and per value whether it was computed or written.
- [ ] 3.2 Prefill the rating and the figures from the body of work.
- [ ] 3.3 Let a person edit the summary before keeping the report.
- [ ] 3.4 PHPUnit on prefill and on the computed-versus-written record.

## 4. Activity reporting

- [ ] 4.1 Add the admin setting: off by default, with a purpose string that cannot be empty.
- [ ] 4.2 Show the purpose on every screen reporting on a named person.
- [ ] 4.3 Write an audit entry for every read of another person's activity.
- [ ] 4.4 Let a person read their own without the setting and without a log entry.
- [ ] 4.5 Report counts per activity type and a per day calendar of intensity, from the existing activity events.
- [ ] 4.6 Export both as CSV, with totals equal to the screen.
- [ ] 4.7 PHPUnit on the gate, the self-read exception and the audit entry.

## 5. Colleague activity

- [ ] 5.1 Add the widget over the existing activity feed.
- [ ] 5.2 Filter to what the reader may see, and exclude filtered entries from every count.
- [ ] 5.3 PHPUnit on the permission filter, including its effect on the totals.

## 6. In-widget search

- [ ] 6.1 Add a filter field to widgets that render rows, in view state only.
- [ ] 6.2 Assert it never writes to the placement or its saved search.
- [ ] 6.3 Vitest on the component: filter, reload, unchanged definition.

## 7. Geographic reporting

- [ ] 7.1 Add the aggregation: counts and rates per declared area property over a period.
- [ ] 7.2 Count objects with no location in an explicit bucket.
- [ ] 7.3 PHPUnit on the bucket, including the empty case.

## 8. Storage reporting

- [ ] 8.1 Read use per workspace and per service from the platform figures and register counts.
- [ ] 8.2 Keep a daily series and report twelve months.
- [ ] 8.3 Name the removable total: past retention, orphaned uploads.
- [ ] 8.4 Export as CSV, administrator-only, and refuse others with 403.
- [ ] 8.5 PHPUnit on the admin guard and on the removable calculation.

## 9. Handover

- [ ] 9.1 Give the dossiq lane the consumer half: case widgets contributed to a launchpad dashboard, each declaring the roles that may see it.
- [ ] 9.2 Agree with the openregister lane which aggregations a shipped report may rely on.
- [ ] 9.3 Tick this change in `competitor-parity-2026-09/tasks.md` when it archives.
