---
capability: dashboards-and-who-may-see-them
status: proposed
---

# Dashboards and who may see them

## Purpose

The reports launchpad ships, the personal layer over a dashboard somebody
else owns, and the activity, geographic and storage reporting the round 4
competitor sweep found in eleven other products. Sits on `dashboards`,
`widgets`, `activity-feed-integration` and `dashboard-view-analytics`.
Requested by the dossiq competitor programme, discovery cluster 12.

## ADDED Requirements

### Requirement: REQ-DWMS-001 A personal layer over a shared dashboard

A user MUST be able to reorder, resize and hide widget placements on a
dashboard they do not own. The differences MUST be stored as a personal
layer keyed by user and dashboard, holding only order, size and the hidden
set, and MUST be applied over the shared dashboard at render time. The
layer MUST be invisible to every other user. A placement marked compulsory
by `admin-templates` MUST NOT be hideable, and MAY be moved.

Candidate C-reporting-10, `should`, two driven passers (itop,
xxllnc-zaken).

#### Scenario: A handler rearranges the team dashboard for themselves

- **GIVEN** a group-shared dashboard with six widgets
- **WHEN** a member of the group moves two widgets and hides a third
- **THEN** that member sees their arrangement
- **AND** every other member still sees the arrangement the administrator composed

#### Scenario: A compulsory widget cannot be hidden

- **GIVEN** a shared dashboard with one compulsory placement
- **WHEN** a user tries to hide it
- **THEN** the request is refused and names the placement as compulsory

### Requirement: REQ-DWMS-002 One action puts the dashboard back

A user with a personal layer MUST be able to remove it in one action. The
removal MUST delete the whole layer, not part of it, and MUST return the
dashboard to what its owner composed. A user with no layer MUST see the
owner's arrangement unchanged.

Candidate C-reporting-10, the reset half.

#### Scenario: Reset to the organisation's arrangement

- **GIVEN** a user with a personal layer on a shared dashboard
- **WHEN** the user chooses to reset the dashboard
- **THEN** the layer is deleted and the owner's arrangement renders
- **AND** the action is confirmed before it runs

### Requirement: REQ-DWMS-003 Reports the product ships

The system MUST ship report definitions with the app: an id, a title, a
description, the aggregation to run, its parameters and its rendering. The
definitions MUST be listed in a gallery, MUST be runnable without composing
a dashboard, and MUST NOT be editable in place. A report whose registers or
schemas are absent MUST be listed as unavailable with the missing names,
and MUST NOT error when opened. A result MUST be saveable as an ordinary
dashboard owned by the user who saved it.

Candidate C-reporting-2, `could`, one driven passer (glpi).

#### Scenario: A report runs on a fresh instance

- **GIVEN** a new instance with no dashboards
- **WHEN** an administrator opens the report gallery and runs "Open cases by team"
- **THEN** the report renders without anything being composed first

#### Scenario: A report whose schema is missing says so

- **GIVEN** a report declaring a schema the instance has not installed
- **WHEN** the gallery is opened
- **THEN** the report is listed as unavailable and names the missing schema

### Requirement: REQ-DWMS-004 A dated status report with a health rating

The system MUST support a status report on a body of work carrying a date,
a health rating (`on-track`, `at-risk`, `off-track`), a prefilled summary
and the figures it was prefilled from. The figures MUST be computed by the
product, the summary MUST be editable before the report is kept, and the
kept report MUST record which values were computed and which were written
by a person.

Candidate C-reporting-3, `could`, one driven passer (odoo).

#### Scenario: A programme report is prefilled and then edited

- **GIVEN** a body of work with open and closed items and a deadline
- **WHEN** a user creates a status report
- **THEN** the health rating and the figures are prefilled
- **AND** the user edits the summary and keeps the report
- **AND** the report records the summary as written by a person

### Requirement: REQ-DWMS-005 Activity reporting names its purpose

Reporting on a named person's activity MUST be off until an administrator
enables it and records a purpose. The purpose MUST be shown on every screen
that reports on a named person. Every read of a named person's activity by
somebody else MUST be written to the audit trail with the reader, the
subject and the period. A person MUST always be able to read their own
activity, without the capability being enabled and without a log entry.

