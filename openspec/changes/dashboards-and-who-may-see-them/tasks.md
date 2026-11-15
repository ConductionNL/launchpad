# Tasks: dashboards-and-who-may-see-them

## 1. The personal layer

- [x] 1.1 Add a personal layer record keyed by user and dashboard, holding order, size and the hidden set.
- [x] 1.2 Apply the layer over a shared dashboard at render time, and only for its owner.
- [x] 1.3 Refuse hiding a placement marked compulsory by `admin-templates`.
- [x] 1.4 Add the reset action, deleting the whole layer behind a confirmation.
- [x] 1.5 Drop orphaned layer entries when a placement disappears after an admin template re-sync. `TemplateResyncService` calls `PersonalLayerService::pruneDashboard()` after a re-sync that removed placements; it sweeps every layer on that dashboard through `PersonalLayerMapper::findByDashboard()`.
- [x] 1.6 PHPUnit on apply, on the compulsory refusal and on reset.
- [x] 1.7 PHPUnit from the READER: a saved layer changes what `DashboardService` hands back, on the landing read and on the by-id read, and reaches nobody else.

## 2. The report library

- [x] 2.1 Define the report definition shape: id, title, description, aggregation, parameters, rendering, required registers and schemas. `Reporting\ReportDefinitions::check()`.
- [ ] 2.2 Ship a first set of definitions with the app and list them in a gallery. Shipped: `lib/Settings/reports.json` (three reports) and the gallery data at `GET /api/reports`. Left: the gallery screen over that endpoint.
- [x] 2.3 List a report whose registers or schemas are missing as unavailable, naming them.
- [x] 2.4 Save a result as an ordinary dashboard owned by the saver. `POST /api/reports/{reportId}/dashboard`: a dashboard of the caller holding one chart of the result.
- [x] 2.5 PHPUnit on the availability check and on save-as-dashboard.

## 3. The status report

- [x] 3.1 Add the status report object: date, health rating, summary, figures, and per value whether it was computed or written. — built: schema `status_report` in the launchpad register (`lib/Settings/register.d/dashboards-and-who-may-see-them.json`, register version 1.0.3) with `provenance` per value; kept through OpenRegister's published object service (`StatusReport\StatusReportStore`), 503 without OpenRegister.
- [x] 3.2 Prefill the rating and the figures from the body of work. — built: `POST /api/status-reports/draft` reads the objects of one schema in one register as the caller (optional filters, status and due-date properties, closed values) and computes total, open, closed, overdue, share closed and days to the deadline, the rating (off track: deadline passed with items open or more than a quarter of the open items overdue; at risk: anything overdue, or within 14 days of the deadline with less than 75% closed) and a summary.
- [ ] 3.3 Let a person edit the summary before keeping the report. — built: `POST /api/status-reports` keeps the report with the person's summary and rating, computing the figures again (a person cannot write them). Left: the screen over these two endpoints.
- [x] 3.4 PHPUnit on prefill and on the computed-versus-written record. — built: `StatusReportServiceTest` (11), `StatusReportStoreTest` (4), `StatusReportControllerTest` (4), and the shipped register carrying the schema (`ImportLaunchpadRegisterTest`).

## 4. Activity reporting

