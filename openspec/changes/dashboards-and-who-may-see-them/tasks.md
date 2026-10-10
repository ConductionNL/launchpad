# Tasks: dashboards-and-who-may-see-them

## 1. The personal layer

- [x] 1.1 Add a personal layer record keyed by user and dashboard, holding order, size and the hidden set.
- [x] 1.2 Apply the layer over a shared dashboard at render time, and only for its owner.
- [x] 1.3 Refuse hiding a placement marked compulsory by `admin-templates`.
- [x] 1.4 Add the reset action, deleting the whole layer behind a confirmation.
- [x] 1.5 Drop orphaned layer entries when a placement disappears after an admin template re-sync. The caller is the Tier-A cleanup category `lib/Service/Cleanup/OrphanedPersonalLayerEntriesCategory.php`, run by the daily `OrphanedDataCleanupJob`; tests `tests/Unit/Service/Cleanup/OrphanedPersonalLayerEntriesCategoryTest.php`.
- [x] 1.6 PHPUnit on apply, on the compulsory refusal and on reset.
- [x] 1.7 PHPUnit from the READER: a saved layer changes what `DashboardService` hands back, on the landing read and on the by-id read, and reaches nobody else.

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

- [x] 4.1 Add the admin setting: off by default, with a purpose string that cannot be empty. `AdminSettingKey::ACTIVITY_REPORTING_*`, `ActivityReportService::setPolicy()`; admin tab `src/components/admin/tabs/ActivityReportingTab.vue`.
- [x] 4.2 Show the purpose on every screen reporting on a named person. `ActivityReportService::report()` returns `purpose` for a report on somebody else; `src/components/activity/ActivityReport.vue` shows it.
- [x] 4.3 Write an audit entry for every read of another person's activity. `CriticalActionPerformedEvent` (admin_audit), for reads and exports; retention is admin_audit's log configuration.
- [x] 4.4 Let a person read their own without the setting and without a log entry. `ActivityReportService::refusal()`; own view on the personal settings page (`src/views/settings/MyActivitySettings.vue`).
- [x] 4.5 Report counts per activity type and a per day calendar of intensity, from the existing activity events. `ActivityReportService::build()` over `lib/Db/ActivityEventReader.php`, per day in the reader's time zone, intensity 0 to 4.
- [x] 4.6 Export both as CSV, with totals equal to the screen. `ActivityReportService::export()`, `GET /api/activity-report/export`.
- [x] 4.7 PHPUnit on the gate, the self-read exception and the audit entry. `tests/Unit/Service/ActivityReportServiceTest.php`, `tests/Unit/Controller/ActivityReportControllerTest.php`.

## 5. Colleague activity

- [x] 5.1 Add the widget over the existing activity feed. widget type `colleague-activity` (`src/components/Widgets/Renderers/ColleagueActivityWidget.vue`, `lib/widget-types.json`), `GET /api/colleague-activity`.
- [x] 5.2 Filter to what the reader may see, and exclude filtered entries from every count. `lib/Service/ColleagueActivityService.php` rechecks LaunchPad dashboard events with `PermissionService::canViewDashboard()` and counts after the filter.
- [x] 5.3 PHPUnit on the permission filter, including its effect on the totals. `tests/Unit/Service/ColleagueActivityServiceTest.php`.

## 6. In-widget search

- [x] 6.1 Add a filter field to widgets that render rows, in view state only. `src/utils/rowFilter.js` wired into `src/components/WidgetWrapper.vue` for row widgets, outside edit mode.
- [x] 6.2 Assert it never writes to the placement or its saved search. the filter only marks DOM rows; `WidgetWrapper.rowFilter.spec.js` asserts the placement is unchanged and nothing is emitted.
- [x] 6.3 Vitest on the component: filter, reload, unchanged definition. `src/utils/__tests__/rowFilter.spec.js`, `src/components/__tests__/WidgetWrapper.rowFilter.spec.js`.

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

- [ ] 9.1 Give the dossiq lane the consumer half: case widgets contributed to a launchpad dashboard, each declaring the roles that may see it. (asked in for-ruben/launchpad-sibling-asks.md, 10 Oct)
- [ ] 9.2 Agree with the openregister lane which aggregations a shipped report may rely on. (asked in for-ruben/launchpad-sibling-asks.md, 10 Oct; waits on Q-launchpad-2)
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

`pruneOrphans()` has its caller now (task 1.5): the Tier-A cleanup category
`orphaned_personal_layer_entries` sweeps every layer against the placements its
dashboard still has, once a day in `OrphanedDataCleanupJob`. One sweep covers
every way a placement goes (a re-sync, a version restore, an owner removing a
widget), where wiring each `deleteByDashboardId()` caller would miss the next.
The user is not told: a stale entry was never rendered, so nothing they see
changes.

Three routes, all `#[NoAdminRequired]`, all reading the caller's own user id
from the session. No route takes a user id from the request, so one person's
layer is unreachable through another's.

**Sections 4, 5 and 6 are built** (10 Oct, build/openspecs-2). Activity
reporting reads the activity app's stream, counts events and never hours, is
off until an administrator turns it on with a purpose, shows the purpose on
every report about somebody else, and sends every such read or export to the
platform audit trail. A person reads their own on the personal settings page.
The colleague activity widget lists what reached the reader and rechecks
dashboard visibility at read time. The in-widget filter marks rendered rows
only. e2e specs are written and wait for the live pass (decision 139):
`tests/e2e/api-direct/activity-reporting.api.spec.ts`,
`tests/e2e/widget-row-filter.spec.ts`.

**Sections 2, 3, 7 and 8 wait on product questions** (QUESTIONS.md
Q-launchpad-2 to Q-launchpad-5): which reports ship first, what a "body of
work" is, what a "rate" per area means, and what a "workspace" and "past
retention" mean for storage.

**Section 9, the handover.** 9.1 and 9.2 are unchanged: the dossiq and
openregister halves have not been handed over, because sections 2 to 8 are what
they depend on.