Candidates C-reporting-13 and C-reporting-19, both `could`. Driven passers:
plane for the first, forgejo, gitea and gitlab for the second.

#### Scenario: The capability is off by default

- **GIVEN** a fresh instance
- **WHEN** an administrator opens a person's activity
- **THEN** the screen says the capability is off and how to enable it

#### Scenario: Reading a colleague's activity is logged

- **GIVEN** activity reporting enabled with the purpose "workload balancing"
- **WHEN** an administrator reads September for a named user
- **THEN** the purpose is shown on the screen
- **AND** an audit entry records the reader, the subject and September

#### Scenario: A person reads their own activity

- **GIVEN** activity reporting disabled
- **WHEN** a user opens their own activity
- **THEN** it renders, and no audit entry is written

### Requirement: REQ-DWMS-006 Activity as a figure and as a calendar

With the capability enabled, the system MUST report a person's activity
across the product for a period as counts per activity type, and as a
calendar of intensity per day. Both MUST be bucketed per day, MUST be
derived from the existing activity events, and MUST be exportable as CSV.
Neither MUST report hours.

Candidates C-reporting-13 and C-reporting-19.

#### Scenario: A month as a heatmap

- **GIVEN** a user with activity on twelve days of September
- **WHEN** an administrator opens the calendar view for September
- **THEN** twelve days carry an intensity and the rest are empty

#### Scenario: The export carries the same numbers

- **GIVEN** the same period
- **WHEN** the administrator exports it
- **THEN** the CSV totals equal the figures on screen

### Requirement: REQ-DWMS-007 Recent colleague activity, filtered by what you may see

The system MUST offer a widget listing recent activity by other users,
built from the existing activity feed and filtered to what the reader is
allowed to see. An entry the reader may not see MUST be absent, not
redacted, and MUST NOT be counted in any total the widget shows.

Candidate C-reporting-26, `could`, one driven passer (zammad).

#### Scenario: A colleague's work on a hidden dashboard is not listed

- **GIVEN** a user who cannot see dashboard A
- **WHEN** a colleague edits dashboard A and the user opens the widget
- **THEN** the edit is absent from the list and from its count

### Requirement: REQ-DWMS-008 Search inside a widget's results

A widget rendering rows MUST offer a filter over its rendered rows. The
filter MUST live in view state only: it MUST NOT be persisted, MUST NOT be
shared with other users, MUST NOT modify the placement or its saved search,
and MUST be cleared by reloading the dashboard.

Candidate C-reporting-21, `could`, one driven passer (xxllnc-zaken).

#### Scenario: Filtering a widget leaves its definition alone

- **GIVEN** a widget with a saved search for open cases
- **WHEN** the user types a filter term and then reloads the page
- **THEN** the rows are filtered before the reload and unfiltered after it
- **AND** the placement's saved search is unchanged

### Requirement: REQ-DWMS-009 Geographic trend reporting

The system MUST report counts and rates per area over a period for objects
that carry a location, with the area taken from a declared property. An
object with no location MUST be counted in an explicit "no location"
bucket, never dropped silently.

Candidate C-reporting-25, `could`, one documented passer (mozard).
Documented, never counted in a driven tally (D21).

#### Scenario: Reports per district over a quarter

- **GIVEN** objects with a district property and a quarter to report on
- **WHEN** the report runs
- **THEN** each district carries a count and a rate
- **AND** objects without a district are counted in the "no location" bucket

### Requirement: REQ-DWMS-010 Storage use per workspace and service, over time

The system MUST report storage use per workspace and per service, as a
figure now and as a series over time, from the platform's own storage
figures and the register counts, never by walking objects. The report MUST
name what can be removed: items past their retention and orphaned uploads.
The report MUST be exportable as CSV, and MUST be administrator-only.

Candidate C-reporting-28, `could`, one driven passer (tuleap) and one
documented (youtrack).

#### Scenario: An administrator sees where the disk went

- **GIVEN** an instance with three workspaces
- **WHEN** an administrator opens the storage report
- **THEN** each workspace carries a current figure and a twelve-month series
- **AND** the removable total is named separately from the total in use

#### Scenario: A non-administrator is refused

- **GIVEN** an ordinary user
- **WHEN** they request the storage report
- **THEN** the request is refused with 403
