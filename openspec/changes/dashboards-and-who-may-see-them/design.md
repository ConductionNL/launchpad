# Design: dashboards-and-who-may-see-them

## Context

Launchpad already has dashboards with scopes (`user`, `admin_template`,
`group_shared`), widget placements, conditional visibility, an activity
extension and view analytics. Nothing below adds a second dashboard model.
Each decision names the existing capability it sits on.

## Decisions

### A personal layer, not a personal copy

`dashboards` REQ-DASH-020 already forks a visible dashboard into a personal
copy. A copy is the wrong answer for C-reporting-10: the moment the
organisation changes its dashboard, the copy is stale and nobody knows.

The layer is a per user, per dashboard record holding only the differences:
placement order, size and hidden set. Render applies the layer over the
shared dashboard at read time. Deleting the record is the reset, which is
why the reset is one action and cannot half-succeed.

A user with no layer sees exactly what the organisation composed, so the
feature costs nothing until somebody uses it.

### Reports ship as definitions, not as dashboards

A shipped report is a definition in the app: an id, a title, the
aggregation it runs, its parameters and its rendering. It is versioned with
the app and is not editable in place. Running one produces a result, and
"save as dashboard" turns it into an ordinary dashboard the user then owns.

Shipping them as seeded dashboards was rejected: seeded rows drift, cannot
be updated with the app, and multiply per instance.

### The status report is prefilled and then owned by a person

Odoo's project update generates a description and a health rating and then
lets a person edit it. That is the right split. The product computes the
figures, the person writes the sentence, and the record keeps both, so a
later reader can see which half was measured.

### Activity reporting is gated by a purpose, and the read is logged

Both activity candidates are surveillance-shaped. The design makes that
explicit rather than leaving it to a policy document:

- The capability is off until an administrator turns it on and records a
  purpose string.
- The purpose is shown on every screen that reports on a named person.
- Every read of a named person's activity is written to the audit trail
  with the reader, the subject and the period.
- A person can always read their own.

`dashboard-view-analytics` already sets the precedent in this repo: daily
buckets, no per-event rows, a retention window. The same shape is used
here.

### In-widget search filters the rendering, never the saved search

The filter lives in component state. It is not persisted, not shared, and
never writes to the placement. Reloading the dashboard clears it. That is
the whole of C-reporting-21, and the design keeps it that small on purpose,
because the failure the candidate names is a filter that silently becomes
the widget's definition.

### Geographic reporting is an aggregation, not a map widget

`map-support` renders a map. This is the reporting claim beside it: counts
and rates per area over a period, from objects with a location. It returns
rows, and the map widget is one way to render them.

### Storage reporting reads the platform, not the objects

Use per workspace and per service comes from Nextcloud's own storage
figures and from openregister's per register counts. Walking objects to
size them would be a second inventory that disagrees with the first.

## Risks

- **A personal layer hides a mandatory widget.** The layer respects
  `admin-templates` compulsory placements: a compulsory widget cannot be
  hidden, only moved.
- **Activity reporting is used for appraisal.** The purpose string and the
  read log do not prevent that. They make it visible, which is what the
  works council needs to review it.
- **A shipped report reads a schema an instance does not have.** A report
  declares the registers and schemas it needs, and is listed as unavailable
  rather than failing when they are missing.

## Open questions

- Whether a personal layer survives a re-sync of an admin template. The
  spec requires the layer to be re-evaluated and orphaned entries dropped,
  and does not choose whether the user is told.
- Which retention the activity read log takes. The spec requires a window
  and leaves the number to configuration.
