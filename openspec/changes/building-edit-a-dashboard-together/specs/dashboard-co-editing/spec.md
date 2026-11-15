# dashboard-co-editing

## ADDED Requirements

### Requirement: Several people SHALL be able to edit a shared dashboard at once

The system SHALL let every person with edit rights on a shared dashboard edit it at the same
time as others. Each editor SHALL see who else is editing that dashboard and since when. The
system SHALL save every change at once. A change to one widget SHALL NOT overwrite a colleague's
change to another widget.

Rows: `build-edit-lock` (launchpad matrix).

#### Scenario: A second editor edits instead of waiting
- **GIVEN** Marieke edits the shared dashboard "Mijn werkplek" since 10:42
- **WHEN** Sanne opens the same dashboard in edit mode
- **THEN** Sanne can edit it
- **AND** Sanne sees that Marieke edits it too, since 10:42

#### Scenario: Changes to different widgets both survive
- **GIVEN** Marieke and Sanne edit the same dashboard
- **WHEN** Marieke resizes the calendar widget and Sanne moves the news widget
- **THEN** the saved dashboard has both changes

### Requirement: Two changes to the same widget SHALL NOT silently replace each other

When two editors change the same widget, the system SHALL apply the first saved change and
refuse the later one, which was made on an older version of that widget. The editor whose change
was refused SHALL see the colleague's version and SHALL be able to make the change again on top
of it.

Rows: `build-edit-lock` (launchpad matrix).

#### Scenario: The later change to one widget is refused, not lost silently
- **GIVEN** Marieke and Sanne both have the calendar widget's settings open
- **WHEN** Marieke saves first and Sanne saves after
- **THEN** Marieke's change is kept
- **AND** Sanne sees Marieke's version and can apply her change again

### Requirement: A new widget SHALL land on the first free spot

When an editor adds a widget, the system SHALL place it on the first free spot in the saved
layout and SHALL NOT place it over another widget, also when a colleague changed the layout a
moment before.

Rows: `build-edit-lock` (launchpad matrix).

#### Scenario: A widget added during a colleague's edit takes a free spot
- **GIVEN** Marieke just moved a widget into the first free spot
- **WHEN** Sanne adds a new widget
- **THEN** the new widget goes to the next free spot and covers no other widget