- [x] 4.1 Add the admin setting: off by default, with a purpose string that cannot be empty. — built: `ActivityReport\ActivityReportingPolicy` (app config, off by default; turning it on without a purpose, or with one over 500 characters, is refused; a stored switch without a purpose reads as off), read and set by administrators at `GET`/`PUT /api/admin/activity-reporting`.
- [ ] 4.2 Show the purpose on every screen reporting on a named person. — left: every response and the CSV carry the purpose, but no screen reports on a named person yet; the screen over `/api/activity-report` is still to be made.
- [x] 4.3 Write an audit entry for every read of another person's activity. — built: every read of another person's activity dispatches Nextcloud's `CriticalActionPerformedEvent` (the admin audit log) with the subject, the period, the reader and the purpose.
- [x] 4.4 Let a person read their own without the setting and without a log entry. — built: `ActivityReportService::report()` gives anybody their own activity whatever the switch, with no audit entry; another person's is refused with 403 while the switch is off (the answer says how to turn it on) and for a non-administrator, before anything is read.
- [x] 4.5 Report counts per activity type and a per day calendar of intensity, from the existing activity events. — built: `GET /api/activity-report?user=&from=&to=` counts per activity type and per day (UTC, a day without activity absent, at most 366 days), from the activity app's events (`ActivityEventReader`, one event per action however many people it was shown to); nothing measures hours. Without the activity app the report says `available: false`.
- [x] 4.6 Export both as CSV, with totals equal to the screen. — built: `GET /api/activity-report/export` returns the same report as CSV (purpose, subject, period, a row per type and per day, the total), under the same rules.
- [x] 4.7 PHPUnit on the gate, the self-read exception and the audit entry. — built: `ActivityReportServiceTest` (9: the gate, the self-read exception, the audit entry, the figures, the CSV totals), `ActivityReportingPolicyTest` (5), `ActivityEventReaderTest` (3), `ActivityReportControllerTest` (6).

## 5. Colleague activity

- [x] 5.1 Add the widget over the existing activity feed. — built: widget type `colleague-activity` (`ColleagueActivityWidget` with its form: lines and days back) over `GET /api/colleague-activity`, which reads the activity app's rows addressed to the reader (`ColleagueActivityReader`), without the reader's own actions; no second feed.
- [x] 5.2 Filter to what the reader may see, and exclude filtered entries from every count. — built: `ColleagueActivityService` checks every event on a launchpad dashboard against the dashboard as it is now (`PermissionService::canViewDashboard()`); an event on a dashboard the reader cannot see, or that is gone, is absent, and the total, the per-type counts and the number of colleagues are taken from what remains.
- [x] 5.3 PHPUnit on the permission filter, including its effect on the totals. — built: `ColleagueActivityServiceTest` (7), `ColleagueActivityReaderTest` (2), `ColleagueActivityControllerTest` (2); Vitest on the widget (7) and its form (3).

## 6. In-widget search

- [x] 6.1 Add a filter field to widgets that render rows, in view state only.
- [x] 6.2 Assert it never writes to the placement or its saved search.
- [x] 6.3 Vitest on the component: filter, reload, unchanged definition.

## 7. Geographic reporting

- [x] 7.1 Add the aggregation: counts and rates per declared area property over a period. `Reporting\AreaAggregation`, run by the shipped report kind `area` (section 2).
- [x] 7.2 Count objects with no location in an explicit bucket.
- [x] 7.3 PHPUnit on the bucket, including the empty case.

## 8. Storage reporting

- [x] 8.1 Read use per workspace and per service from the platform figures and register counts. — built: `StorageReport\StorageFigures` reads the folder sizes the file cache keeps (the figure Nextcloud's quota uses) and the object count per register, never walking files or objects. A workspace is a Nextcloud group (its members' files; a person in two groups counts in both, so the total in use comes from the services); a service is files, trash or versions over every home, or one app's app data. `GET /api/admin/storage-report`.
- [x] 8.2 Keep a daily series and report twelve months. — built: `StorageSnapshotJob` keeps one snapshot a day (totals only) in launchpad's app data (`StorageSeries`, at most 400 days, no table); the report gives the total, every workspace and every service per month for the last twelve months, the month's last snapshot, empty for a month without one.
- [x] 8.3 Name the removable total: past retention, orphaned uploads. — built: apart from the total in use, trash entries older than Nextcloud's `trashbin_retention_obligation` (`auto` is 30 days, `disabled` expires nothing) and launchpad uploads older than a day that no launchpad row names; when the rows cannot be read, no upload counts as orphaned.
- [x] 8.4 Export as CSV, administrator-only, and refuse others with 403. — built: `GET /api/admin/storage-report/export`, the same numbers as CSV; both routes carry the admin-setting attribute and the service refuses anybody who is not an administrator with 403 again.
- [x] 8.5 PHPUnit on the admin guard and on the removable calculation. — built: `StorageReportServiceTest` (10), `StorageFiguresTest` (7), `StorageSeriesTest` (4), `StorageReportControllerTest` (4), `StorageSnapshotJobTest` (2).

