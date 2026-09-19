# Tasks: dashboards-and-who-may-see-them

## 1. The personal layer

- [x] 1.1 Add a personal layer record keyed by user and dashboard, holding order, size and the hidden set.
- [x] 1.2 Apply the layer over a shared dashboard at render time, and only for its owner.
- [x] 1.3 Refuse hiding a placement marked compulsory by `admin-templates`.
- [x] 1.4 Add the reset action, deleting the whole layer behind a confirmation.
- [ ] 1.5 Drop orphaned layer entries when a placement disappears after an admin template re-sync. `pruneOrphans()` is written and tested, and nothing calls it. The reason is below.
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

**Sections 2 to 8 are not built.** The report library, the status report,
activity reporting and its purpose gate, colleague activity, in-widget search,
geographic reporting and storage reporting are untouched, and their boxes are
still open above. Section 6 needs Vitest, and an earlier draft of this file
said Vitest could not run here because `npm ci` refused over a
`package.json` and `package-lock.json` that were out of sync. That is not
true on this tip. The two files agree on every dependency, and
`npm run test` runs the suite: 748 tests across 69 files. So the reason
section 6 is open is the plain one. Nobody built it.

**Section 9, the handover.** 9.1 and 9.2 are unchanged: the dossiq and
openregister halves have not been handed over, because sections 2 to 8 are what
they depend on.
