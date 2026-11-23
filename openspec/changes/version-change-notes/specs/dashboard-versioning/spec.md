# Delta: dashboard-versioning

## MODIFIED Requirements

### Requirement: The owner sees the version history (REQ-VERSUI-001)

The versions of a dashboard MUST be the tab Versions of the dashboard settings (dashboards REQ-DASH-039, board LpVersies), offered to the dashboard owner and to administrators when versioning is supported for the dashboard. There MUST be no "Version history" entry in the dashboard menu or in More. The tab MUST list versions newest first in the columns When, By and What changed, and MUST mark the newest as the current version.

#### Scenario: Owner opens the history

- **GIVEN** Sanne owns the personal dashboard "Mijn week" and it has four saved versions
- **WHEN** she opens the dashboard settings and chooses the tab Versions
- **THEN** the tab lists the four versions newest first with when, by whom and what changed, the newest marked as the current version

#### Scenario: Colleague sees no entry

- **GIVEN** Pieter can view but does not own "Mijn week" and is not an administrator
- **WHEN** he opens More on the dashboard
- **THEN** no version entry is shown and he cannot open the dashboard settings

#### Scenario: Unsupported dashboard

- **GIVEN** a groupfolder-backed dashboard for which `modeSupported` is false
- **WHEN** the owner opens the tab Versions
- **THEN** the tab says this dashboard keeps no versions

@e2e exclude never built: the groupfolder backend does not exist. `Dashboard` has no `contentBackend` field, so `DashboardVersionService::isGroupfolderBacked()` (lib/Service/DashboardVersionService.php:588) always answers false, `IVersionManager` is never called anywhere in lib/ (it appears only in a doc comment), and every dashboard is database-backed, so no dashboard answers `modeSupported: false`.

## ADDED Requirements

### Requirement: Each automatic version says what changed (REQ-VERSUI-004)

An automatic snapshot (REQ-VERS-001) MUST carry a note of at most 120 characters that names what changed since the
previous snapshot: "Dashboard renamed to {name}", "Columns from {old} to {new}", "Widget {title} added", "Widget
{title} removed", "Widget {old} renamed to {new}", "Widget {title} moved", "Widget {title} resized". When more than one
thing changed, the note MUST name the first and count the rest ("Widget Inbox added and 2 more changes"). A widget is
named by its title, else by its type. An explicit snapshot with a note of its own (REQ-VERSUI-002) MUST keep that note.
Notes MUST be in the language of the person who made the change.

#### Scenario: A widget moved

- **GIVEN** Pieter moves the widget Nieuws on Intranet Zuiddrecht
- **WHEN** the snapshot is written
- **THEN** the Versions tab shows "Widget Nieuws moved" by Pieter Jansen

#### Scenario: Several changes at once

- **GIVEN** Femke adds the widget Inbox, removes Oud nieuws and changes the columns from 8 to 12 in one save
- **WHEN** the snapshot is written
- **THEN** its note reads "Widget Inbox added and 2 more changes"

### Requirement: A template update is noted as such (REQ-VERSUI-005)

When a template re-sync (admin-templates REQ-RESYNC-001) changes a copy, the version it writes MUST name the template as
its author ("Template {name}") and MUST carry the note "Updated from the template", instead of the administrator who
ran the re-sync and a list of widget changes.

#### Scenario: Intranet updated from its template

- **GIVEN** Intranet Zuiddrecht is a copy of template "Intranet"
- **WHEN** the administrator re-syncs the template
- **THEN** the copy's Versions tab shows a version by "Template Intranet" with "Updated from the template"
