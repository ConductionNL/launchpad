# Delta: admin-templates

## ADDED Requirements

### Requirement: REQ-RESYNC-006 Add-only strategy adds new template widgets and changes nothing else

`POST /api/admin/templates/{id}/resync` SHALL accept `strategy: "add_only"`. Under it, each copy SHALL gain the
template placements it does not have yet, at the first free spot, and nothing else SHALL change: no placement is moved,
resized, updated or removed, also when the template removed it. Compulsory widgets SHALL still be reconciled as
REQ-RESYNC-004 says. The dry run SHALL report the same plan without writing.

#### Scenario: Two new widgets reach every copy

- **GIVEN** 212 copies of template "Intranet medewerkers" at version 13
- **AND** version 14 added Leesbevestiging and Verjaardagen and removed Oud nieuws
- **WHEN** the admin re-syncs with `{"strategy": "add_only"}`
- **THEN** every copy gains Leesbevestiging and Verjaardagen
- **AND** Oud nieuws stays on every copy that had it, and no other placement moves

### Requirement: REQ-RESYNC-007 Merge leaves a widget where the person put it

Under `strategy: "merge"`, a template placement that the copy's owner moved or resized after provisioning SHALL keep
the owner's position and size; the template's other changes to it (title, configuration) SHALL still apply. A
placement the owner did not touch SHALL follow the template as REQ-RESYNC-003 says.

#### Scenario: Alice moved the news widget

- **GIVEN** alice moved the template's "Nieuws" widget to the top of her copy
- **AND** the admin changed the template's "Nieuws" feed and moved it down
- **WHEN** the admin re-syncs with `{"strategy": "merge"}`
- **THEN** alice's "Nieuws" stays at the top and reads the new feed

### Requirement: REQ-RESYNC-008 The rollout window says what will happen

"Push changes" on a template (board LpBeheerSjablonen) SHALL open a window that names the template, its version and the
number of copies, and SHALL state from a dry run which widgets the template gained and lost since the copies were last
synced. It SHALL offer "How to update?" with three choices, each with its consequence: Only add new widgets (nothing
disappears; personal layout stays; `add_only`), Keep personal changes (the template wins, except what someone moved or
added themselves; `merge`), Replace everything with the template (personal changes are lost; `overwrite`). It SHALL say
how many people changed their copy, and SHALL name the number of dashboards on the confirm button ("Push to 212
dashboards"). Only Keep personal changes is preselected.

#### Scenario: The admin opens the rollout window

- **GIVEN** template "Intranet medewerkers" version 14 with 212 copies, 38 changed by their owners
- **WHEN** the admin chooses Push changes
- **THEN** the window says the template gained Leesbevestiging and Verjaardagen and lost Oud nieuws
- **AND** it offers the three choices with Keep personal changes selected, says 38 people changed their dashboard, and the button reads "Push to 212 dashboards"