## 9. Handover

- [ ] 9.1 Give the dossiq lane the consumer half: case widgets contributed to a launchpad dashboard, each declaring the roles that may see it.
- [ ] 9.2 Agree with the openregister lane which aggregations a shipped report may rely on.
- [ ] 9.3 Tick this change in `competitor-parity-2026-09/tasks.md` when it archives.

## What shipped, and what has not

**Section 1 is built and covered.** `launchpad_personal_layers` holds one row
per person per dashboard: the geometry and order they changed, and the
placements they hid. `PersonalLayerService` lays it over the shared placements
inside `DashboardService::getDashboardForUser()`, on both the owned-read path
and the share path, and inside `getEffectiveDashboard()`, which is the
`GET /api/dashboard` the grid loads from. Only for a dashboard the caller does
not own: their own dashboard is left alone, because there the arrangement IS
the dashboard.

Two things were fixed once the reader got its own tests. `getEffectiveDashboard()`
did not apply the layer at all, so a person who arranged the dashboard they land
on saw their arrangement through the layer endpoint and nowhere else. And the
layer service was a nullable constructor argument on `DashboardService`, purely
so that unit tests could leave it out. A null read exactly like a person with no
layer, so a wiring failure would have stored every layer and shown none of them
with the whole suite green. It is required now; nothing in the app ever built
that service without it.

A compulsory placement cannot be hidden, and a save that asks to hide one
writes nothing at all rather than landing the allowed half. It can still be
moved. A placement made compulsory after somebody hid it renders again, which
is checked. The reset is `DELETE`, removes the whole row, and resetting twice
is not an error.

`pruneOrphans()` is the exception, and task 1.5 is open again because of it.
The method drops entries for placements that no longer exist, and deletes the
row when the sweep empties it. Nothing calls it. Two tests cover it and there
is no call site in `lib/`, which is the same shape this branch just fixed for
`withPersonalLayer()`: a method with a green suite and no caller reports the
same green as one that runs.

Wiring it is not a one-line call, which is why it is a task and not a fix
here. A re-sync replaces the placements of one dashboard for everybody, and
`pruneOrphans()` takes one user id. `PersonalLayerMapper` can find a layer by
user and dashboard and delete it, and it cannot list the layers on a
dashboard, so there is no query to sweep with. Eight places call
`deleteByDashboardId()`, and each has to decide whether it is a re-sync that
should prune or a deletion that should drop the layer outright.

What it costs while it waits is bounded, and it is not a wrong number on
screen. `applyTo()` walks the live placements and looks up overrides by id,
so an entry for a placement that is gone is ignored at render time. Placement
ids come from an autoincrement column and are never reused, so a stale entry
cannot attach itself to a different widget later. The cost is stale keys in
the layer row, one per placement ever removed from that dashboard.

Three routes, all `#[NoAdminRequired]`, all reading the caller's own user id
from the session. No route takes a user id from the request, so one person's
layer is unreachable through another's.

**Sections 2 to 8 are built on the server.** The report library (section 2),
the status report (3), activity reporting with its purpose gate (4), colleague
activity (5), in-widget search (6), geographic reporting (7) and storage
reporting (8) each have their endpoint, their rules and their PHPUnit; section 5
also has its widget and section 6 its Vitest. What is left is screens: the
report gallery (2.2), the status report form (3.3) and the activity report
screen that shows the purpose (4.2). Each is a page over an endpoint that
exists, and none was made by hand outside the manifest.

**Section 9, the handover.** 9.1 and 9.2 are unchanged: the dossiq and
openregister halves have not been handed over, because sections 2 to 8 are what
they depend on.
